<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets one LiveSession link to MULTIPLE ActualLiveRecords — a live that blipped
 * and reconnected is reported by TikTok as 2+ back-to-back records, and the PIC
 * needs to attribute all segments to the single scheduled session.
 *
 * The pivot carries a GLOBAL unique on actual_live_record_id, reproducing the
 * old unique on live_sessions.matched_actual_live_record_id: one TikTok live can
 * still feed at most one session, so its live-attributed GMV lands in exactly one
 * session's gmv_amount and payroll can never double-count.
 *
 * live_sessions.matched_actual_live_record_id is kept as a denormalized "primary"
 * pointer for backward compatibility (existing readers still resolve to a record);
 * only its UNIQUE constraint is dropped, since the pivot now owns that guarantee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_session_actual_live_record', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actual_live_record_id')->constrained('actual_live_records')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->decimal('live_attributed_gmv_myr', 12, 2)->default(0);
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();

            // Global guard: one ActualLiveRecord can be attributed to at most one session.
            $table->unique('actual_live_record_id', 'lsalr_actual_unique');
            $table->index('live_session_id', 'lsalr_session_idx');
        });

        // Backfill one primary pivot row per already-linked session.
        DB::table('live_sessions')
            ->whereNotNull('matched_actual_live_record_id')
            ->select('id', 'matched_actual_live_record_id', 'gmv_amount', 'verified_by', 'verified_at')
            ->orderBy('id')
            ->chunk(500, function ($rows) {
                $now = now();
                $insert = [];
                foreach ($rows as $r) {
                    $insert[] = [
                        'live_session_id' => $r->id,
                        'actual_live_record_id' => $r->matched_actual_live_record_id,
                        'is_primary' => true,
                        'live_attributed_gmv_myr' => $r->gmv_amount ?? 0,
                        'linked_by' => $r->verified_by,
                        'linked_at' => $r->verified_at,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($insert !== []) {
                    DB::table('live_session_actual_live_record')->insert($insert);
                }
            });

        // Drop the UNIQUE on the primary column but KEEP the column + FK — the
        // pivot now owns the "one record, one session" guarantee.
        if (DB::getDriverName() === 'mysql') {
            // The FK needs a backing index; add a plain one before dropping the unique.
            DB::statement('CREATE INDEX ls_malr_idx ON live_sessions (matched_actual_live_record_id)');
            DB::statement('ALTER TABLE live_sessions DROP INDEX live_sessions_matched_actual_live_record_id_unique');
        } else {
            Schema::table('live_sessions', function (Blueprint $table) {
                $table->dropUnique('live_sessions_matched_actual_live_record_id_unique');
            });
        }
    }

    public function down(): void
    {
        // Restore the UNIQUE (safe: each session still has a distinct primary record).
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE live_sessions ADD UNIQUE live_sessions_matched_actual_live_record_id_unique (matched_actual_live_record_id)');
            DB::statement('DROP INDEX ls_malr_idx ON live_sessions');
        } else {
            Schema::table('live_sessions', function (Blueprint $table) {
                $table->unique('matched_actual_live_record_id', 'live_sessions_matched_actual_live_record_id_unique');
            });
        }

        Schema::dropIfExists('live_session_actual_live_record');
    }
};
