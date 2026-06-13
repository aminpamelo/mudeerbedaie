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
        if (Schema::hasColumn('it_tickets', 'category_id')) {
            return;
        }

        Schema::table('it_tickets', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('priority')
                ->constrained('it_ticket_categories')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('it_tickets', 'category_id')) {
            return;
        }

        Schema::table('it_tickets', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
