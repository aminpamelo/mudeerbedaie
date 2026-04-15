<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        // Step 1: Add new JSON columns
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->json('upsell_funnel_ids')->nullable()->after('payout_status');
            $table->json('upsell_pic_user_ids')->nullable()->after('upsell_funnel_ids');
        });

        // Step 2: Migrate existing data from old FK columns to new JSON arrays
        DB::table('class_sessions')
            ->where(function ($q) {
                $q->whereNotNull('upsell_funnel_id')
                    ->orWhereNotNull('upsell_pic_user_id');
            })
            ->orderBy('id')
            ->each(function ($session) {
                $updates = [];

                if ($session->upsell_funnel_id) {
                    $updates['upsell_funnel_ids'] = json_encode([(int) $session->upsell_funnel_id]);
                }

                if ($session->upsell_pic_user_id) {
                    $updates['upsell_pic_user_ids'] = json_encode([(int) $session->upsell_pic_user_id]);
                }

                if (! empty($updates)) {
                    DB::table('class_sessions')->where('id', $session->id)->update($updates);
                }
            });

        // Step 3: Drop old FK columns (MySQL only; SQLite leaves them as unused — dropping
        // columns with embedded FK definitions would require table recreation which breaks
        // child-table FK references from funnel_orders)
        if ($driver === 'mysql') {
            Schema::table('class_sessions', function (Blueprint $table) {
                $table->dropForeign(['upsell_funnel_id']);
                $table->dropForeign(['upsell_pic_user_id']);
                $table->dropColumn(['upsell_funnel_id', 'upsell_pic_user_id']);
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        // Step 1: Re-add old FK columns
        Schema::table('class_sessions', function (Blueprint $table) use ($driver) {
            $table->unsignedBigInteger('upsell_funnel_id')->nullable()->after('payout_status');
            $table->unsignedBigInteger('upsell_pic_user_id')->nullable()->after('upsell_funnel_id');

            if ($driver === 'mysql') {
                $table->foreign('upsell_funnel_id')->references('id')->on('funnels')->nullOnDelete();
                $table->foreign('upsell_pic_user_id')->references('id')->on('users')->nullOnDelete();
            }
        });

        // Step 2: Migrate data back (take first element from JSON array)
        DB::table('class_sessions')
            ->where(function ($q) {
                $q->whereNotNull('upsell_funnel_ids')
                    ->orWhereNotNull('upsell_pic_user_ids');
            })
            ->orderBy('id')
            ->each(function ($session) {
                $updates = [];

                if ($session->upsell_funnel_ids) {
                    $ids = json_decode($session->upsell_funnel_ids, true);
                    $updates['upsell_funnel_id'] = ! empty($ids) ? $ids[0] : null;
                }

                if ($session->upsell_pic_user_ids) {
                    $ids = json_decode($session->upsell_pic_user_ids, true);
                    $updates['upsell_pic_user_id'] = ! empty($ids) ? $ids[0] : null;
                }

                if (! empty($updates)) {
                    DB::table('class_sessions')->where('id', $session->id)->update($updates);
                }
            });

        // Step 3: Drop JSON columns
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropColumn(['upsell_funnel_ids', 'upsell_pic_user_ids']);
        });
    }
};
