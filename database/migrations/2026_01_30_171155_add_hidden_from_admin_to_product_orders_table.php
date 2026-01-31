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
        if (Schema::hasColumn('product_orders', 'hidden_from_admin')) {
            return;
        }

        Schema::table('product_orders', function (Blueprint $table) {
            $table->boolean('hidden_from_admin')->default(false)->after('source_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_orders', function (Blueprint $table) {
            $table->dropColumn('hidden_from_admin');
        });
    }
};
