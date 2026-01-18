<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we need to use a different approach
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support modifying enums, so we'll drop and recreate
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin'])
                    ->default('student')
                    ->after('email_verified_at');
            });
        } else {
            // MySQL/MariaDB
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin') NOT NULL DEFAULT 'student'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost'])
                    ->default('student')
                    ->after('email_verified_at');
            });
        } else {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'teacher', 'student', 'live_host', 'admin_livehost') NOT NULL DEFAULT 'student'");
        }
    }
};
