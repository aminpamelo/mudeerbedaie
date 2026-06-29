<?php

namespace App\Http\Controllers\Shipping;

use App\Console\Commands\SyncEasyParcelTracking;
use App\Http\Controllers\Controller;
use App\Models\ProductOrder;
use App\Services\Shipping\EasyParcelTrackingSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives push callbacks from EasyParcel and reconciles the matching local
 * order. Handles the three relevant topics (verified against EasyParcel's
 * OpenAPI source):
 *
 *  - shipment.tracking.update — awb_number + latest_tracking_status + latest_shipment_status_code (+ status_log[])
 *  - shipment.status.update   — awb_number + shipment_status + shipment_status_code
 *  - shipment.awb.update      — awb_number + shipment_number + awb_url + tracking_url (no status; backfills the AWB)
 *
 * Orders are correlated by awb_number (→ tracking_id) OR shipment_number
 * (→ metadata.easyparcel_shipment_number), so an order booked with a still-pending
 * AWB is linked the moment shipment.awb.update arrives.
 *
 * EasyParcel signs nothing (no signature header in any docs version), so the
 * endpoint is protected by an unguessable ?secret= token in the registered URL.
 * Always answers 200 so EasyParcel does not retry-storm; unknown shipments and
 * unparsable payloads are logged, not errored. The cron poller
 * ({@see SyncEasyParcelTracking}) is the safety net for when no webhook arrives.
 */
class EasyParcelWebhookController extends Controller
{
    /** Payload keys carrying the AWB / tracking number (real key is awb_number). */
    private const AWB_KEYS = ['awb_number', 'awb_no', 'awb', 'tracking_number', 'tracking_no', 'tracking_id'];

    /** Payload keys carrying the human status label (real keys: latest_tracking_status / shipment_status). */
    private const STATUS_KEYS = ['latest_tracking_status', 'shipment_status', 'tracking_status', 'current_status'];

    /** Payload keys carrying the authoritative numeric status code. */
    private const STATUS_CODE_KEYS = ['latest_shipment_status_code', 'shipment_status_code', 'status_code'];

    public function handle(Request $request, EasyParcelTrackingSync $sync): JsonResponse
    {
        if (! $this->secretValid($request)) {
            Log::warning('EasyParcel webhook rejected: invalid secret', ['ip' => $request->ip()]);

            return response()->json(['received' => false], 401);
        }

        $payload = $request->all();

        $topic = $this->extract($payload, ['topic']);
        $awb = $this->extract($payload, self::AWB_KEYS);
        $shipmentNumber = $this->extract($payload, ['shipment_number']);
        $statusString = $this->extract($payload, self::STATUS_KEYS);
        $statusCode = $this->extractInt($payload, self::STATUS_CODE_KEYS);
        $awbUrl = $this->extract($payload, ['awb_url']);
        $trackingUrl = $this->extract($payload, ['tracking_url']);

        if (blank($awb) && blank($shipmentNumber)) {
            Log::info('EasyParcel webhook without AWB or shipment number', ['topic' => $topic, 'payload' => $payload]);

            return response()->json(['received' => true]);
        }

        $order = $this->findOrder($awb, $shipmentNumber);

        if (! $order) {
            Log::info('EasyParcel webhook for unknown shipment', [
                'topic' => $topic,
                'awb' => $awb,
                'shipment_number' => $shipmentNumber,
            ]);

            return response()->json(['received' => true]);
        }

        // Backfill the AWB / label first (shipment.awb.update, or any payload that
        // carries a freshly generated AWB for an order still missing one).
        $awbApplied = false;
        if (filled($awb) || filled($awbUrl) || filled($trackingUrl)) {
            $awbApplied = $sync->applyAwb($order, $awb, $awbUrl, $trackingUrl);
        }

        // Then reconcile the order status (shipment.status.update / shipment.tracking.update).
        $resolved = null;
        if (filled($statusString) || $statusCode !== null) {
            $resolved = $sync->apply($order, $statusString, $statusCode);
        }

        Log::info('EasyParcel webhook applied', [
            'order_id' => $order->id,
            'topic' => $topic,
            'awb' => $awb,
            'shipment_number' => $shipmentNumber,
            'reported_status' => $statusString,
            'status_code' => $statusCode,
            'awb_backfilled' => $awbApplied,
            'resolved_status' => $resolved,
        ]);

        return response()->json(['received' => true]);
    }

    /**
     * Correlate the callback to a local EasyParcel order by AWB (tracking_id) or,
     * when the AWB is not yet known locally, by the EasyParcel shipment number
     * stashed in metadata at booking time.
     */
    private function findOrder(?string $awb, ?string $shipmentNumber): ?ProductOrder
    {
        if (blank($awb) && blank($shipmentNumber)) {
            return null;
        }

        return ProductOrder::query()
            ->where('shipping_provider', 'easyparcel')
            ->where(function ($query) use ($awb, $shipmentNumber): void {
                if (filled($awb)) {
                    $query->orWhere('tracking_id', $awb);
                }

                if (filled($shipmentNumber)) {
                    $query->orWhere('metadata->easyparcel_shipment_number', $shipmentNumber);
                }
            })
            ->first();
    }

    /**
     * Validate the shared secret. EasyParcel sends no signature of its own, so the
     * endpoint is guarded by a token: register the URL as
     * /webhooks/easyparcel?secret=<token> (or send it as X-EasyParcel-Signature).
     * When no secret is configured the endpoint is open, so it works before being
     * locked down — set EASYPARCEL_WEBHOOK_SECRET to require it.
     */
    private function secretValid(Request $request): bool
    {
        $secret = config('services.easyparcel.webhook_secret');

        if (blank($secret)) {
            return true;
        }

        $provided = $request->query('secret') ?: $request->header('X-EasyParcel-Signature');

        return is_string($provided) && hash_equals((string) $secret, $provided);
    }

    /**
     * First non-empty string value for any candidate key, searching the top level
     * and a nested `data` object.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     */
    private function extract(array $payload, array $keys): ?string
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        foreach ($keys as $key) {
            foreach ([$payload, $data] as $source) {
                if (filled($source[$key] ?? null)) {
                    return (string) $source[$key];
                }
            }
        }

        return null;
    }

    /**
     * First numeric value (including 0) for any candidate key. Status code 0 is a
     * real value (Cancelled), so this cannot lean on filled().
     *
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     */
    private function extractInt(array $payload, array $keys): ?int
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        foreach ($keys as $key) {
            foreach ([$payload, $data] as $source) {
                $value = $source[$key] ?? null;

                if (is_int($value) || (is_string($value) && is_numeric($value) && $value !== '')) {
                    return (int) $value;
                }
            }
        }

        return null;
    }
}
