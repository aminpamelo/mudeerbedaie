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
 * sync). Each milestone is sent at most once, tracked in the order metadata.
 */
class CekbotOrderNotifier
{
    public function __construct(private CekbotBotService $bot) {}

    public function handle(ProductOrder $order): void
    {
        if ($order->source !== 'whatsapp_bot') {
            return;
        }

        $milestone = match (true) {
            $order->wasChanged('payment_status') && $order->payment_status === 'paid' => 'paid',
            $order->wasChanged('status') && $order->status === 'shipped' => 'shipped',
            $order->wasChanged('status') && $order->status === 'processing' => 'processing',
            default => null,
        };

        if ($milestone === null || data_get($order->metadata, "cekbot_notified.{$milestone}")) {
            return;
        }

        try {
            $conversation = CekbotConversation::query()
                ->with('session')
                ->find(data_get($order->metadata, 'cekbot_conversation_id'));

            if (! $conversation?->session) {
                return;
            }

            $this->bot->sendReply($conversation, $this->message($order, $milestone));

            $metadata = $order->metadata ?? [];
            $metadata['cekbot_notified'][$milestone] = now()->toIso8601String();
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
        };
    }

    private function shippedMessage(ProductOrder $order, string $number): string
    {
        $lines = ["Pesanan {$number} anda telah dihantar 🚚"];

        if ($order->tracking_id) {
            $lines[] = '';
            $lines[] = 'No. tracking: *'.$order->tracking_id.'*'.($order->shipping_provider_label ? ' ('.$order->shipping_provider_label.')' : '');
            $lines[] = 'Semak status: '.$order->tracking_url;
        }

        $lines[] = '';
        $lines[] = 'Terima kasih kerana membeli dengan kami 🤍';

        return implode("\n", $lines);
    }
}
