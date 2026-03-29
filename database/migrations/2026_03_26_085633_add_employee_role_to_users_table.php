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
        // Skip if already has the 'employee' role value
        $currentColumn = Schema::getColumnType('users', 'role');
        if ($currentColumn && \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM users WHERE Field = 'role' AND Type LIKE '%employee%'")) {
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
