<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Note: the "at-most-one active tier row per (user x platform x tier_number)
     * with overlapping effective windows" invariant is enforced at the
     * application layer (see the FormRequest validation for commission tiers)
     * because partial unique indexes are not portable between MySQL and SQLite.
     * The composite unique (user_id, platform_id, tier_number, effective_from)
     * only prevents exact-date duplicates — it does not prevent two
     * is_active=true rows with overlapping effective windows for the same
     * (user, platform, tier_number) tuple.
     */
    public function up(): void
    {
        if (Schema::hasTable('live_host_platform_commission_tiers')) {
            return;
        }

        Schema::create('live_host_platform_commission_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('platforms')->restrictOnDelete();
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_host_platform_commission_tiers');
    }
};
