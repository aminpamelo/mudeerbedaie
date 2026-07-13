<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot linking a CMS content piece to the live hosts featured as its talent.
     */
    public function up(): void
    {
        Schema::create('content_talent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['content_id', 'user_id'], 'content_talent_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_talent');
    }
};
