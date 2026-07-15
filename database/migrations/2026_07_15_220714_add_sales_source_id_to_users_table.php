<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Links a Fighter user to their dedicated sales-source "segment" so every
     * order coming from that fighter's funnels can be tagged deterministically.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('sales_source_id')->nullable()->after('role')->constrained('sales_sources')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['sales_source_id']);
            $table->dropColumn('sales_source_id');
        });
    }
};
