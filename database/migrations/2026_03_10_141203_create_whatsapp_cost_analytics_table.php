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
        Schema::create('whatsapp_cost_analytics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('country_code', 5)->default('MY');
            $table->string('pricing_category', 30);
            $table->integer('message_volume')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->decimal('cost_myr', 10, 4)->default(0);
            $table->string('granularity', 10)->default('DAILY');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['date', 'country_code', 'pricing_category'], 'wca_unique_daily');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_cost_analytics');
    }
};
