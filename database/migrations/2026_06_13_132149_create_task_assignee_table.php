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
        if (! Schema::hasTable('task_assignee')) {
            Schema::create('task_assignee', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['task_id', 'employee_id']);
            });
        }

        // Backfill: every existing task's single assignee becomes its first co-owner,
        // so multi-owner queries return the current owner for legacy tasks.
        $now = now();

        DB::table('tasks')
            ->whereNotNull('assigned_to')
            ->orderBy('id')
            ->select('id', 'assigned_to')
            ->chunkById(500, function ($tasks) use ($now) {
                $rows = $tasks->map(fn ($task) => [
                    'task_id' => $task->id,
                    'employee_id' => $task->assigned_to,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows !== []) {
                    DB::table('task_assignee')->insertOrIgnore($rows);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_assignee');
    }
};
