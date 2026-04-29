<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_api_credentials', function (Blueprint $table) {
            $table->foreignId('platform_app_id')
                ->nullable()
                ->after('platform_account_id')
                ->constrained('platform_apps')
                ->nullOnDelete();

            $table->index(
                ['platform_account_id', 'platform_app_id', 'is_active'],
                'platform_creds_account_app_active_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('platform_api_credentials', function (Blueprint $table) {
            $table->dropIndex('platform_creds_account_app_active_idx');
            $table->dropConstrainedForeignId('platform_app_id');
        });
    }
};
