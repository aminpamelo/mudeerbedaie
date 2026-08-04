<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When true, HQ/admin marks this funnel as copyable by fighters — it shows
     * up in the fighter Funnel Library and can be cloned into a fighter-owned
     * funnel.
     */
    public function up(): void
    {
        Schema::table('funnels', function (Blueprint $table): void {
            $table->boolean('available_to_fighters')->default(false)->index()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table): void {
            $table->dropColumn('available_to_fighters');
        });
    }
};
