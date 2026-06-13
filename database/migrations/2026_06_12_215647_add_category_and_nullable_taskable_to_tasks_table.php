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
        if (! Schema::hasColumn('tasks', 'category_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('taskable_id')
                    ->constrained('task_categories')
                    ->nullOnDelete();
            });
        }

        $this->setTaskableNullable(true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('tasks')->whereNull('taskable_type')->orWhereNull('taskable_id')->exists()) {
            throw new RuntimeException(
                'Cannot roll back: standalone tasks (with no parent meeting) exist. '
                .'Delete or reassign them before reverting the taskable columns to NOT NULL.'
            );
        }

        $this->setTaskableNullable(false);

        if (Schema::hasColumn('tasks', 'category_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            });
        }
    }

    /**
     * Toggle nullability of the polymorphic taskable columns so a task can exist
     * without a parent meeting (a standalone task). Works on both MySQL and SQLite.
     */
    private function setTaskableNullable(bool $nullable): void
    {
        if (DB::getDriverName() === 'mysql') {
            $null = $nullable ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE tasks MODIFY taskable_type VARCHAR(255) {$null}");
            DB::statement("ALTER TABLE tasks MODIFY taskable_id BIGINT UNSIGNED {$null}");

            return;
        }

        Schema::table('tasks', function (Blueprint $table) use ($nullable) {
            $type = $table->string('taskable_type');
            $id = $table->unsignedBigInteger('taskable_id');

            if ($nullable) {
                $type->nullable();
                $id->nullable();
            }

            $type->change();
            $id->change();
        });
    }
};
