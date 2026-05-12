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
        Schema::table('tiktok_live_reports', function (Blueprint $table) {
            $table->string('tiktok_live_id')->nullable()->after('id');
            $table->foreignId('platform_account_id')->nullable()->after('tiktok_live_id')
                ->constrained('platform_accounts')->nullOnDelete();
            $table->string('source', 16)->default('csv')->after('platform_account_id');
            $table->timestamp('synced_at')->nullable()->after('source');
        });

        // Plain unique works on both drivers and tolerates multiple NULL pairs
        Schema::table('tiktok_live_reports', function (Blueprint $table) {
            $table->unique(['platform_account_id', 'tiktok_live_id'], 'tlr_account_live_unique');
        });

        // Backfill platform_account_id for already-matched CSV rows
        DB::table('tiktok_live_reports')
            ->whereNotNull('matched_live_session_id')
            ->whereNull('platform_account_id')
            ->update([
                'platform_account_id' => DB::raw(
                    '(SELECT live_sessions.platform_account_id FROM live_sessions '
                    .'WHERE live_sessions.id = tiktok_live_reports.matched_live_session_id)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('tiktok_live_reports', function (Blueprint $table) {
            $table->dropUnique('tlr_account_live_unique');
            $table->dropConstrainedForeignId('platform_account_id');
            $table->dropColumn(['tiktok_live_id', 'source', 'synced_at']);
        });
    }
};
