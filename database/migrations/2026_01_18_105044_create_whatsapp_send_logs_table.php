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
        Schema::create('whatsapp_send_logs', function (Blueprint $table) {
            $table->id();
            $table->date('send_date');
            $table->integer('message_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            $table->string('device_token')->nullable();
            $table->timestamps();

            $table->unique(['send_date', 'device_token']);
            $table->index('send_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_send_logs');
    }
};
