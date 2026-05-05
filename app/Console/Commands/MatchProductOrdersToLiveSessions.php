<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\LiveHost\MatchProductOrderToLiveSession;
use App\Models\ProductOrder;
use Illuminate\Console\Command;

class MatchProductOrdersToLiveSessions extends Command
{
    protected $signature = 'livehost:match-product-orders {--since= : Only match orders created on/after this date (Y-m-d)}';

    protected $description = 'Backfill matched_live_session_id on TikTok Shop product orders';

    public function handle(MatchProductOrderToLiveSession $matcher): int
    {
        $query = ProductOrder::query()
            ->where('source', 'tiktok_shop')
            ->whereNull('matched_live_session_id');

        if ($since = $this->option('since')) {
            $query->where('created_at', '>=', $since);
        }

        $total = (clone $query)->count();
        $matched = 0;

        $this->info("Scanning {$total} unmatched orders…");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(200, function ($orders) use ($matcher, &$matched, $bar) {
            foreach ($orders as $order) {
                if ($matcher->handle($order) !== null) {
                    $matched++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Matched {$matched} of {$total} orders.");

        return self::SUCCESS;
    }
}
