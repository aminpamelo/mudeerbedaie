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
        Schema::create('external_systems', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('base_url');
            $table->string('provision_path')->default('/api/v1/provision');
            $table->string('auth_type')->default('bearer'); // bearer | hmac | both
            $table->text('api_key')->nullable(); // encrypted at rest
            $table->text('signing_secret')->nullable(); // encrypted at rest
            $table->unsignedSmallInteger('timeout')->default(30);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_systems');
    }
};
