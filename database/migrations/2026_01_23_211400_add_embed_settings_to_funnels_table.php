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
        Schema::table('funnels', function (Blueprint $table) {
            $table->json('embed_settings')->nullable()->after('settings');
            $table->boolean('embed_enabled')->default(false)->after('embed_settings');
            $table->string('embed_key', 32)->nullable()->unique()->after('embed_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->dropColumn(['embed_settings', 'embed_enabled', 'embed_key']);
        });
    }
};
