<?php

namespace App\Services\Orders;

use App\Helpers\PhoneNumberHelper;
use App\Mail\OrderShippedNotification;
use App\Models\CekbotSession;
use App\Models\OrderTrackingNotification;
use App\Models\ProductOrder;
use App\Models\WhatsAppTemplate;
use App\Services\MergeTag\MergeTagEngine;
use App\Services\WhatsApp\WahaSessionManager;
use App\Services\WhatsApp\WhatsAppBlastService;
use App\Services\WhatsApp\WhatsAppManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TrackingNotificationService
{
    /**
     * Build the default customer-friendly Malay message with the order's
     * tracking details filled in. WhatsApp *bold* markers are used; the email
     * view strips them.
     */
    public function defaultMessage(ProductOrder $order): string
    {
        $name = $order->getCustomerName();
        $store = (string) config('store.name', config('app.name', 'Kami'));
        $courier = $order->shipping_provider_label;
        $tracking = $order->tracking_id ?: '—';
        $url = $order->tracking_url;

        $lines = [];
        $lines[] = "Salam {$name} 👋";
        $lines[] = '';
        $lines[] = "Terima kasih kerana membeli-belah dengan {$store}! 🌸";
        $lines[] = '';
        $via = $courier ? " melalui {$courier}" : '';
        $lines[] = "Pesanan anda *{$order->order_number}* telah pun dihantar{$via}.";
        $lines[] = '';
        $lines[] = "📦 No. Tracking: *{$tracking}*";
        if ($url) {
            $lines[] = "🔗 Jejak pakej: {$url}";
        }
        $lines[] = '';
        $lines[] = 'Anda boleh menyemak status penghantaran menggunakan pautan di atas. Jika ada sebarang pertanyaan, balas sahaja mesej ini — kami sedia membantu 🤍';
        $lines[] = '';
        $lines[] = 'Ikhlas,';
        $lines[] = "Pasukan {$store}";

        return implode("\n", $lines);
    }

    /**
     * Which channels can actually reach this order's customer, with a reason
     * when they cannot.
     *
     * @return array<string, array{available: bool, target: string, reason: ?string}>
     */
    public function channelAvailability(ProductOrder $order): array
    {
        $email = $order->getCustomerEmail();
        $hasEmail = $email !== 'No email provided';
        $emailSyntaxOk = $hasEmail && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        $emailDeliverable = $emailSyntaxOk && $this->isEmailDeliverable($email);

        $rawPhone = $order->getCustomerPhone();
        $phone = PhoneNumberHelper::normalize($rawPhone);
        $hasPhone = $phone !== null;

        $waha = app(WahaSessionManager::class);
        $wahaConfigured = $waha->isConfigured();
        $wahaSession = CekbotSession::where('status', CekbotSession::STATUS_WORKING)->exists();

        $metaConfigured = app(WhatsAppManager::class)->metaProvider()->isConfigured();
        $hasApprovedTemplate = WhatsAppTemplate::approved()->exists();

        return [
            OrderTrackingNotification::CHANNEL_EMAIL => [
                'available' => $emailDeliverable,
                'target' => $hasEmail ? $email : '—',
                'reason' => match (true) {
                    ! $hasEmail => 'Tiada alamat e-mel pelanggan',
                    ! $emailSyntaxOk => 'Format e-mel tidak sah',
                    ! $emailDeliverable => 'Domain e-mel tiada rekod MX — risiko bounce',
                    default => null,
                },
            ],
            OrderTrackingNotification::CHANNEL_WHATSAPP_WAHA => [
                'available' => $hasPhone && $wahaConfigured && $wahaSession,
                'target' => $hasPhone ? PhoneNumberHelper::format($phone) : ($rawPhone ?: '—'),
                'reason' => match (true) {
                    ! $hasPhone => 'Nombor telefon tiada / disembunyikan',
                    ! $wahaConfigured => 'Pelayan WAHA belum dikonfigurasi',
                    ! $wahaSession => 'Tiada sesi Cekbot yang aktif',
                    default => null,
                },
            ],
            OrderTrackingNotification::CHANNEL_WHATSAPP_META => [
                'available' => $hasPhone && $metaConfigured && $hasApprovedTemplate,
                'target' => $hasPhone ? PhoneNumberHelper::format($phone) : ($rawPhone ?: '—'),
                'reason' => match (true) {
                    ! $hasPhone => 'Nombor telefon tiada / disembunyikan',
                    ! $metaConfigured => 'Meta Cloud API belum dikonfigurasi',
                    ! $hasApprovedTemplate => 'Tiada template diluluskan',
                    default => null,
                },
            ],
        ];
    }

    /**
     * Whether an email is worth sending to: valid syntax AND the domain has a
     * mail record (MX, or fallback A). Cached per domain so the DNS lookup runs
     * at most once a day. Guards our SMTP reputation against hard bounces.
     */
    public function isEmailDeliverable(?string $email): bool
    {
        if ($email === null || $email === '' || $email === 'No email provided') {
            return false;
        }

        if (! str_contains($email, '@') || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $domain = strtolower(Str::afterLast($email, '@'));

        if ($domain === '') {
            return false;
        }

        return Cache::remember('tracking_mx:'.$domain, now()->addDay(), function () use ($domain) {
            try {
                return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
            } catch (\Throwable $e) {
                return false;
            }
        });
    }

    /**
     * Send a tracking notification through the chosen channel and record it.
     *
     * @param  array{message?: string, template?: ?WhatsAppTemplate}  $payload
     */
    public function send(ProductOrder $order, string $channel, array $payload = [], ?int $userId = null): OrderTrackingNotification
    {
        return match ($channel) {
            OrderTrackingNotification::CHANNEL_EMAIL => $this->notifyEmail($order, (string) ($payload['message'] ?? ''), $userId),
            OrderTrackingNotification::CHANNEL_WHATSAPP_WAHA => $this->notifyWaha($order, (string) ($payload['message'] ?? ''), $userId),
            OrderTrackingNotification::CHANNEL_WHATSAPP_META => $this->notifyMeta($order, $payload['template'] ?? null, $userId),
            default => $this->record($order, $channel, null, OrderTrackingNotification::STATUS_FAILED, null, null, 'Saluran tidak dikenali', [], $userId),
        };
    }

    protected function notifyEmail(ProductOrder $order, string $message, ?int $userId): OrderTrackingNotification
    {
        $email = $order->getCustomerEmail();

        if (! $this->isEmailDeliverable($email)) {
            return $this->record($order, OrderTrackingNotification::CHANNEL_EMAIL, $email === 'No email provided' ? null : $email, OrderTrackingNotification::STATUS_FAILED, null, $message, 'E-mel tidak sah atau domain berisiko bounce', [], $userId);
        }

        try {
            Mail::to($email)->queue(new OrderShippedNotification($order, $message));

            return $this->record($order, OrderTrackingNotification::CHANNEL_EMAIL, $email, OrderTrackingNotification::STATUS_SENT, null, $message, null, ['queued' => true], $userId);
        } catch (\Throwable $e) {
            return $this->record($order, OrderTrackingNotification::CHANNEL_EMAIL, $email, OrderTrackingNotification::STATUS_FAILED, null, $message, $e->getMessage(), [], $userId);
        }
    }

    protected function notifyWaha(ProductOrder $order, string $message, ?int $userId): OrderTrackingNotification
    {
        $phone = PhoneNumberHelper::normalize($order->getCustomerPhone());

        if ($phone === null) {
            return $this->record($order, OrderTrackingNotification::CHANNEL_WHATSAPP_WAHA, null, OrderTrackingNotification::STATUS_FAILED, null, $message, 'Nombor telefon tiada / disembunyikan', [], $userId);
        }

        $manager = app(WahaSessionManager::class);

        if (! $manager->isConfigured()) {
            return $this->record($order, OrderTrackingNotification::CHANNEL_WHATSAPP_WAHA, $phone, OrderTrackingNotification::STATUS_FAILED, null, $message, 'Pelayan WAHA belum dikonfigurasi', [], $userId);
        }

        $session = CekbotSession::where('status', CekbotSession::STATUS_WORKING)->first();

        if ($session === null) {
            return $this->record($order, OrderTrackingNotification::CHANNEL_WHATSAPP_WAHA, $phone, OrderTrackingNotification::STATUS_FAILED, null, $message, 'Tiada sesi Cekbot yang aktif', [], $userId);
        }

        $result = $manager->sendText($session->session_name, $phone, $message);

        return $this->record(
            $order,
            OrderTrackingNotification::CHANNEL_WHATSAPP_WAHA,
            $phone,
            $result['success'] ? OrderTrackingNotification::STATUS_SENT : OrderTrackingNotification::STATUS_FAILED,
            $result['message_id'] ?? null,
            $message,
            $result['success'] ? null : ($result['error'] ?? 'Gagal menghantar'),
            ['session' => $session->session_name],
            $userId,
        );
    }

    protected function notifyMeta(ProductOrder $order, ?WhatsAppTemplate $template, ?int $userId): OrderTrackingNotification
    {
        if (! $template instanceof WhatsAppTemplate) {
            return $this->record($order, OrderTrackingNotification::CHANNEL_WHATSAPP_META, null, OrderTrackingNotification::STATUS_FAILED, null, null, 'Tiada template dipilih', [], $userId);
        }

        $phone = PhoneNumberHelper::normalize($order->getCustomerPhone());

        if ($phone === null) {
            return $this->record($order, OrderTrackingNotification::CHANNEL_WHATSAPP_META, null, OrderTrackingNotification::STATUS_FAILED, null, null, 'Nombor telefon tiada / disembunyikan', ['template' => $template->name], $userId);
        }

        $provider = app(WhatsAppManager::class)->metaProvider();

        if (! $provider->isConfigured()) {
            return $this->record($order, OrderTrackingNotification::CHANNEL_WHATSAPP_META, $phone, OrderTrackingNotification::STATUS_FAILED, null, null, 'Meta Cloud API belum dikonfigurasi', ['template' => $template->name], $userId);
        }

        $language = $template->language;
        $components = $this->buildMetaComponents($template, $order);
        $result = $provider->sendTemplate($phone, $template->name, $language, $components);

        return $this->record(
            $order,
            OrderTrackingNotification::CHANNEL_WHATSAPP_META,
            $phone,
            $result['success'] ? OrderTrackingNotification::STATUS_SENT : OrderTrackingNotification::STATUS_FAILED,
            $result['message_id'] ?? null,
            null,
            $result['success'] ? null : ($result['error'] ?? 'Gagal menghantar'),
            ['template' => $template->name, 'language' => $language, 'components' => $components],
            $userId,
        );
    }

    /**
     * Build Meta template BODY components by resolving the template's OWN saved
     * variable mappings (configured in the WhatsApp Templates module) against the
     * order through the MergeTag engine. This makes the template module the single
     * source of truth — {{2}} => order.tracking_number is honoured everywhere.
     *
     * @return array<int, array{type: string, parameters: array<int, array{type: string, text: string}>}>
     */
    public function buildMetaComponents(WhatsAppTemplate $template, ProductOrder $order): array
    {
        $params = $this->resolvedBodyParams($template, $order);

        if ($params === []) {
            return [];
        }

        return [[
            'type' => 'body',
            'parameters' => array_map(
                fn (string $text): array => ['type' => 'text', 'text' => $text],
                array_values($params),
            ),
        ]];
    }

    /**
     * Render the template BODY with each {{n}} replaced by the value its saved
     * mapping resolves to for this order (used for the modal preview). Unmapped
     * placeholders are kept visible so gaps are obvious.
     */
    public function renderMetaPreview(WhatsAppTemplate $template, ProductOrder $order): string
    {
        $body = app(WhatsAppBlastService::class)->bodyText($template);

        if ($body === '') {
            return '';
        }

        $params = $this->resolvedBodyParams($template, $order);

        return preg_replace_callback('/\{\{\s*(\d+)\s*\}\}/', function (array $m) use ($params): string {
            $index = (int) $m[1];

            if (array_key_exists($index, $params) && $params[$index] !== '') {
                return $params[$index];
            }

            return $m[0];
        }, $body);
    }

    /**
     * Resolve each BODY placeholder (1..N) to a string using the template's saved
     * variable mapping. Empty / "custom" slots resolve to an empty string so the
     * parameter count always matches the placeholder count (Meta requirement).
     * When a template has no mapping at all, fall back to sensible order defaults.
     *
     * @return array<int, string> 1-indexed resolved values
     */
    protected function resolvedBodyParams(WhatsAppTemplate $template, ProductOrder $order): array
    {
        $count = app(WhatsAppBlastService::class)->variableCount($template, 'BODY');

        if ($count < 1) {
            return [];
        }

        $mappings = is_array($template->variable_mappings) ? ($template->variable_mappings['body'] ?? []) : [];
        $hasMapping = collect($mappings)->contains(fn ($f) => is_string($f) && $f !== '' && $f !== 'custom');
        $defaults = ['contact.name', 'order.number', 'order.tracking_number', 'order.tracking_url', 'order.courier'];

        $engine = app(MergeTagEngine::class)->setContext($this->mergeTagContext($order));

        $params = [];
        for ($i = 1; $i <= $count; $i++) {
            $field = $mappings[$i] ?? $mappings[(string) $i] ?? '';

            if ((! is_string($field) || $field === '' || $field === 'custom') && ! $hasMapping) {
                $field = $defaults[$i - 1] ?? 'order.number';
            }

            $params[$i] = (is_string($field) && $field !== '' && $field !== 'custom')
                ? $engine->resolve('{{'.$field.'}}')
                : '';
        }

        return $params;
    }

    /**
     * MergeTag context for an order (loads items so order.items_list resolves).
     *
     * @return array<string, mixed>
     */
    protected function mergeTagContext(ProductOrder $order): array
    {
        $order->loadMissing(['items.product']);

        return ['product_order' => $order];
    }

    /**
     * Persist a notification row, stamp a summary on the order for the list
     * badge, and drop a system note on the order timeline.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function record(
        ProductOrder $order,
        string $channel,
        ?string $recipient,
        string $status,
        ?string $providerMessageId,
        ?string $message,
        ?string $error,
        array $meta,
        ?int $userId,
    ): OrderTrackingNotification {
        $notification = $order->trackingNotifications()->create([
            'channel' => $channel,
            'recipient' => $recipient,
            'status' => $status,
            'provider_message_id' => $providerMessageId,
            'message' => $message,
            'error' => $error,
            'meta' => $meta,
            'sent_by' => $userId,
        ]);

        $order->addSystemNote(
            $status === OrderTrackingNotification::STATUS_SENT
                ? "Tracking dihantar kepada pelanggan via {$notification->channelLabel()}".($recipient ? " ({$recipient})" : '')
                : "Gagal hantar tracking via {$notification->channelLabel()}: {$error}",
            ['tracking_notification_id' => $notification->id],
        );

        return $notification;
    }
}
