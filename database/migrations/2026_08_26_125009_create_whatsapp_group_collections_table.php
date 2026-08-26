<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_group_collections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('whatsapp_group_collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('whatsapp_group_collections')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->string('invite_link')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['collection_id', 'sort_order'], 'wa_group_items_collection_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_group_collection_items');
        Schema::dropIfExists('whatsapp_group_collections');
    }
};
