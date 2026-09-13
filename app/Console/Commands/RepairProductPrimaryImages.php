<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Console\Command;

class RepairProductPrimaryImages extends Command
{
    /**
     * @var string
     */
    protected $signature = 'products:repair-primary-images';

    /**
     * @var string
     */
    protected $description = 'Flag the first image as primary for products that have images but no primary one (repairs the old edit-save bug that cleared the flag)';

    public function handle(): int
    {
        $repaired = 0;

        Product::query()
            ->whereHas('images')
            ->whereDoesntHave('images', fn ($query) => $query->where('is_primary', true))
            ->chunkById(200, function ($products) use (&$repaired): void {
                foreach ($products as $product) {
                    $first = $product->media()->images()->ordered()->first();

                    if ($first) {
                        ProductMedia::whereKey($first->id)->update(['is_primary' => true]);
                        $repaired++;
                    }
                }
            });

        $this->info("Repaired {$repaired} product(s) with a missing primary image.");

        return self::SUCCESS;
    }
}
