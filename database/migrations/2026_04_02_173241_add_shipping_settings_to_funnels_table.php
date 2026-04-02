<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('funnels', 'shipping_settings')) {
            return;
        }

        Schema::table('funnels', function (Blueprint $table) {
            $table->json('shipping_settings')->nullable()->after('disable_shipping');
        });
    }

    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->dropColumn('shipping_settings');
        });
    }
};
