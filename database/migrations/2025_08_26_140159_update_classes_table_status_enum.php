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
        Schema::table('classes', function (Blueprint $table) {
            // Update the status enum to match ClassModel expectations
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled', 'suspended'])
                ->default('draft')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            // Revert back to original enum values
            $table->enum('status', ['scheduled', 'ongoing', 'completed', 'cancelled'])
                ->default('scheduled')
                ->change();
        });
    }
};
