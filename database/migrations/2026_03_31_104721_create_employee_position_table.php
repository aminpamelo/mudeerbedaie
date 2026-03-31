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
        if (Schema::hasTable('employee_position')) {
            return;
        }

        Schema::create('employee_position', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['employee_id', 'position_id']);
        });

        // Migrate existing position_id data from employees table to pivot table
        DB::table('employees')
            ->whereNotNull('position_id')
            ->orderBy('id')
            ->each(function ($employee) {
                DB::table('employee_position')->insert([
                    'employee_id' => $employee->id,
                    'position_id' => $employee->position_id,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_position');
    }
};
