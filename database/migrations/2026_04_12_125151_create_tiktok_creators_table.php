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
        Schema::create('tiktok_creators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_account_id')->constrained('platform_accounts')->cascadeOnDelete();
            $table->string('creator_user_id')->index();
            $table->string('handle')->nullable();
            $table->string('display_name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->bigInteger('follower_count')->default(0);
            $table->decimal('total_gmv', 15, 2)->default(0);
            $table->bigInteger('total_orders')->default(0);
            $table->decimal('total_commission', 15, 2)->default(0);
            $table->json('raw_response')->nullable();
            $table->timestamp('performance_fetched_at')->nullable();
            $table->timestamps();
            $table->unique(['platform_account_id', 'creator_user_id'], 'tc_account_creator_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_creators');
    }
};
