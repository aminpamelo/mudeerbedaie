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
        $this->setAssignedByNullable(true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('tasks')->whereNull('assigned_by')->exists()) {
            throw new RuntimeException(
                'Cannot roll back: tasks with a null assigned_by exist (created by a user with no '
                .'linked employee). Backfill assigned_by before reverting it to NOT NULL.'
            );
        }

        $this->setAssignedByNullable(false);
    }

    /**
     * Toggle nullability of tasks.assigned_by so a task created by a user without a
     * linked employee (e.g. an admin) does not violate the NOT NULL constraint.
     * Works on both MySQL and SQLite.
     */
    private function setAssignedByNullable(bool $nullable): void
    {
        if (DB::getDriverName() === 'mysql') {
            $null = $nullable ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE tasks MODIFY assigned_by BIGINT UNSIGNED {$null}");

            return;
        }

        Schema::table('tasks', function (Blueprint $table) use ($nullable) {
            $column = $table->unsignedBigInteger('assigned_by');

            if ($nullable) {
                $column->nullable();
            }

            $column->change();
        });
    }
};
