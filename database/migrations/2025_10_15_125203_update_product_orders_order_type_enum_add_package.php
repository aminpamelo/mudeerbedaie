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
        // SQLite doesn't have ENUM, it's stored as TEXT with CHECK constraint
        // We don't need to modify anything for SQLite - it already accepts any text value
        // The validation happens at the application level

        // For MySQL/PostgreSQL, we would update the ENUM here
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE product_orders MODIFY COLUMN order_type ENUM('retail', 'wholesale', 'b2b', 'package') DEFAULT 'retail'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE product_orders MODIFY COLUMN order_type ENUM('retail', 'wholesale', 'b2b') DEFAULT 'retail'");
        }
    }
};
