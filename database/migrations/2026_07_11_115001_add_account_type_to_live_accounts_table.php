<?php

use App\Models\LiveAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Classifies each canonical creator account as a shop's own LINKED TikTok Shop
 * account, an external AFFILIATE creator, or UNKNOWN (not yet reviewed).
 *
 * The TikTok shop_lives/performance API returns lives for every creator who
 * sold the shop's products — linked accounts and affiliates alike — with no
 * distinguishing field. This column is the app-side signal the Live Host
 * timetable filters on so affiliate lives stop polluting the schedule.
 *
 * Backfill: accounts the consolidation already resolved cleanly (a stable
 * numeric Creator ID and not flagged for review) are unambiguously the shop's
 * own creators, so they seed as `linked`. Everything else stays `unknown` for
 * the PIC to classify — no affiliate rows are touched or deleted here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_accounts', function (Blueprint $table) {
            $table->string('account_type', 20)->default('unknown')->after('needs_review');
            $table->index('account_type', 'live_accounts_account_type_idx');
        });

        LiveAccount::query()
            ->whereNotNull('creator_user_id')
            ->where('creator_user_id', '!=', '')
            ->where('needs_review', false)
            ->update(['account_type' => LiveAccount::TYPE_LINKED]);
    }

    public function down(): void
    {
        Schema::table('live_accounts', function (Blueprint $table) {
            $table->dropIndex('live_accounts_account_type_idx');
            $table->dropColumn('account_type');
        });
    }
};
