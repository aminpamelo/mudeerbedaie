<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_sales_pages', function (Blueprint $table): void {
            $table->text('design_notes')->nullable()->after('tone');
        });
    }

    public function down(): void
    {
        Schema::table('ai_sales_pages', function (Blueprint $table): void {
            $table->dropColumn('design_notes');
        });
    }
};
