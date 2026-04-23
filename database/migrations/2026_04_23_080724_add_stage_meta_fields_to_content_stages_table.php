<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_stages', function (Blueprint $table) {
            if (! Schema::hasColumn('content_stages', 'video_concept')) {
                $table->text('video_concept')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('content_stages', 'stage_description')) {
                $table->text('stage_description')->nullable()->after('video_concept');
            }
            if (! Schema::hasColumn('content_stages', 'account_name')) {
                $table->string('account_name', 255)->nullable()->after('stage_description');
            }
            if (! Schema::hasColumn('content_stages', 'posting_time')) {
                $table->timestamp('posting_time')->nullable()->after('account_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('content_stages', function (Blueprint $table) {
            $columns = ['video_concept', 'stage_description', 'account_name', 'posting_time'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('content_stages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
