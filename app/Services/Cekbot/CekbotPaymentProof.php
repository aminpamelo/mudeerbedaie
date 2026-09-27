<?php

namespace App\Services\Cekbot;

use App\Models\CekbotConversation;
use App\Models\CekbotMessage;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Payment-proof handling for transfer orders created by a Cekbot flow: stores
 * the receipt the customer sends on WhatsApp against the order (awaiting team
 * confirmation), and lets the team confirm the payment — which marks the order
 * paid and tells the customer on WhatsApp.
 */
class CekbotPaymentProof
{
    public function __construct(private CekbotOutbound $out) {}

    /**
     * Attach the receipt from an inbound image/document message to the order.
     * The payment stays pending until the team confirms it.
     */
    public function attach(ProductOrder $order, CekbotConversation $conversation, CekbotMessage $message): void
    {
        $media = $this->out->fetchInboundMedia($conversation->session, $message);
        $path = null;

        if ($media) {
            $path = 'cekbot-receipts/'.$order->order_number.'-'.Str::random(6).'.'.$this->extension($media['mime']);
            Storage::disk('public')->put($path, $media['body']);
        }

        $order->update([
            'receipt_attachment' => $path ?? $order->receipt_attachment,
            'metadata' => array_merge($order->metadata ?? [], [
                'payment_proof_submitted_at' => now()->toIso8601String(),
                'payment_proof_message_id' => $message->id,
            ]),
        ]);

        $order->addSystemNote(
            $path
                ? 'Bukti bayaran diterima melalui WhatsApp — menunggu pengesahan team.'
                : 'Pelanggan hantar bukti bayaran melalui WhatsApp tetapi fail tidak dapat dimuat turun — semak di Cekbot Inbox.',
            ['cekbot_message_id' => $message->id],
        );
    }

    /**
     * Whether the customer has sent a payment proof for this order.
     */
    public static function submitted(ProductOrder $order): bool
    {
        return filled(data_get($order->metadata, 'payment_proof_submitted_at'));
    }

    /**
     * Team confirms the transfer: mark the order paid and notify the customer.
     */
    public function confirm(ProductOrder $order, User $user, CekbotBotService $bot): void
    {
        $order->markPaymentAsConfirmed($user->id, (string) $order->receipt_attachment);
        $order->addSystemNote('Bayaran transfer disahkan oleh '.$user->name.' (Cekbot).');

        $conversation = CekbotConversation::query()
            ->with('session')
            ->find(data_get($order->metadata, 'cekbot_conversation_id'));

        if ($conversation?->session) {
            $bot->sendReply($conversation, 'Alhamdulillah, pembayaran untuk pesanan *'.$order->order_number.'* telah disahkan ✅ Terima kasih! Kami akan proses pesanan anda segera 🙏');
        }
    }

    private function extension(string $mime): string
    {
        return match (true) {
            str_contains($mime, 'pdf') => 'pdf',
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            default => 'jpg',
        };
    }
}
