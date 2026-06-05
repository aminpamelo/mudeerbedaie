<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The canonical creator account ("nickname") a host goes live on. This is
     * the new punca kuasa for the Live Host timetable. The stable identity is
     * the TikTok creator_user_id; the handle/display_name are display labels
     * only (they drift across CSV imports), so the numeric id is the merge key
     * and normalized_handle is the fallback when no id is present.
     */
    public function up(): void
    {
        Schema::create('live_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('creator_user_id')->nullable();
            $table->string('nickname')->nullable();
            $table->string('display_name')->nullable();
            $table->string('normalized_handle')->nullable();
            $table->string('avatar_url')->nullable();
            $table->unsignedBigInteger('follower_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('needs_review')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('creator_user_id', 'live_accounts_creator_user_id_unique');
            $table->index('normalized_handle', 'live_accounts_normalized_handle_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_accounts');
    }
};
