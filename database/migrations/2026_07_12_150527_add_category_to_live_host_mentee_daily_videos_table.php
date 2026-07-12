<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The content category a host picks when logging a daily video (tarik live,
 * engagement, tunjuk buku, lakonan, podcast). Nullable so existing rows survive;
 * new logs require it at the validation layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_host_mentee_daily_videos', function (Blueprint $table) {
            $table->string('category')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('live_host_mentee_daily_videos', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
