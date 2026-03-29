<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('letter_templates')) {
            return;
        }

        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // verbal_warning, first_written, second_written, show_cause, termination, offer_letter, resignation_acceptance
            $table->text('content'); // HTML template with {{placeholders}}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_templates');
    }
};
