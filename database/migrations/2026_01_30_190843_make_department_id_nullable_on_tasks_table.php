<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite doesn't support ALTER COLUMN, so we recreate the table
        DB::statement('PRAGMA foreign_keys=off');

        DB::statement('CREATE TABLE tasks_temp AS SELECT * FROM tasks');
        DB::statement('DROP TABLE tasks');

        DB::statement('
            CREATE TABLE tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_number VARCHAR NOT NULL,
                department_id INTEGER NULL,
                title VARCHAR NOT NULL,
                description TEXT NULL,
                task_type VARCHAR NOT NULL DEFAULT \'adhoc\',
                status VARCHAR NOT NULL DEFAULT \'todo\',
                priority VARCHAR NOT NULL DEFAULT \'medium\',
                assigned_to INTEGER NULL,
                created_by INTEGER NOT NULL,
                due_date DATE NULL,
                due_time TIME NULL,
                started_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                cancelled_at TIMESTAMP NULL,
                estimated_hours DECIMAL(8,2) NULL,
                actual_hours DECIMAL(8,2) NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                metadata TEXT NULL,
                reminder_sent_at TIMESTAMP NULL,
                overdue_notified_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
            )
        ');

        DB::statement('INSERT INTO tasks SELECT * FROM tasks_temp');
        DB::statement('DROP TABLE tasks_temp');

        DB::statement('CREATE UNIQUE INDEX tasks_task_number_unique ON tasks(task_number)');
        DB::statement('CREATE INDEX tasks_department_id_status_index ON tasks(department_id, status)');

        DB::statement('PRAGMA foreign_keys=on');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This would require making department_id NOT NULL again
        // which could fail if there are tasks with NULL department_id
    }
};
