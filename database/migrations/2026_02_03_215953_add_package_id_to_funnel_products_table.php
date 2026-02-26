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
        Schema::table('funnel_products', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('course_id')->constrained('packages')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funnel_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_id');
        });
    }
};
