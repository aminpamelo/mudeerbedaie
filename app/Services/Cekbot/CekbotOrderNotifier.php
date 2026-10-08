<?php

namespace App\Services\Cekbot;

use App\Models\CekbotConversation;
use App\Models\ProductOrder;
use Illuminate\Support\Facades\Log;

/**
 * Keeps WhatsApp-bot customers updated on their order: when a Cekbot order's
 * payment is confirmed, or it starts processing / ships, the customer gets a
 * WhatsApp message in the same chat the order came from.
 *
 * Driven by ProductOrder's `updated` hook, so it fires no matter where the
 * team changes the order (Cekbot Orders page, admin order page, EasyParcel
 * sync). Each milestone is sent at most once, tracked in the order metadata;
 * a tracking number added after shipping is sent as a follow-up (once per number).
 */
class CekbotOrderNotifier
{
    public function __construct(private CekbotBotService $bot) {}

    public function handle(ProductOrder $order): void
    {
        if ($order->source !== 'whatsapp_bot') {
            return;
        }

        $notifiedTracking = data_get($order->metadata, 'cekbot_notified.tracking_id');

        $milestone = match (true) {
            $order->wasChanged('payment_status') && $order->payment_status === 'paid' => 'paid',
            $order->wasChanged('status') && $order->status === 'shipped' => 'shipped',
            // Shipped first, tracking number added later (e.g. EasyParcel AWB
            // generated after booking) — send the number as a follow-up.
            $order->status === 'shipped' && $order->wasChanged('tracking_id')
                && filled($order->tracking_id) && $order->tracking_id !== $notifiedTracking
                && data_get($order->metadata, 'cekbot_notified.shipped') => 'tracking',
            $order->wasChanged('status') && $order->status === 'processing' => 'processing',
            default => null,
        };

        if ($milestone === null || ($milestone !== 'tracking' && data_get($order->metadata, "cekbot_notified.{$milestone}"))) {
            return;
        }

        try {
            $conversation = CekbotConversation::query()
                ->with('session')
                ->find(data_get($order->metadata, 'cekbot_conversation_id'));

            if (! $conversation?->session) {
                return;
            }

            // Not paced: this runs inside the team's order save request.
            $this->bot->sendReply($conversation, $this->message($order, $milestone), paced: false);

            $metadata = $order->metadata ?? [];
            $metadata['cekbot_notified'][$milestone] = now()->toIso8601String();
            if (in_array($milestone, ['shipped', 'tracking'], true) && filled($order->tracking_id)) {
                $metadata['cekbot_notified']['tracking_id'] = $order->tracking_id;
            }
            $order->metadata = $metadata;
            $order->saveQuietly();
        } catch (\Throwable $e) {
            Log::warning('Cekbot order notification failed', [
                'order_id' => $order->id,
                'milestone' => $milestone,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function message(ProductOrder $order, string $milestone): string
    {
        $number = '*'.$order->order_number.'*';

        return match ($milestone) {
            'paid' => "Alhamdulillah, pembayaran untuk pesanan {$number} telah disahkan ✅ Terima kasih! Kami akan proses pesanan anda segera 🙏",
            'processing' => "Pesanan {$number} anda sedang kami proses sekarang 📦 Kami akan maklumkan bila pesanan dihantar ye 🙏",
            'shipped' => $this->shippedMessage($order, $number),
            'tracking' => implode("\n", [
                "No. tracking untuk pesanan {$number} 🚚",
                '',
                ...$this->trackingLines($order),
            ]),
        };
    }

    private function shippedMessage(ProductOrder $order, string $number): string
    {
        $lines = ["Pesanan {$number} anda kini dalam proses penghantaran 🚚 InsyaAllah 2-3 hari lagi akan sampai."];

        if ($order->tracking_id) {
            $lines[] = '';
            array_push($lines, ...$this->trackingLines($order));
        }

        $lines[] = '';
        $lines[] = 'Terima kasih kerana membeli dengan kami 🤍';

        return implode("\n", $lines);
    }

    /**
     * @return array<int, string>
     */
    private function trackingLines(ProductOrder $order): array
    {
        return [
            'No. tracking: *'.$order->tracking_id.'*'.($order->shipping_provider_label ? ' ('.$order->shipping_provider_label.')' : ''),
            'Semak status: '.$order->tracking_url,
        ];
    }
}
