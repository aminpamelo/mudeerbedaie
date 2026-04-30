<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Delete duplicate rows, keeping the latest id per (account, product).
        $keepIds = DB::table('tiktok_product_performance')
            ->selectRaw('MAX(id) as id')
            ->groupBy('platform_account_id', 'tiktok_product_id')
            ->pluck('id');

        if ($keepIds->isNotEmpty()) {
            DB::table('tiktok_product_performance')
                ->whereNotIn('id', $keepIds->all())
                ->delete();
        }

        // Step 2: Replace the non-unique compound index with a unique one
        // so future upserts have a DB-level guarantee against duplicates.
        Schema::table('tiktok_product_performance', function (Blueprint $table) {
            $table->dropIndex('tpp_account_product_idx');
            $table->unique(
                ['platform_account_id', 'tiktok_product_id'],
                'tpp_account_product_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('tiktok_product_performance', function (Blueprint $table) {
            $table->dropUnique('tpp_account_product_unique');
            $table->index(
                ['platform_account_id', 'tiktok_product_id'],
                'tpp_account_product_idx'
            );
        });
    }
};
