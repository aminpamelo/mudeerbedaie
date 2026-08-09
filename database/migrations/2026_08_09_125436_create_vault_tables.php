<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('color', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('vault_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('vault_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url')->nullable();
            $table->string('username')->nullable();
            $table->text('password');
            $table->text('notes')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('vault_categories')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('category_id', 'vault_cred_category_index');
            $table->index('created_by', 'vault_cred_created_by_index');
        });

        Schema::create('vault_credential_tag', function (Blueprint $table) {
            $table->foreignId('vault_credential_id')->constrained('vault_credentials')->cascadeOnDelete();
            $table->foreignId('vault_tag_id')->constrained('vault_tags')->cascadeOnDelete();
            $table->primary(['vault_credential_id', 'vault_tag_id'], 'vault_cred_tag_pk');
        });

        Schema::create('vault_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_id')->nullable()->constrained('vault_credentials')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 20);
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['credential_id', 'created_at'], 'vault_audit_cred_time_index');
            $table->index(['user_id', 'created_at'], 'vault_audit_user_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_audit_logs');
        Schema::dropIfExists('vault_credential_tag');
        Schema::dropIfExists('vault_credentials');
        Schema::dropIfExists('vault_tags');
        Schema::dropIfExists('vault_categories');
    }
};
