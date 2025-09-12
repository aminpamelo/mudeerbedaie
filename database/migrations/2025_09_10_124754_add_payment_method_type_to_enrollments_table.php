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
        Schema::table('enrollments', function (Blueprint $table) {
            $table->enum('payment_method_type', ['automatic', 'manual'])
                ->default('automatic')
                ->after('subscription_status')
                ->comment('Type of payment method: automatic (card) or manual (bank transfer, cash, etc.)');

            $table->boolean('manual_payment_required')
                ->default(false)
                ->after('payment_method_type')
                ->comment('Whether enrollment requires manual payment before activation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['payment_method_type', 'manual_payment_required']);
        });
    }
};
