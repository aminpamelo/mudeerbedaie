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
            $table->timestamp('billing_cycle_anchor')->nullable()->after('subscription_cancel_at');
            $table->timestamp('trial_end_at')->nullable()->after('billing_cycle_anchor');
            $table->string('subscription_timezone', 50)->nullable()->after('trial_end_at');
            $table->enum('proration_behavior', ['create_prorations', 'none', 'always_invoice'])->default('create_prorations')->after('subscription_timezone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['billing_cycle_anchor', 'trial_end_at', 'subscription_timezone', 'proration_behavior']);
        });
    }
};
