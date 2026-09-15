<?php

namespace App\Services\Cekbot;

use App\Models\CekbotConversation;
use App\Models\CekbotFlow;
use App\Models\CekbotFlowEnrollment;
use App\Models\Product;
use App\Models\ProductOrder;

/**
 * Creates a {@see ProductOrder} from a completed Cekbot flow — shared by the
 * deterministic step engine and the AI sales agent so order shape stays
 * identical regardless of how the details were collected.
 */
class CekbotFlowOrderCreator
{
    /**
     * @param  array{
     *     label?: string,
     *     price?: float|int|string|null,
     *     currency?: string|null,
     *     product_id?: int|null,
     *     payment_method?: string|null,
     *     name?: string|null,
     *     phone?: string|null,
     *     address?: string|null,
     *     receipt?: string|null,
     * }  $details
     */
    public function create(CekbotFlow $flow, CekbotConversation $conversation, array $details): ProductOrder
    {
        $label = trim((string) ($details['label'] ?? 'Pakej')) ?: 'Pakej';
        $price = (float) ($details['price'] ?? 0);
        $currency = (string) ($details['currency'] ?? 'RM') ?: 'RM';
        $name = trim((string) ($details['name'] ?? '')) ?: ($conversation->name ?: $conversation->phoneNumber());
        $phone = trim((string) ($details['phone'] ?? '')) ?: $conversation->phoneNumber();
        $method = $details['payment_method'] ?? null;
        $address = trim((string) ($details['address'] ?? '')) ?: null;
        $catalogProductId = $details['product_id'] ?? null;

        $order = ProductOrder::create([
            'order_number' => ProductOrder::generateOrderNumber(),
            'status' => 'pending',
            'payment_status' => 'pending',
            'source' => 'whatsapp_bot',
            'source_reference' => 'cekbot:'.$conversation->session->session_name,
            'sales_source_id' => $flow->sales_source_id,
            'currency' => $currency,
            'subtotal' => $price,
            'total_amount' => $price,
            'customer_name' => $name,
            'customer_phone' => $phone,
            'payment_method' => $method,
            'order_date' => now(),
            'shipping_address' => $address ? [
                'name' => $name,
                'phone' => $phone,
                'full_address' => $address,
            ] : null,
            'metadata' => [
                'cekbot_flow_id' => $flow->id,
                'cekbot_flow_name' => $flow->name,
                'cekbot_conversation_id' => $conversation->id,
                'cekbot_session' => $conversation->session->session_name,
                'chat_id' => $conversation->chat_id,
                'driver' => $details['driver'] ?? 'flow',
                'receipt' => $details['receipt'] ?? null,
            ],
        ]);

        $order->items()->create([
            'product_id' => $catalogProductId,
            'itemable_type' => $catalogProductId ? Product::class : null,
            'itemable_id' => $catalogProductId,
            'product_name' => $label,
            'quantity_ordered' => 1,
            'unit_price' => $price,
            'total_price' => $price,
            'unit_cost' => 0,
        ]);

        // Free-text COD address lives in shipping_address JSON (set above) — the
        // same as funnel/POS/lead orders — so effectiveAddress() can normalise
        // it for courier booking without forcing a half-empty address row.

        $order->addSystemNote('Order created from WhatsApp Cekbot flow: '.$flow->name, [
            'cekbot_flow_id' => $flow->id,
            'payment_method' => $method,
            'driver' => $details['driver'] ?? 'flow',
        ]);

        return $order;
    }

    /**
     * Mark an enrollment completed and attach the created order, and file the
     * conversation under the "Deal" pipeline stage.
     */
    public function completeEnrollment(CekbotFlowEnrollment $enrollment, ProductOrder $order): void
    {
        $enrollment->update([
            'status' => CekbotFlowEnrollment::STATUS_COMPLETED,
            'current_step' => CekbotFlowEnrollment::STEP_DONE,
            'product_order_id' => $order->id,
            'completed_at' => now(),
        ]);
    }
}
