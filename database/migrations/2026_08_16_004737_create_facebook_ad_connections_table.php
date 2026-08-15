<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_ad_connections', function (Blueprint $table) {
            $table->id();
            // null = shared company connection; set when a fighter links their own BM later
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('business_manager_id');
            $table->text('access_token');
            $table->string('status', 20)->default('pending'); // pending | connected | error
            $table->text('status_message')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_ad_connections');
    }
};
