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
        if (DB::getDriverName() === 'sqlite') {
            // SQLite: backup roles, recreate column, restore roles
            $roles = DB::table('users')->select('id', 'role')->get();

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'sales'])
                    ->default('student')
                    ->after('email_verified_at');
            });

            foreach ($roles as $row) {
                DB::table('users')->where('id', $row->id)->update(['role' => $row->role]);
            }
        } else {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'sales') NOT NULL DEFAULT 'student'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $roles = DB::table('users')->select('id', 'role')->get();

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin'])
                    ->default('student')
                    ->after('email_verified_at');
            });

            foreach ($roles as $row) {
                $role = $row->role === 'sales' ? 'student' : $row->role;
                DB::table('users')->where('id', $row->id)->update(['role' => $role]);
            }
        } else {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin') NOT NULL DEFAULT 'student'");
        }
    }
};
