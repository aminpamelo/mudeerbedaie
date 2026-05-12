<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the (user_id, platform_account_id) unique index so a single host
     * can have multiple TikTok creator identities under the same platform
     * account. The pivot's surrogate `id` PK still keeps each row addressable
     * and FKs from live_sessions / live_time_slots continue to work.
     */
    public function up(): void
    {
        try {
            Schema::table('live_host_platform_account', function (Blueprint $table) {
                $table->dropUnique('live_host_platform_account_user_platform_unique');
            });
        } catch (\Throwable $e) {
            // Index already absent (e.g. partially migrated env); proceed.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('live_host_platform_account', function (Blueprint $table) {
                $table->unique(
                    ['user_id', 'platform_account_id'],
                    'live_host_platform_account_user_platform_unique'
                );
            });
        } catch (\Throwable $e) {
            // Re-adding the constraint would fail if duplicates now exist
            // (which is the whole point of the up() migration). Leaving the
            // table without the unique index is the only sensible rollback.
        }
    }
};
