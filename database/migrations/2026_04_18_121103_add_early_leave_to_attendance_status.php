<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE attendance_logs MODIFY status ENUM('present','absent','late','half_day','on_leave','holiday','wfh','early_leave') NOT NULL");
        } else {
            // SQLite doesn't enforce enum constraints, so no change needed
            // The string column already accepts any value
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // Revert any early_leave records back to present before removing the enum value
            DB::table('attendance_logs')->where('status', 'early_leave')->update(['status' => 'present']);
            DB::statement("ALTER TABLE attendance_logs MODIFY status ENUM('present','absent','late','half_day','on_leave','holiday','wfh') NOT NULL");
        }
    }
};
