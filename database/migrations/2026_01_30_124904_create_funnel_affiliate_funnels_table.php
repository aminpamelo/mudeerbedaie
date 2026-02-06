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
        Schema::create('funnel_affiliate_funnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('funnel_affiliates')->cascadeOnDelete();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['affiliate_id', 'funnel_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_affiliate_funnels');
    }
};
