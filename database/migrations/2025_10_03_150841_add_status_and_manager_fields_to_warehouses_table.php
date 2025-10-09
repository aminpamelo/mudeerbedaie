<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('agent_id');
            $table->string('manager_name')->nullable()->after('status');
            $table->string('manager_email')->nullable()->after('manager_name');
            $table->string('manager_phone', 20)->nullable()->after('manager_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['status', 'manager_name', 'manager_email', 'manager_phone']);
        });
    }
};
