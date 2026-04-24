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
        // For SQLite (used in tests), enum changes are not supported the same way.
        // We use string column type on SQLite, so just ensure the column exists.
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: backup roles, recreate column with 'livehost_assistant' added, restore roles
            $roles = \Illuminate\Support\Facades\DB::table('users')->select('id', 'role')->get();

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'sales', 'employee', 'livehost_assistant'])
                    ->default('student')
                    ->after('email_verified_at');
            });

            foreach ($roles as $row) {
                \Illuminate\Support\Facades\DB::table('users')->where('id', $row->id)->update(['role' => $row->role]);
            }

            return;
        }

        // Skip if already has the 'livehost_assistant' role value (MySQL/MariaDB only)
        $columns = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM users WHERE Field = 'role' AND Type LIKE '%livehost_assistant%'");
        if (! empty($columns)) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'sales', 'employee', 'livehost_assistant'])
                ->default('student')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $roles = \Illuminate\Support\Facades\DB::table('users')->select('id', 'role')->get();

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'sales', 'employee'])
                    ->default('student')
                    ->after('email_verified_at');
            });

            foreach ($roles as $row) {
                $role = $row->role === 'livehost_assistant' ? 'student' : $row->role;
                \Illuminate\Support\Facades\DB::table('users')->where('id', $row->id)->update(['role' => $role]);
            }

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'sales', 'employee'])
                ->default('student')
                ->change();
        });
    }
};
