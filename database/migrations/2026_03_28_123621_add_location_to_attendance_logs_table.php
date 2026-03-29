<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('attendance_logs', 'clock_in_latitude')) {
            return;
        }

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->decimal('clock_in_latitude', 10, 7)->nullable()->after('clock_in_ip');
            $table->decimal('clock_in_longitude', 10, 7)->nullable()->after('clock_in_latitude');
            $table->decimal('clock_out_latitude', 10, 7)->nullable()->after('clock_out_ip');
            $table->decimal('clock_out_longitude', 10, 7)->nullable()->after('clock_out_latitude');
        });

        // Seed office location settings
        Setting::setValue('hr_office_latitude', '3.1390', 'string', 'hr');
        Setting::setValue('hr_office_longitude', '101.6869', 'string', 'hr');
        Setting::setValue('hr_office_radius_meters', '200', 'number', 'hr');
        Setting::setValue('hr_require_location_office', '1', 'boolean', 'hr');
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn([
                'clock_in_latitude',
                'clock_in_longitude',
                'clock_out_latitude',
                'clock_out_longitude',
            ]);
        });

        Setting::where('key', 'like', 'hr_office_%')->orWhere('key', 'hr_require_location_office')->delete();
    }
};
