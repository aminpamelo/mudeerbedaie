<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_ad_strategies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('orange');
            $table->string('description', 500)->nullable();
            $table->text('playbook')->nullable();
            $table->json('launch_preset')->nullable(); // objective, daily_budget, ages, countries, advantage_audience, call_to_action
            $table->json('rule_presets')->nullable(); // list of rule attribute sets
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_ad_strategies');
    }
};
