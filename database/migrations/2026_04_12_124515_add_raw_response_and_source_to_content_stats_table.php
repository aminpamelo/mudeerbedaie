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
        Schema::table('content_stats', function (Blueprint $table) {
            $table->string('source', 32)->default('manual')->after('shares');
            $table->json('raw_response')->nullable()->after('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_stats', function (Blueprint $table) {
            $table->dropColumn(['raw_response', 'source']);
        });
    }
};
