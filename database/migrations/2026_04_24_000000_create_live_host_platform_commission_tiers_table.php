<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_host_platform_commission_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->unsignedTinyInteger('tier_number');
            $table->decimal('min_gmv_myr', 12, 2);
            $table->decimal('max_gmv_myr', 12, 2)->nullable();
            $table->decimal('internal_percent', 5, 2);
            $table->decimal('l1_percent', 5, 2);
            $table->decimal('l2_percent', 5, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['user_id', 'platform_id', 'tier_number', 'effective_from'],
                'lh_tier_unique'
            );
            $table->index(['user_id', 'platform_id', 'is_active'], 'lh_tier_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_host_platform_commission_tiers');
    }
};
