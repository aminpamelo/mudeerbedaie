<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_ad_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_ad_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('level', 20); // campaign | adset | ad
            $table->string('metric', 30); // spend | fb_roas | funnel_roas | cpc | ctr | cpa | purchases
            $table->string('operator', 5); // lt | gt
            $table->decimal('threshold', 12, 4);
            $table->decimal('min_spend', 12, 2)->default(0);
            $table->unsignedTinyInteger('lookback_days')->default(3);
            $table->string('action', 30); // pause | activate | increase_budget | decrease_budget
            $table->decimal('action_value', 8, 2)->nullable(); // % for budget changes
            $table->decimal('max_daily_budget', 12, 2)->nullable();
            $table->decimal('min_daily_budget', 12, 2)->nullable();
            $table->unsignedSmallInteger('cooldown_hours')->default(24);
            $table->boolean('is_active')->default(true);
            $table->boolean('dry_run')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->text('last_run_summary')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'facebook_ad_account_id'], 'fb_ad_rules_active_account_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_ad_rules');
    }
};
