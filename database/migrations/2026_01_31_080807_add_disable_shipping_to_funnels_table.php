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
        if (Schema::hasColumn('funnels', 'disable_shipping')) {
            return;
        }

        Schema::table('funnels', function (Blueprint $table) {
            $table->boolean('disable_shipping')->default(false)->after('show_orders_in_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->dropColumn('disable_shipping');
        });
    }
};
