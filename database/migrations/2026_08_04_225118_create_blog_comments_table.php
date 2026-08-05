<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained('blog_posts')->cascadeOnDelete();

            // nullOnDelete keeps the thread readable if the account is removed;
            // author_name snapshots the display name at posting time.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name')->nullable();

            // Self-referencing parent for one level of threaded replies.
            $table->foreignId('parent_id')->nullable()->constrained('blog_comments')->cascadeOnDelete();

            $table->text('body');

            // pending | approved | spam
            $table->string('status', 20)->default('pending');

            $table->string('ip_address', 45)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['blog_post_id', 'status'], 'blog_comments_post_status_index');
            $table->index('status', 'blog_comments_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_comments');
    }
};
