<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->foreignId('referenced_content_id')->nullable()->constrained('contents')->nullOnDelete();
            $table->string('referenced_url', 500)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['content_id', 'position']);
            $table->index('referenced_content_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_references');
    }
};
