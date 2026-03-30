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
            // SQLite doesn't support ENUM or column type changes in the same way.
            // The column already exists as a string type in SQLite, so skip.
            return;
        }

        // Skip if already has the 'employee' role value (MySQL/MariaDB only)
        $columns = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM users WHERE Field = 'role' AND Type LIKE '%employee%'");
        if (! empty($columns)) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'sales', 'employee'])
                ->default('student')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'teacher', 'student', 'live_host', 'admin_livehost', 'class_admin', 'sales'])
                ->default('student')
                ->change();
        });
    }
};
