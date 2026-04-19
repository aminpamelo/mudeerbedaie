<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Note: the "at-most-one active rate per (user x platform)" invariant is
     * enforced at the application layer (see the platform commission rate
     * controller / service) because partial unique indexes are not portable
     * between MySQL and SQLite. The composite unique
     * (user_id, platform_id, effective_from) only prevents exact-timestamp
     * duplicates — it does not prevent two is_active=true rows coexisting for
     * the same (user, platform) pair.
     */
    public function up(): void
    {
        Schema::create('live_host_platform_commission_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->restrictOnDelete();
            $table->decimal('commission_rate_percent', 5, 2)->default(0);
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'platform_id', 'effective_from'], 'uniq_host_platform_rate_effective');
            $table->index(['user_id', 'platform_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_host_platform_commission_rates');
    }
};
