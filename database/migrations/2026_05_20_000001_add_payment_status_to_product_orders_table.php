<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_orders', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('pending')->after('status')->index();
            $table->foreignId('payment_confirmed_by_user_id')->nullable()->after('payment_status')->constrained('users')->nullOnDelete();
            $table->timestamp('payment_confirmed_at')->nullable()->after('payment_confirmed_by_user_id');
            $table->text('payment_rejection_reason')->nullable()->after('payment_confirmed_at');
        });

        // Backfill — paid statuses
        DB::table('product_orders')
            ->where(function ($q) {
                $q->whereIn('status', ['confirmed', 'processing', 'partially_shipped', 'shipped', 'delivered'])
                    ->orWhereNotNull('paid_time');
            })
            ->update(['payment_status' => 'paid']);

        // Failed
        DB::table('product_orders')
            ->whereIn('status', ['cancelled', 'failed'])
            ->update(['payment_status' => 'failed']);

        // Refunded
        DB::table('product_orders')
            ->where('status', 'refunded')
            ->update(['payment_status' => 'refunded']);
    }

    public function down(): void
    {
        Schema::table('product_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_confirmed_by_user_id');
            $table->dropColumn(['payment_status', 'payment_confirmed_at', 'payment_rejection_reason']);
        });
    }
};
