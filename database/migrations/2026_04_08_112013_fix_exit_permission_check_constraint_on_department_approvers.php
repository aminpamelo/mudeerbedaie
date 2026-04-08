<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $hasOld = Schema::hasColumn('department_approvers', 'approval_type_old');
            $hasNew = Schema::hasColumn('department_approvers', 'approval_type');

            if ($hasOld && $hasNew) {
                // Both columns exist from a previous partial migration.
                // Copy data from old to new, drop the stale index, then drop old column.
                DB::table('department_approvers')->whereNull('approval_type')->update([
                    'approval_type' => DB::raw('"approval_type_old"'),
                ]);

                // Drop the index that references approval_type_old before dropping the column
                Schema::table('department_approvers', function (Blueprint $table) {
                    $table->dropIndex('department_approvers_department_id_approval_type_index');
                });

                Schema::table('department_approvers', function (Blueprint $table) {
                    $table->dropColumn('approval_type_old');
                });

                // Re-create the index on the correct column
                Schema::table('department_approvers', function (Blueprint $table) {
                    $table->index(['department_id', 'approval_type'], 'department_approvers_department_id_approval_type_index');
                });
            } elseif ($hasOld && ! $hasNew) {
                // Only old column exists
                Schema::table('department_approvers', function (Blueprint $table) {
                    $table->dropIndex('department_approvers_department_id_approval_type_index');
                });

                Schema::table('department_approvers', function (Blueprint $table) {
                    $table->enum('approval_type', ['overtime', 'leave', 'claims', 'exit_permission'])->default('overtime')->after('approver_employee_id');
                });

                DB::table('department_approvers')->update([
                    'approval_type' => DB::raw('"approval_type_old"'),
                ]);

                Schema::table('department_approvers', function (Blueprint $table) {
                    $table->dropColumn('approval_type_old');
                });

                Schema::table('department_approvers', function (Blueprint $table) {
                    $table->index(['department_id', 'approval_type'], 'department_approvers_department_id_approval_type_index');
                });
            } elseif (! $hasOld && $hasNew) {
                // Normal case: drop index, rename, add new, copy, drop old, re-create index
                try {
                    Schema::table('department_approvers', function (Blueprint $table) {
                        $table->dropIndex('department_approvers_department_id_approval_type_index');
                    });
                } catch (\Throwable) {
                    // Index may not exist on fresh migrations
                }

                Schema::table('department_approvers', function (Blueprint $table) {
                    $table->renameColumn('approval_type', 'approval_type_old');
                });

                Schema::table('department_approvers', function (Blueprint $table) {
                    $table->enum('approval_type', ['overtime', 'leave', 'claims', 'exit_permission'])->default('overtime')->after('approver_employee_id');
                });

                DB::table('department_approvers')->update([
                    'approval_type' => DB::raw('"approval_type_old"'),
                ]);

                Schema::table('department_approvers', function (Blueprint $table) {
                    $table->dropColumn('approval_type_old');
                });

                Schema::table('department_approvers', function (Blueprint $table) {
                    $table->index(['department_id', 'approval_type'], 'department_approvers_department_id_approval_type_index');
                });
            }
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('department_approvers', function (Blueprint $table) {
                $table->renameColumn('approval_type', 'approval_type_old');
            });

            Schema::table('department_approvers', function (Blueprint $table) {
                $table->enum('approval_type', ['overtime', 'leave', 'claims'])->default('overtime')->after('approver_employee_id');
            });

            DB::table('department_approvers')->update([
                'approval_type' => DB::raw('"approval_type_old"'),
            ]);

            Schema::table('department_approvers', function (Blueprint $table) {
                $table->dropColumn('approval_type_old');
            });
        }
    }
};
