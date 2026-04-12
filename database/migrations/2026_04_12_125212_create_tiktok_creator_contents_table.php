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
        Schema::create('tiktok_creator_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tiktok_creator_id')->constrained('tiktok_creators')->cascadeOnDelete();
            $table->foreignId('content_id')->nullable()->constrained('contents')->nullOnDelete();
            $table->foreignId('platform_account_id')->constrained('platform_accounts')->cascadeOnDelete();
            $table->string('creator_video_id')->nullable();
            $table->string('tiktok_product_id')->nullable();
            $table->bigInteger('views')->default(0);
            $table->bigInteger('likes')->default(0);
            $table->bigInteger('comments')->default(0);
            $table->bigInteger('shares')->default(0);
            $table->decimal('gmv', 15, 2)->default(0);
            $table->bigInteger('orders')->default(0);
            $table->json('raw_response')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->index(['tiktok_creator_id', 'content_id'], 'tcc_creator_content_idx');
            $table->index('creator_video_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_creator_contents');
    }
};
