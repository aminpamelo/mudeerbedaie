<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_pixels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('platform', 20); // facebook | google
            $table->string('group_name')->nullable();
            $table->json('settings');
            $table->string('last_test_status', 20)->nullable(); // passed | failed
            $table->text('last_test_message')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'platform'], 'funnel_pixels_user_platform_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_pixels');
    }
};
