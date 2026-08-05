<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Newsletter sign-ups captured from article inline / end-of-post forms.
     *
     * This table is the source of truth for blog subscribers. CRM Audiences are
     * student-backed (audience_student → students → users), so an anonymous email
     * only becomes a broadcast recipient when an admin explicitly pushes it across
     * from the subscribers screen — at which point `audience_id` records the link.
     */
    public function up(): void
    {
        Schema::create('blog_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('locale', 5)->default('ms');

            // Which article converted them — the highest-signal content metric there is.
            $table->foreignId('blog_post_id')->nullable()->constrained('blog_posts')->nullOnDelete();
            $table->string('source', 40)->default('blog');

            $table->foreignId('audience_id')->nullable()->constrained('audiences')->nullOnDelete();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('token', 64)->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->unique('email', 'blog_subscribers_email_unique');
            $table->index('unsubscribed_at', 'blog_subscribers_unsubscribed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_subscribers');
    }
};
