<?php

namespace App\Services\Funnel;

use App\Models\FunnelAffiliateCommission;
use App\Models\FunnelAffiliateCommissionRule;
use App\Models\FunnelOrder;
use App\Models\FunnelSession;

class AffiliateCommissionService
{
    /**
     * Calculate and create commission record for an affiliate-referred order.
     */
    public function calculateCommission(FunnelOrder $funnelOrder, FunnelSession $session): ?FunnelAffiliateCommission
    {
        if (! $session->affiliate_id) {
            return null;
        }

        $funnel = $funnelOrder->funnel;

        if (! $funnel || ! $funnel->isAffiliateEnabled()) {
            return null;
        }

        // Get the product from the order step
        $step = $funnelOrder->step;
        if (! $step) {
            return null;
        }

        $orderAmount = (float) $funnelOrder->funnel_revenue;
        if ($orderAmount <= 0) {
            return null;
        }

        // Find commission rules for products in this step
        $products = $step->products;
        $totalCommission = 0;
        $commissionType = 'fixed';
        $commissionRate = 0;

        foreach ($products as $product) {
            $rule = FunnelAffiliateCommissionRule::where('funnel_id', $funnel->id)
                ->where('funnel_product_id', $product->id)
                ->first();

            if ($rule) {
                $totalCommission += $rule->calculateCommission((float) $product->funnel_price);
                $commissionType = $rule->commission_type;
                $commissionRate = (float) $rule->commission_value;
            }
        }

        if ($totalCommission <= 0) {
            return null;
        }

        return FunnelAffiliateCommission::create([
            'affiliate_id' => $session->affiliate_id,
            'funnel_id' => $funnel->id,
            'funnel_order_id' => $funnelOrder->id,
            'product_order_id' => $funnelOrder->product_order_id,
            'session_id' => $session->id,
            'commission_type' => $commissionType,
            'commission_rate' => $commissionRate,
            'order_amount' => $orderAmount,
            'commission_amount' => $totalCommission,
            'status' => 'pending',
        ]);
    }
}
