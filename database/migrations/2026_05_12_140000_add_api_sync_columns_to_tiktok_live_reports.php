<?php

declare(strict_types=1);

use App\Models\TiktokLiveReport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tiktok_live_reports', 'tiktok_live_id')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            Schema::table('tiktok_live_reports', function (Blueprint $table) {
                $table->string('tiktok_live_id')->nullable()->after('id');
                $table->foreignId('platform_account_id')->nullable()->after('tiktok_live_id')
                    ->constrained('platform_accounts')->nullOnDelete();
                $table->string('source', 16)->default('csv')->after('platform_account_id');
                $table->timestamp('synced_at')->nullable()->after('source');
            });
        } else {
            Schema::table('tiktok_live_reports', function (Blueprint $table) {
                $table->string('tiktok_live_id')->nullable();
                $table->foreignId('platform_account_id')->nullable()
                    ->constrained('platform_accounts')->nullOnDelete();
                $table->string('source', 16)->default('csv');
                $table->timestamp('synced_at')->nullable();
            });
        }

        Schema::table('tiktok_live_reports', function (Blueprint $table) {
            $table->unique(['platform_account_id', 'tiktok_live_id'], 'tlr_account_live_unique');
        });

        TiktokLiveReport::query()
            ->whereNotNull('matched_live_session_id')
            ->whereNull('platform_account_id')
            ->with('matchedLiveSession:id,platform_account_id')
            ->chunkById(500, function ($reports) {
                foreach ($reports as $report) {
                    $accountId = $report->matchedLiveSession?->platform_account_id;

                    if ($accountId !== null) {
                        DB::table('tiktok_live_reports')
                            ->where('id', $report->id)
                            ->update(['platform_account_id' => $accountId]);
                    }
                }
            });
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
