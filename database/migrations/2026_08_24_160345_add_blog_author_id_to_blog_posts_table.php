<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->foreignId('blog_author_id')
                ->nullable()
                ->after('author_id')
                ->constrained('blog_authors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropForeign(['blog_author_id']);
            $table->dropColumn('blog_author_id');
        });
    }
};
