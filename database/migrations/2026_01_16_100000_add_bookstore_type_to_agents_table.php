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
        // Add pricing tier and related columns
        // Check database driver
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support enum modification, so we'll change to string
            // The validation will be handled at the application level
            Schema::table('agents', function (Blueprint $table) {
                // Add new columns
                $table->string('pricing_tier', 20)->default('standard')->after('type');
                $table->decimal('commission_rate', 5, 2)->nullable()->after('pricing_tier');
                $table->decimal('credit_limit', 12, 2)->default(0)->after('commission_rate');
                $table->boolean('consignment_enabled')->default(false)->after('credit_limit');
            });
        } else {
            // For MySQL/PostgreSQL
            Schema::table('agents', function (Blueprint $table) {
                $table->string('pricing_tier', 20)->default('standard')->after('type');
                $table->decimal('commission_rate', 5, 2)->nullable()->after('pricing_tier');
                $table->decimal('credit_limit', 12, 2)->default(0)->after('commission_rate');
                $table->boolean('consignment_enabled')->default(false)->after('credit_limit');
            });
        }

        // Add index for pricing tier queries
        Schema::table('agents', function (Blueprint $table) {
            $table->index('pricing_tier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        Schema::table('agents', function (Blueprint $table) {
            $table->dropIndex(['pricing_tier']);
            $table->dropColumn(['pricing_tier', 'commission_rate', 'credit_limit', 'consignment_enabled']);
        });

        if ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE agents MODIFY COLUMN type ENUM('agent', 'company') NOT NULL DEFAULT 'agent'");
        }
    }
};
