<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds missing enum values needed for TikTok Shop order sync:
     * - status: adds 'completed' (TikTok's COMPLETED status)
     * - order_type: adds 'product' (for TikTok Shop product orders)
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // Update status enum to include 'completed'
            DB::statement("ALTER TABLE product_orders MODIFY COLUMN status ENUM(
                'draft', 'pending', 'confirmed', 'processing',
                'partially_shipped', 'shipped', 'delivered',
                'cancelled', 'refunded', 'returned', 'completed'
            ) DEFAULT 'pending'");

            // Update order_type enum to include 'product'
            DB::statement("ALTER TABLE product_orders MODIFY COLUMN order_type ENUM(
                'retail', 'wholesale', 'b2b', 'package', 'product'
            ) DEFAULT 'retail'");
        } else {
            // For SQLite, convert to string (enum constraint not enforced anyway)
            Schema::table('product_orders', function (Blueprint $table) {
                $table->string('status')->default('pending')->change();
                $table->string('order_type')->default('retail')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // First update any new values to safe defaults
            DB::table('product_orders')->where('status', 'completed')->update(['status' => 'delivered']);
            DB::table('product_orders')->where('order_type', 'product')->update(['order_type' => 'retail']);

            // Revert status enum
            DB::statement("ALTER TABLE product_orders MODIFY COLUMN status ENUM(
                'draft', 'pending', 'confirmed', 'processing',
                'partially_shipped', 'shipped', 'delivered',
                'cancelled', 'refunded', 'returned'
            ) DEFAULT 'pending'");

            // Revert order_type enum
            DB::statement("ALTER TABLE product_orders MODIFY COLUMN order_type ENUM(
                'retail', 'wholesale', 'b2b', 'package'
            ) DEFAULT 'retail'");
        }
        // For SQLite, no action needed as string type remains compatible
    }
};
