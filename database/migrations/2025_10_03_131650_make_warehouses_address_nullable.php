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
            $table->json('address')->nullable()->change();
            $table->text('description')->nullable()->change();
            $table->string('contact_person')->nullable()->change();
            $table->string('contact_phone')->nullable()->change();
            $table->string('contact_email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->json('address')->nullable(false)->change();
            $table->text('description')->nullable(false)->change();
            $table->string('contact_person')->nullable(false)->change();
            $table->string('contact_phone')->nullable(false)->change();
            $table->string('contact_email')->nullable(false)->change();
        });
    }
};
