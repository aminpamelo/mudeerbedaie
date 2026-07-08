<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-mentee, per-day comments are now grouped by author: each user keeps one
     * editable comment per host per day (unique on mentee + date + user) instead
     * of a single shared comment that whoever saved last overwrote. Existing
     * comments are migrated into this table (attributed to their original author),
     * then the now-redundant comment columns are dropped from the daily metrics
     * table, which keeps only the per-day sales_override.
     *
     * The steps are written to be safely re-runnable. An earlier version used
     * auto-generated index names that exceeded MySQL's 64-char identifier limit,
     * so the CREATE succeeded but the unique index failed — leaving a partial,
     * unindexed table (which the deployed app may already have written to) and no
     * migration record. Everything below therefore checks state first and never
     * drops data.
     */
    public function up(): void
    {
        if (! Schema::hasTable('live_host_mentee_daily_comments')) {
            Schema::create('live_host_mentee_daily_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mentee_id')->constrained('live_host_mentees')->cascadeOnDelete();
                $table->date('metric_date');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('comment');
                $table->timestamps();
            });
        }

        $this->ensureIndexes();
        $this->backfillFromMetrics();

        if (Schema::hasColumn('live_host_mentee_daily_metrics', 'commented_by')) {
            Schema::table('live_host_mentee_daily_metrics', function (Blueprint $table) {
                $table->dropConstrainedForeignId('commented_by');
            });
        }
        Schema::table('live_host_mentee_daily_metrics', function (Blueprint $table) {
            foreach (['comment', 'commented_at'] as $column) {
                if (Schema::hasColumn('live_host_mentee_daily_metrics', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Add the (short-named) indexes if missing. Explicit names are required —
     * the auto-generated composite unique name exceeds MySQL's 64-char limit.
     */
    private function ensureIndexes(): void
    {
        $hasUnique = Schema::hasIndex('live_host_mentee_daily_comments', 'lhmdc_mentee_date_user_unique');
        $hasIndex = Schema::hasIndex('live_host_mentee_daily_comments', 'lhmdc_mentee_date_idx');

        if ($hasUnique && $hasIndex) {
            return;
        }

        Schema::table('live_host_mentee_daily_comments', function (Blueprint $table) use ($hasUnique, $hasIndex) {
            if (! $hasUnique) {
                $table->unique(['mentee_id', 'metric_date', 'user_id'], 'lhmdc_mentee_date_user_unique');
            }
            if (! $hasIndex) {
                $table->index(['mentee_id', 'metric_date'], 'lhmdc_mentee_date_idx');
            }
        });
    }

    /**
     * Copy every existing daily-metric comment into the new per-author table,
     * preserving author and timestamps. insertOrIgnore keeps this idempotent and
     * avoids colliding with any comments the app already wrote to a partial table.
     */
    private function backfillFromMetrics(): void
    {
        if (! Schema::hasColumn('live_host_mentee_daily_metrics', 'comment')) {
            return;
        }

        DB::table('live_host_mentee_daily_metrics')
            ->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                $insert = [];
                foreach ($rows as $row) {
                    $stamp = $row->commented_at ?? $row->created_at ?? $row->updated_at;
                    $insert[] = [
                        'mentee_id' => $row->mentee_id,
                        'metric_date' => $row->metric_date,
                        'user_id' => $row->commented_by,
                        'comment' => $row->comment,
                        'created_at' => $stamp,
                        'updated_at' => $stamp,
                    ];
                }
                if ($insert !== []) {
                    DB::table('live_host_mentee_daily_comments')->insertOrIgnore($insert);
                }
            });
    }

    public function down(): void
    {
        Schema::table('live_host_mentee_daily_metrics', function (Blueprint $table) {
            if (! Schema::hasColumn('live_host_mentee_daily_metrics', 'comment')) {
                $table->text('comment')->nullable();
            }
            if (! Schema::hasColumn('live_host_mentee_daily_metrics', 'commented_by')) {
                $table->foreignId('commented_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('live_host_mentee_daily_metrics', 'commented_at')) {
                $table->timestamp('commented_at')->nullable();
            }
        });

        // Best-effort restore: fold each host/day's earliest comment back into the
        // single legacy column (later authors' comments cannot be represented).
        DB::table('live_host_mentee_daily_comments')
            ->orderBy('mentee_id')
            ->orderBy('metric_date')
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn ($row) => $row->mentee_id.'|'.$row->metric_date)
            ->each(function ($group): void {
                $first = $group->first();
                DB::table('live_host_mentee_daily_metrics')
                    ->where('mentee_id', $first->mentee_id)
                    ->whereDate('metric_date', $first->metric_date)
                    ->update([
                        'comment' => $first->comment,
                        'commented_by' => $first->user_id,
                        'commented_at' => $first->created_at,
                    ]);
            });

        Schema::dropIfExists('live_host_mentee_daily_comments');
    }
};
