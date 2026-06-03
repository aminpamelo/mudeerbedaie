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
        Schema::table('disciplinary_actions', function (Blueprint $table) {
            $table->string('source')->nullable()->after('status');
            $table->string('source_period')->nullable()->after('source');
            $table->index(['source', 'source_period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disciplinary_actions', function (Blueprint $table) {
            $table->dropIndex(['source', 'source_period']);
            $table->dropColumn(['source', 'source_period']);
        });
    }
};
