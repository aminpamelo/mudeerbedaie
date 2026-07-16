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
        Schema::table('products', function (Blueprint $table) {
            // NULL = official HQ product; set = created by that fighter (editable
            // only by them, sellable only in their own funnels/order form).
            $table->foreignId('created_by_fighter_id')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
            $table->index('created_by_fighter_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_fighter_id');
        });
    }
};
