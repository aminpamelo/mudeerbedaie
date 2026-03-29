<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('disciplinary_actions')) {
            return;
        }

        Schema::create('disciplinary_actions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('type'); // verbal_warning, first_written, second_written, show_cause, suspension, termination
            $table->text('reason');
            $table->date('incident_date');
            $table->date('issued_date')->nullable();
            $table->foreignId('issued_by')->constrained('employees');
            $table->boolean('response_required')->default(false);
            $table->date('response_deadline')->nullable();
            $table->text('employee_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('outcome')->nullable();
            $table->string('letter_pdf_path')->nullable();
            $table->string('status')->default('draft'); // draft, issued, pending_response, responded, closed
            $table->foreignId('previous_action_id')->nullable()->constrained('disciplinary_actions');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinary_actions');
    }
};
