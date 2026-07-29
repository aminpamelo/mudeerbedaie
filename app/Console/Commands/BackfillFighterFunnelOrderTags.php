<?php

namespace App\Console\Commands;

use App\Models\Funnel;
use App\Models\FunnelOrder;
use App\Models\ProductOrder;
use App\Services\Fighter\FighterProvisioner;
use Illuminate\Console\Command;

class BackfillFighterFunnelOrderTags extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fighter:backfill-funnel-order-tags {--dry-run : List what would change without writing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill sales_source_id + admin visibility on existing funnel orders whose funnel is owned by a Fighter, so they surface in that fighter\'s /fighter/orders feed.';

    /**
     * Execute the console command.
     */
    public function handle(FighterProvisioner $provisioner): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Untagged funnel orders are the candidates — orders already carrying a
        // segment (or created after the tagging fix) are skipped.
        $query = ProductOrder::query()
            ->where('source', 'funnel')
            ->whereNull('sales_source_id');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No untagged funnel orders found. Nothing to backfill.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Scanning {$total} untagged funnel order(s)…");

        $tagged = 0;
        $skipped = 0;

        $query->orderBy('id')->chunkById(200, function ($orders) use ($provisioner, $dryRun, &$tagged, &$skipped): void {
            foreach ($orders as $order) {
                $funnel = $this->resolveFunnel($order);

                if (! $funnel) {
                    $skipped++;

                    continue;
                }

                $tagging = $provisioner->orderTaggingFor($funnel);

                // Only fighter-owned funnels yield a segment; everyone else is left alone.
                if (empty($tagging['sales_source_id'])) {
                    $skipped++;

                    continue;
                }

                $this->line(sprintf(
                    '  %s%s → segment #%d (%s)',
                    $dryRun ? 'would tag ' : 'tagged ',
                    $order->order_number,
                    $tagging['sales_source_id'],
                    $funnel->slug,
                ));

                if (! $dryRun) {
                    $order->update([
                        'sales_source_id' => $tagging['sales_source_id'],
                        'hidden_from_admin' => $tagging['hidden_from_admin'],
                    ]);
                }

                $tagged++;
            }
        });

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] would tag ' : 'Tagged ')."{$tagged} order(s); skipped {$skipped} (non-fighter or unresolved funnel).");

        return self::SUCCESS;
    }

    /**
     * Resolve the funnel a ProductOrder belongs to: the FunnelOrder pivot is the
     * source of truth; fall back to the order metadata / source reference for
     * legacy rows that predate it.
     */
    private function resolveFunnel(ProductOrder $order): ?Funnel
    {
        $funnel = FunnelOrder::where('product_order_id', $order->id)->first()?->funnel;

        if ($funnel) {
            return $funnel;
        }

        if ($funnelId = ($order->metadata['funnel_id'] ?? null)) {
            $funnel = Funnel::find($funnelId);
        }

        if (! $funnel && $order->source_reference) {
            $funnel = Funnel::where('slug', $order->source_reference)->first();
        }

        return $funnel;
    }
}
