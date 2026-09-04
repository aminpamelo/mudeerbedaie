<?php

namespace App\Services\Cekbot\Checks;

use App\Models\CekbotConversation;
use App\Models\ProductOrder;
use Illuminate\Support\Str;

/**
 * Answers "where is my order / status pesanan <no>" queries with live data
 * from ProductOrder.
 */
class OrderStatusChecker implements CekbotChecker
{
    private const TRIGGERS = ['pesanan', 'order', 'tracking', 'penghantaran', 'status', 'resit', 'parcel', 'poslaju'];

    public function respond(string $body, CekbotConversation $conversation): ?string
    {
        $lower = Str::lower($body);

        if (! Str::contains($lower, self::TRIGGERS)) {
            return null;
        }

        // Candidate order-number tokens: contain a digit and are 4+ chars.
        preg_match_all('/\b[A-Za-z0-9\-]*\d[A-Za-z0-9\-]*\b/', $body, $matches);
        $candidates = array_values(array_filter($matches[0], fn ($t) => strlen($t) >= 4));

        if (empty($candidates)) {
            return null;
        }

        $order = ProductOrder::query()
            ->where(fn ($q) => $q
                ->whereIn('order_number', $candidates)
                ->orWhereIn('platform_order_number', $candidates))
            ->latest('id')
            ->first();

        if (! $order) {
            return 'Maaf, kami tak jumpa pesanan dengan nombor tersebut. Sila semak semula nombor pesanan anda. 🙏';
        }

        return $this->format($order);
    }

    private function format(ProductOrder $order): string
    {
        $lines = [
            "📦 Pesanan {$order->order_number}",
            'Status: '.Str::title(str_replace('_', ' ', (string) $order->status)),
        ];

        if ($order->payment_status) {
            $lines[] = 'Bayaran: '.Str::title((string) $order->payment_status);
        }

        if ($order->total_amount) {
            $lines[] = 'Jumlah: RM'.number_format((float) $order->total_amount, 2);
        }

        if ($order->tracking_id) {
            $lines[] = "No. tracking: {$order->tracking_id}";
            $lines[] = 'Jejak: https://www.tracking.my/instant/'.rawurlencode($order->tracking_id);
        }

        return implode("\n", $lines);
    }
}
