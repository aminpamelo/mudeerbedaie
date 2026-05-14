<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repair migration: the earlier
     * 2026_05_12_131831_drop_unique_user_platform_index_from_live_host_platform_account
     * wrapped dropUnique in an overly broad try/catch and silently swallowed
     * MySQL errno 1553 — the unique on (user_id, platform_account_id) was
     * the only index supporting the user_id foreign key, so MySQL refuses
     * to drop it. The migration was recorded as Ran on production but the
     * constraint stayed in place, surfacing as
     * UniqueConstraintViolationException for legitimate second-creator
     * inserts under the same (host, platform account) pair.
     *
     * This migration applies the same fix pattern as
     * 2026_04_30_211137_dedupe_and_unique_tiktok_product_performance:
     * add a replacement supporting index for the FK first, then drop the
     * unique. Idempotent on both MySQL and SQLite.
     */
    public function up(): void
    {
        if (! $this->hasIndex('live_host_platform_account', 'live_host_platform_account_user_id_index')) {
            Schema::table('live_host_platform_account', function (Blueprint $table) {
                $table->index('user_id', 'live_host_platform_account_user_id_index');
            });
        }

        if ($this->hasIndex('live_host_platform_account', 'live_host_platform_account_user_platform_unique')) {
            Schema::table('live_host_platform_account', function (Blueprint $table) {
                $table->dropUnique('live_host_platform_account_user_platform_unique');
            });
        }
    }

    public function down(): void
    {
        try {
            if (! $this->hasIndex('live_host_platform_account', 'live_host_platform_account_user_platform_unique')) {
                Schema::table('live_host_platform_account', function (Blueprint $table) {
                    $table->unique(
                        ['user_id', 'platform_account_id'],
                        'live_host_platform_account_user_platform_unique'
                    );
                });
            }

            if ($this->hasIndex('live_host_platform_account', 'live_host_platform_account_user_id_index')) {
                Schema::table('live_host_platform_account', function (Blueprint $table) {
                    $table->dropIndex('live_host_platform_account_user_id_index');
                });
            }
        } catch (\Throwable $e) {
            // Re-adding the unique fails if duplicate (user_id, platform_account_id)
            // rows now exist — which is the whole point of the original drop.
            // Leave the explicit user_id index in place so the FK still has
            // supporting coverage.
        }
    }

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

        $row = DB::selectOne(
            "SELECT COUNT(*) AS c FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ?",
            [$table, $indexName]
        );

        return $row && (int) $row->c > 0;
    }
};
