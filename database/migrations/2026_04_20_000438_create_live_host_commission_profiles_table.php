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
        Schema::create('live_host_commission_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('base_salary_myr', 10, 2)->default(0);
            $table->decimal('per_live_rate_myr', 10, 2)->default(0);
            $table->foreignId('upline_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('override_rate_l1_percent', 5, 2)->default(0);
            $table->decimal('override_rate_l2_percent', 5, 2)->default(0);
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index('upline_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_host_commission_profiles');
    }
};
