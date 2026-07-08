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
     */
    public function up(): void
    {
        // Self-heal a prior failed run: an earlier version used index names that
        // exceeded MySQL's 64-char identifier limit, so the table was created but
        // the unique index failed, leaving an empty partial table and no migration
        // record. Dropping it here is safe — the table is only ever populated by
        // the backfill below, which runs after a successful creation.
        Schema::dropIfExists('live_host_mentee_daily_comments');

        Schema::create('live_host_mentee_daily_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentee_id')->constrained('live_host_mentees')->cascadeOnDelete();
            $table->date('metric_date');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment');
            $table->timestamps();

            // Explicit short names — the auto-generated ones exceed MySQL's 64-char limit.
            $table->unique(['mentee_id', 'metric_date', 'user_id'], 'lhmdc_mentee_date_user_unique');
            $table->index(['mentee_id', 'metric_date'], 'lhmdc_mentee_date_idx');
        });

        $this->backfillFromMetrics();

        Schema::table('live_host_mentee_daily_metrics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commented_by');
        });
        Schema::table('live_host_mentee_daily_metrics', function (Blueprint $table) {
            $table->dropColumn(['comment', 'commented_at']);
        });
    }

    /**
     * Copy every existing daily-metric comment into the new per-author table,
     * preserving author and timestamps. There is at most one comment per
     * (mentee, date) today, so the unique key can never collide here.
     */
    private function backfillFromMetrics(): void
    {
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
                    DB::table('live_host_mentee_daily_comments')->insert($insert);
                }
            });
    }

    public function down(): void
    {
        Schema::table('live_host_mentee_daily_metrics', function (Blueprint $table) {
            $table->text('comment')->nullable();
            $table->foreignId('commented_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('commented_at')->nullable();
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
