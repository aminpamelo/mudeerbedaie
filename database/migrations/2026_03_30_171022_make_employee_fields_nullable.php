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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('ic_number')->nullable()->change();
            $table->date('date_of_birth')->nullable()->change();
            $table->string('gender')->nullable()->change();
            $table->string('religion')->nullable()->change();
            $table->string('race')->nullable()->change();
            $table->string('marital_status')->nullable()->change();
            $table->string('phone')->nullable()->change();
            $table->string('personal_email')->nullable()->change();
            $table->string('address_line_1')->nullable()->change();
            $table->string('city')->nullable()->change();
            $table->string('state')->nullable()->change();
            $table->string('postcode')->nullable()->change();
            $table->string('employment_type')->nullable()->change();
            $table->date('join_date')->nullable()->change();
            $table->string('bank_name')->nullable()->change();
            $table->string('bank_account_number')->nullable()->change();
        });

        // Handle foreign key columns separately
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['position_id']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->change();
            $table->unsignedBigInteger('position_id')->nullable()->change();

            $table->foreign('department_id')->references('id')->on('departments')->cascadeOnDelete();
            $table->foreign('position_id')->references('id')->on('positions')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('ic_number')->nullable(false)->change();
            $table->date('date_of_birth')->nullable(false)->change();
            $table->string('gender')->nullable(false)->change();
            $table->string('religion')->nullable(false)->change();
            $table->string('race')->nullable(false)->change();
            $table->string('marital_status')->nullable(false)->change();
            $table->string('phone')->nullable(false)->change();
            $table->string('personal_email')->nullable(false)->change();
            $table->string('address_line_1')->nullable(false)->change();
            $table->string('city')->nullable(false)->change();
            $table->string('state')->nullable(false)->change();
            $table->string('postcode')->nullable(false)->change();
            $table->string('employment_type')->nullable(false)->change();
            $table->date('join_date')->nullable(false)->change();
            $table->string('bank_name')->nullable(false)->change();
            $table->string('bank_account_number')->nullable(false)->change();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['position_id']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable(false)->change();
            $table->unsignedBigInteger('position_id')->nullable(false)->change();

            $table->foreign('department_id')->references('id')->on('departments')->cascadeOnDelete();
            $table->foreign('position_id')->references('id')->on('positions')->cascadeOnDelete();
        });
    }
};
