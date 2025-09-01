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
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('bank_account_holder')->nullable()->after('phone');
            $table->text('bank_account_number')->nullable()->after('bank_account_holder'); // Encrypted
            $table->string('bank_name')->nullable()->after('bank_account_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn(['bank_account_holder', 'bank_account_number', 'bank_name']);
        });
    }
};
