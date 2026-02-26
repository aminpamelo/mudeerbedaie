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
        Schema::table('pending_platform_products', function (Blueprint $table) {
            $table->foreignId('suggested_package_id')->nullable()->after('suggested_variant_id')
                ->constrained('packages')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pending_platform_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suggested_package_id');
        });
    }
};
