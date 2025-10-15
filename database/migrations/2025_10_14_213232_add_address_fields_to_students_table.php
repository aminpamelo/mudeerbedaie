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
        Schema::table('students', function (Blueprint $table) {
            // Rename old address to address_line_1
            $table->renameColumn('address', 'address_line_1');
        });

        Schema::table('students', function (Blueprint $table) {
            // Add new address fields
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('city')->nullable()->after('address_line_2');
            $table->string('state')->nullable()->after('city');
            $table->string('postcode')->nullable()->after('state');
            $table->string('country')->nullable()->default('Malaysia')->after('postcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Drop new fields
            $table->dropColumn(['address_line_2', 'city', 'state', 'postcode', 'country']);
        });

        Schema::table('students', function (Blueprint $table) {
            // Rename back to address
            $table->renameColumn('address_line_1', 'address');
        });
    }
};
