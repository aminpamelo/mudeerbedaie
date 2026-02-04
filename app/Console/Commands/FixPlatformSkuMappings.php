<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PendingPlatformProduct;
use App\Models\PlatformSkuMapping;
use Illuminate\Console\Command;

class FixPlatformSkuMappings extends Command
{
    protected $signature = 'tiktok:fix-sku-mappings
        {--account= : Platform account ID to fix (all accounts if omitted)}
        {--dry-run : Show what would be fixed without making changes}
        {--reset-orphans : Reset linked products with no suggestion data back to pending}';

    protected $description = 'Rebuild PlatformSkuMappings using correct TikTok SKU IDs from pending product raw_data';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $accountId = $this->option('account');
        $resetOrphans = $this->option('reset-orphans');

        $this->info($dryRun ? '[DRY RUN] Scanning for broken SKU mappings...' : 'Fixing broken SKU mappings...');

        $query = PendingPlatformProduct::whereIn('status', ['linked', 'created'])
            ->whereNotNull('raw_data')
            ->when($accountId, fn ($q) => $q->where('platform_account_id', $accountId));

        $pendingProducts = $query->get();

        if ($pendingProducts->isEmpty()) {
            $this->info('No linked/created pending products found.');

            return self::SUCCESS;
        }

        $this->info("Found {$pendingProducts->count()} linked/created pending products to check.");

        $fixed = 0;
        $skipped = 0;
        $orphans = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($pendingProducts->count());
        $bar->start();

        foreach ($pendingProducts as $pending) {
            $bar->advance();

            $correctSkuId = $pending->getTikTokSkuId();

            if (empty($correctSkuId) || $correctSkuId === $pending->platform_product_id) {
                $skipped++;

                continue;
            }

            // Check if a mapping already exists with the correct SKU
            $existingCorrect = PlatformSkuMapping::where('platform_id', $pending->platform_id)
                ->where('platform_account_id', $pending->platform_account_id)
                ->where('platform_sku', $correctSkuId)
                ->first();

            if ($existingCorrect) {
                $skipped++;

                continue;
            }

            // Determine what to link to
            $productId = $pending->suggested_product_id;
            $packageId = $pending->suggested_package_id;

            if (! $productId && ! $packageId) {
                // Orphaned: marked as linked but no suggestion data (mapping was overwritten)
                $orphans++;

                if ($resetOrphans && ! $dryRun) {
                    $pending->update([
                        'status' => 'pending',
                        'reviewed_at' => null,
                        'reviewed_by' => null,
                    ]);
                } elseif ($dryRun) {
                    $this->newLine();
                    $this->line("  <fg=yellow>Orphan (no link target):</> <info>{$pending->name}</info> — would reset to pending");
                }

                continue;
            }

            if ($dryRun) {
                $this->newLine();
                $this->line("  Would create mapping: SKU <comment>{$correctSkuId}</comment> for <info>{$pending->name}</info>");
                $fixed++;

                continue;
            }

            try {
                PlatformSkuMapping::updateOrCreate(
                    [
                        'platform_id' => $pending->platform_id,
                        'platform_account_id' => $pending->platform_account_id,
                        'platform_sku' => $correctSkuId,
                    ],
                    [
                        'product_id' => $packageId ? null : $productId,
                        'product_variant_id' => $pending->suggested_variant_id,
                        'package_id' => $packageId,
                        'platform_product_name' => $pending->name,
                        'is_active' => true,
                        'mapping_metadata' => [
                            'platform_product_id' => $pending->platform_product_id,
                            'fixed_from_pending' => true,
                            'fixed_at' => now()->toIso8601String(),
                        ],
                        'last_used_at' => now(),
                    ]
                );
                $fixed++;
            } catch (\Throwable $e) {
                $errors++;
                $this->newLine();
                $this->error("  Error fixing {$pending->name}: {$e->getMessage()}");
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Fixed/Created', $fixed],
                ['Skipped (already correct)', $skipped],
                ['Orphans (no link target)', $orphans],
                ['Errors', $errors],
            ]
        );

        if ($orphans > 0 && ! $resetOrphans) {
            $this->warn("{$orphans} orphaned products found with no link target. Run with --reset-orphans to reset them to pending for re-linking.");
        } elseif ($orphans > 0 && $resetOrphans && ! $dryRun) {
            $this->info("{$orphans} orphaned products have been reset to pending status for re-linking.");
        }

        if ($dryRun && ($fixed > 0 || $orphans > 0)) {
            $this->warn('Run without --dry-run to apply these changes.');
        }

        // Clean up the empty-SKU mapping if it exists
        if (! $dryRun) {
            $emptyMappings = PlatformSkuMapping::where('platform_sku', '')->count();
            if ($emptyMappings > 0) {
                PlatformSkuMapping::where('platform_sku', '')->delete();
                $this->info("Cleaned up {$emptyMappings} mapping(s) with empty platform_sku.");
            }
        }

        return self::SUCCESS;
    }
}
