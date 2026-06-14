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
        // Comments may be authored by users who are not employees (admins/CEOs),
        // so the employee link must be optional.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE task_comments MODIFY employee_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('task_comments', function (Blueprint $table) {
                $table->unsignedBigInteger('employee_id')->nullable()->change();
            });
        }

        // Author as a user account — always known, even for non-employees.
        if (! Schema::hasColumn('task_comments', 'user_id')) {
            Schema::table('task_comments', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('employee_id')
                    ->constrained('users')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('task_comments', 'user_id')) {
            Schema::table('task_comments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
