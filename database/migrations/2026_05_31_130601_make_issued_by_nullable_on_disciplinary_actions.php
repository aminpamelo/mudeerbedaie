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
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE disciplinary_actions MODIFY issued_by BIGINT UNSIGNED NULL');
        } else {
            Schema::table('disciplinary_actions', function (Blueprint $table) {
                $table->unsignedBigInteger('issued_by')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('disciplinary_actions')->whereNull('issued_by')->update(['issued_by' => DB::raw('employee_id')]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE disciplinary_actions MODIFY issued_by BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('disciplinary_actions', function (Blueprint $table) {
                $table->unsignedBigInteger('issued_by')->nullable(false)->change();
            });
        }
    }
};
