<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_sales_pages', function (Blueprint $table): void {
            $table->string('style_preset')->nullable()->default('auto')->after('design_notes');
        });
    }

    public function down(): void
    {
        Schema::table('ai_sales_pages', function (Blueprint $table): void {
            $table->dropColumn('style_preset');
        });
    }
};
