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
        Schema::table('users', function (Blueprint $table) {
            // Make email nullable first
            $table->string('email')->nullable()->change();
        });

        // Drop the unique constraint on email
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });

        // Add phone number field with unique constraint (nullable initially for existing users)
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->unique()->after('email');
        });

        // Add index on email for faster lookups
        Schema::table('users', function (Blueprint $table) {
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop phone column
            $table->dropColumn('phone');

            // Make email required again and add unique constraint
            $table->string('email')->nullable(false)->change();
            $table->unique('email');
        });
    }
};
