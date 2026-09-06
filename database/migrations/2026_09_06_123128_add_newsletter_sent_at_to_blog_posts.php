<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            // Stamped the first time a post goes live, so the new-article
            // newsletter fires exactly once and re-saves never re-send it.
            $table->timestamp('newsletter_sent_at')->nullable()->after('published_at');
        });

        // Backfill posts that were already live before this feature existed:
        // mark them notified so a later edit/re-save never retro-blasts the
        // whole subscriber list with an old article. (Valid on MySQL + SQLite.)
        DB::table('blog_posts')
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->update(['newsletter_sent_at' => DB::raw('published_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('newsletter_sent_at');
        });
    }
};
