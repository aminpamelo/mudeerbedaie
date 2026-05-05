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

        // Step 2: Add the unique index FIRST so MySQL has a supporting index
        // for the platform_account_id foreign key. The original compound index
        // (tpp_account_product_idx) was the FK's only supporting index, so
        // dropping it before adding a replacement throws errno 1553.
        if (! $this->hasIndex('tiktok_product_performance', 'tpp_account_product_unique')) {
            Schema::table('tiktok_product_performance', function (Blueprint $table) {
                $table->unique(
                    ['platform_account_id', 'tiktok_product_id'],
                    'tpp_account_product_unique'
                );
            });
        }

        // Step 3: Now safe to drop the original non-unique index.
        if ($this->hasIndex('tiktok_product_performance', 'tpp_account_product_idx')) {
            Schema::table('tiktok_product_performance', function (Blueprint $table) {
                $table->dropIndex('tpp_account_product_idx');
            });
        }
    }

    public function down(): void
    {
        if (! $this->hasIndex('tiktok_product_performance', 'tpp_account_product_idx')) {
            Schema::table('tiktok_product_performance', function (Blueprint $table) {
                $table->index(
                    ['platform_account_id', 'tiktok_product_id'],
                    'tpp_account_product_idx'
                );
            });
        }

        if ($this->hasIndex('tiktok_product_performance', 'tpp_account_product_unique')) {
            Schema::table('tiktok_product_performance', function (Blueprint $table) {
                $table->dropUnique('tpp_account_product_unique');
            });
        }
    }

    /**
     * Driver-aware "does this index exist on this table" check. SQLite and
     * MySQL expose index metadata differently; this avoids hard-coding
     * either.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $row = DB::selectOne(
                'SELECT COUNT(*) AS c FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
                [$table, $indexName]
            );

            return $row && (int) $row->c > 0;
        }

        // SQLite (and a fallback for other drivers).
        $row = DB::selectOne(
            "SELECT COUNT(*) AS c FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ?",
            [$table, $indexName]
        );

        return $row && (int) $row->c > 0;
    }
};
