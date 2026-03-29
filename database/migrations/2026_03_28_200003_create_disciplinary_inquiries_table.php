<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('disciplinary_inquiries')) {
            return;
        }

        Schema::create('disciplinary_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplinary_action_id')->constrained('disciplinary_actions');
            $table->date('hearing_date');
            $table->time('hearing_time');
            $table->string('location');
            $table->json('panel_members');
            $table->text('minutes')->nullable();
            $table->text('findings')->nullable();
            $table->string('decision')->nullable(); // guilty, not_guilty, partially_guilty
            $table->text('penalty')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, completed, postponed, cancelled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinary_inquiries');
    }
};
