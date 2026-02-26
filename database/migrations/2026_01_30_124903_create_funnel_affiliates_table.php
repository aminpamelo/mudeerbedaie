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
        Schema::create('funnel_affiliates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('phone', 50)->unique();
            $table->string('email')->nullable();
            $table->string('ref_code', 20)->unique();
            $table->enum('status', ['active', 'inactive', 'banned'])->default('active');
            $table->json('metadata')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index('phone');
            $table->index('ref_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funnel_affiliates');
    }
};
