<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A per-creator-account slot override that replaces the account's normal slots
 * for a date range (effective_until null = open-ended). The override's actual
 * slot times live on live_time_slots rows tagged with override_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_time_slot_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_account_id')->constrained('live_accounts')->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['live_account_id', 'effective_from'], 'lts_override_account_from_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_time_slot_overrides');
    }
};
