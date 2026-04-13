<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('contents', 'platform_account_id')) {
            return;
        }

        Schema::table('contents', function (Blueprint $table) {
            $table->foreignId('platform_account_id')
                ->nullable()
                ->after('video_url')
                ->constrained('platform_accounts')
                ->nullOnDelete();
            $table->index(['platform_account_id', 'tiktok_post_id'], 'contents_platform_post_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropIndex('contents_platform_post_idx');
            $table->dropConstrainedForeignId('platform_account_id');
        });
    }
};
