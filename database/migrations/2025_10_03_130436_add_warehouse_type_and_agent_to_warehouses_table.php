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
            $table->enum('warehouse_type', ['own', 'agent', 'company'])->default('own')->after('code');
            $table->foreignId('agent_id')->nullable()->after('warehouse_type')->constrained('agents')->nullOnDelete();

            $table->index(['warehouse_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropIndex(['warehouse_type', 'is_active']);
            $table->dropColumn(['warehouse_type', 'agent_id']);
        });
    }
};
