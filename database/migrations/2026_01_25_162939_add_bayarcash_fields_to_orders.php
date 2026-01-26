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
        // Add Bayarcash fields to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->string('bayarcash_transaction_id')->nullable()->after('stripe_payment_intent_id');
            $table->string('bayarcash_payment_channel')->nullable()->after('bayarcash_transaction_id');
            $table->json('bayarcash_response')->nullable()->after('bayarcash_payment_channel');
        });

        // Add Bayarcash fields to product_orders table
        Schema::table('product_orders', function (Blueprint $table) {
            $table->string('bayarcash_transaction_id')->nullable()->after('payment_method');
            $table->string('bayarcash_payment_channel')->nullable()->after('bayarcash_transaction_id');
            $table->json('bayarcash_response')->nullable()->after('bayarcash_payment_channel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'bayarcash_transaction_id',
                'bayarcash_payment_channel',
                'bayarcash_response',
            ]);
        });

        Schema::table('product_orders', function (Blueprint $table) {
            $table->dropColumn([
                'bayarcash_transaction_id',
                'bayarcash_payment_channel',
                'bayarcash_response',
            ]);
        });
    }
};
