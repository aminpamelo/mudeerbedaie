<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scope a tiktok_report_imports row to a single TikTok Shop
     * (PlatformAccount). This lets the LiveSessionMatcher / OrderRefundReconciler
     * restrict candidate sessions to the same shop that uploaded the export,
     * so a creator that streams on multiple shops can't cross-contaminate.
     *
     * Nullable for backward compatibility with any imports created before this
     * change; legacy rows keep the original "match across all shops" behavior.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tiktok_report_imports')) {
            return;
        }

        if (Schema::hasColumn('tiktok_report_imports', 'platform_account_id')) {
            return;
        }

        Schema::table('tiktok_report_imports', function (Blueprint $table) {
            $table->foreignId('platform_account_id')
                ->nullable()
                ->after('report_type')
                ->constrained('platform_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tiktok_report_imports')) {
            return;
        }

        if (! Schema::hasColumn('tiktok_report_imports', 'platform_account_id')) {
            return;
        }

        Schema::table('tiktok_report_imports', function (Blueprint $table) {
            // dropConstrainedForeignId works on both MySQL and SQLite drivers.
            $table->dropConstrainedForeignId('platform_account_id');
        });
    }
};
