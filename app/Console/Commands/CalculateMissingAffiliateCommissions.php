<?php

namespace App\Console\Commands;

use App\Models\FunnelOrder;
use App\Services\Funnel\AffiliateCommissionService;
use Illuminate\Console\Command;

class CalculateMissingAffiliateCommissions extends Command
{
    protected $signature = 'affiliate:calculate-missing';

    protected $description = 'Calculate commissions for existing orders that were placed through affiliate links but have no commission record';

    public function handle(): int
    {
        $service = app(AffiliateCommissionService::class);

        // Find funnel orders that:
        // 1. Have a session with an affiliate_id
        // 2. Don't already have a commission record
        $orders = FunnelOrder::query()
            ->whereHas('session', function ($q) {
                $q->whereNotNull('affiliate_id');
            })
            ->whereDoesntHave('commission')
            ->with(['session', 'funnel', 'step'])
            ->get();

        $this->info("Found {$orders->count()} orders with affiliate sessions but no commission record.");

        $created = 0;
        $skipped = 0;

        foreach ($orders as $funnelOrder) {
            $commission = $service->calculateCommission($funnelOrder, $funnelOrder->session);

            if ($commission) {
                $affiliateName = $funnelOrder->session->affiliate->name ?? 'N/A';
                $this->line("  Created commission RM {$commission->commission_amount} for order #{$funnelOrder->id} (affiliate: {$affiliateName})");
                $created++;
            } else {
                $this->line("  Skipped order #{$funnelOrder->id} (no matching rules, zero revenue, or affiliate program disabled)");
                $skipped++;
            }
        }

        $this->info("Done. Created: {$created}, Skipped: {$skipped}");

        return self::SUCCESS;
    }
}
