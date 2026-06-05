<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Many-to-many between a live account and the staff hosts who operate it.
     * A company/brand account can be broadcast by multiple staff on shifts,
     * and a host can operate several accounts, so the pairing is captured on
     * the schedule slot per broadcast while this table records eligibility.
     */
    public function up(): void
    {
        Schema::create('live_account_host', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_account_id')->constrained('live_accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['live_account_id', 'user_id'], 'live_account_host_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_account_host');
    }
};
