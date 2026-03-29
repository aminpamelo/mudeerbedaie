<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exit_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_checklist_id')->constrained('exit_checklists')->cascadeOnDelete();
            $table->string('title');
            $table->string('category'); // asset_return, system_access, documentation, clearance, other
            $table->foreignId('assigned_to')->nullable()->constrained('employees');
            $table->string('status')->default('pending'); // pending, completed, not_applicable
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_checklist_items');
    }
};
