<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a `deleted_at` column so orders can be soft-deleted (e.g. a Fighter
     * trashing a manual/funnel order they created) and later restored. Works on
     * both MySQL and SQLite — `softDeletes()` is a plain nullable timestamp.
     */
    public function up(): void
    {
        Schema::table('product_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_orders', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('product_orders', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
