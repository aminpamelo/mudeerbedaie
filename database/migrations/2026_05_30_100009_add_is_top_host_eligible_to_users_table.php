<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks a live host as "graduated / eligible to lead a mentoring program".
     * A plain boolean add — safe on both MySQL and SQLite without driver branching.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_top_host_eligible')) {
                $table->boolean('is_top_host_eligible')->default(false)->after('host_color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_top_host_eligible')) {
                $table->dropColumn('is_top_host_eligible');
            }
        });
    }
};
