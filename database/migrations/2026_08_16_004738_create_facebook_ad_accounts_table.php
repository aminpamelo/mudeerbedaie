<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_ad_connection_id')->constrained()->cascadeOnDelete();
            $table->string('account_id'); // numeric ad account id (without the act_ prefix)
            $table->string('name');
            $table->string('currency', 10)->nullable();
            $table->string('account_status', 30)->nullable();
            $table->timestamps();

            $table->unique(['facebook_ad_connection_id', 'account_id'], 'fb_ad_accounts_connection_account_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_ad_accounts');
    }
};
