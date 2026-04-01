<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE department_approvers MODIFY COLUMN approval_type ENUM('overtime', 'leave', 'claims', 'exit_permission') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE department_approvers MODIFY COLUMN approval_type ENUM('overtime', 'leave', 'claims') NOT NULL");
        }
    }
};
