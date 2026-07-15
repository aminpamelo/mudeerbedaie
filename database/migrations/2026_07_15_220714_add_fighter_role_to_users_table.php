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
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: backup roles, recreate column with 'fighter' added, restore roles
            $roles = DB::table('users')->select('id', 'role')->get();

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'sales', 'employee', 'livehost_assistant', 'accountant', 'ceo', 'fighter'])
                    ->default('student')
                    ->after('email_verified_at');
            });

            foreach ($roles as $row) {
                DB::table('users')->where('id', $row->id)->update(['role' => $row->role]);
            }

            return;
        }

        // Skip if already has the 'fighter' role value (MySQL/MariaDB only)
        $columns = DB::select("SHOW COLUMNS FROM users WHERE Field = 'role' AND Type LIKE '%fighter%'");
        if (! empty($columns)) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'sales', 'employee', 'livehost_assistant', 'accountant', 'ceo', 'fighter'])
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
            $roles = DB::table('users')->select('id', 'role')->get();

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'sales', 'employee', 'livehost_assistant', 'accountant', 'ceo'])
                    ->default('student')
                    ->after('email_verified_at');
            });

            foreach ($roles as $row) {
                $role = $row->role === 'fighter' ? 'student' : $row->role;
                DB::table('users')->where('id', $row->id)->update(['role' => $role]);
            }

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'sales', 'employee', 'livehost_assistant', 'accountant', 'ceo'])
                ->default('student')
                ->change();
        });
    }
};
