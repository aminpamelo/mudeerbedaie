<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PIC-recorded disciplinary/conduct log for a mentee (live host). A record,
     * not a workflow — the PIC logs an incident (lateness, absence, rule
     * violation, misconduct, other) with a severity and description. Mentoring
     * scoped and lightweight; distinct from HR's Employee-based DisciplinaryAction.
     */
    public function up(): void
    {
        Schema::create('live_host_mentee_disciplinary_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentee_id')->constrained('live_host_mentees')->cascadeOnDelete();
            $table->date('incident_date');
            $table->string('category')->default('other'); // lateness|absence|rule_violation|misconduct|other
            $table->string('severity')->default('minor'); // minor|major
            $table->text('description');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['mentee_id', 'incident_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_host_mentee_disciplinary_records');
    }
};
