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
        Schema::table('department_approvers', function (Blueprint $table) {
            $table->unsignedInteger('tier')->default(1)->after('approval_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('department_approvers', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }
};
