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
            // Get all existing users with their roles
            $users = DB::table('users')->select('id', 'role')->get();

            // SQLite doesn't support modifying enums, so we'll drop and recreate
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', [
                    'admin',
                    'teacher',
                    'student',
                    'live_host',
                    'admin_livehost',
                    'class_admin',
                    'pic_department',
                    'member_department',
                ])
                    ->default('student')
                    ->after('email_verified_at');
            });

            // Restore user roles
            foreach ($users as $user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['role' => $user->role]);
            }
        } else {
            // MySQL/MariaDB
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'pic_department', 'member_department') NOT NULL DEFAULT 'student'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // Get all existing users with their roles
            $users = DB::table('users')->select('id', 'role')->get();

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin'])
                    ->default('student')
                    ->after('email_verified_at');
            });

            // Restore user roles (convert department roles back to student)
            foreach ($users as $user) {
                $role = in_array($user->role, ['pic_department', 'member_department']) ? 'student' : $user->role;
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['role' => $role]);
            }
        } else {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin') NOT NULL DEFAULT 'student'");
        }
    }
};
