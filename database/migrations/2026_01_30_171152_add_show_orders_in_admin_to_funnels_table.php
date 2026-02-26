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
        if (Schema::hasColumn('funnels', 'show_orders_in_admin')) {
            return;
        }

        Schema::table('funnels', function (Blueprint $table) {
            $table->boolean('show_orders_in_admin')->default(true)->after('affiliate_custom_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->dropColumn('show_orders_in_admin');
        });
    }
};
