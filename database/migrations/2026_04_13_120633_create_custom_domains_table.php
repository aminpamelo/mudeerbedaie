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
        Schema::create('custom_domains', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->enum('type', ['custom', 'subdomain'])->default('custom');
            $table->string('cloudflare_hostname_id')->nullable();
            $table->enum('verification_status', ['pending', 'active', 'failed', 'deleting'])->default('pending');
            $table->enum('ssl_status', ['pending', 'active', 'failed'])->default('pending');
            $table->json('verification_errors')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('ssl_active_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('funnel_id');
            $table->index('user_id');
            $table->index('verification_status');
            $table->index('domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_domains');
    }
};
