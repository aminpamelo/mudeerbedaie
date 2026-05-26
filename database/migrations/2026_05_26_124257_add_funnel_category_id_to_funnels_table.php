<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->foreignId('funnel_category_id')
                ->nullable()
                ->after('template_id')
                ->constrained('funnel_categories')
                ->nullOnDelete();

            $table->index(['user_id', 'funnel_category_id']);
        });
    }

    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'funnel_category_id']);
            $table->dropConstrainedForeignId('funnel_category_id');
        });
    }
};
