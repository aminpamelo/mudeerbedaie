<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Many-to-many between a live account and the TikTok Shops it sells for.
     * The real data shows one creator account broadcasting/attributing GMV
     * across multiple shops, so the shop is a commerce affiliation, never a
     * 1:1 owner of the account.
     */
    public function up(): void
    {
        Schema::create('live_account_shop', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_account_id')->constrained('live_accounts')->cascadeOnDelete();
            $table->foreignId('platform_account_id')->constrained('platform_accounts')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['live_account_id', 'platform_account_id'], 'live_account_shop_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_account_shop');
    }
};
