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
        Schema::create('platform_api_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained()->onDelete('cascade');
            $table->foreignId('platform_account_id')->constrained()->onDelete('cascade');
            $table->string('credential_type'); // api_key, oauth_token, app_secret, etc.
            $table->string('name'); // Display name for the credential
            $table->text('encrypted_value'); // Encrypted credential value
            $table->text('encrypted_refresh_token')->nullable(); // For OAuth
            $table->json('metadata')->nullable(); // Additional credential data
            $table->json('scopes')->nullable(); // API scopes/permissions
            $table->timestamp('expires_at')->nullable(); // Token expiration
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_refresh')->default(false); // Auto-refresh OAuth tokens
            $table->timestamps();

            $table->index(['platform_account_id', 'credential_type'], 'platform_creds_account_type_idx');
            $table->index(['is_active', 'expires_at'], 'platform_creds_active_expires_idx');
            $table->index(['last_used_at'], 'platform_creds_last_used_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_api_credentials');
    }
};
