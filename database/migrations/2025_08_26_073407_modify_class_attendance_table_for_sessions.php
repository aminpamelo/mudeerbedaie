<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('class_attendance', function (Blueprint $table) {
            // Drop existing foreign key and constraint
            $table->dropForeign(['class_id']);
            $table->dropUnique(['class_id', 'student_id']);

            // Add session_id foreign key
            $table->foreignId('session_id')->after('id')->constrained('class_sessions')->onDelete('cascade');

            // Drop class_id column since we'll use session->class relationship
            $table->dropColumn('class_id');

            // Remove enrollment_id as it's not needed with the new structure
            $table->dropForeign(['enrollment_id']);
            $table->dropColumn('enrollment_id');

            // Add new unique constraint
            $table->unique(['session_id', 'student_id'], 'session_student_attendance_unique');

            // Add index for efficient queries
            $table->index(['student_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_attendance', function (Blueprint $table) {
            // Drop new constraints and foreign key
            $table->dropForeign(['session_id']);
            $table->dropUnique(['session_id', 'student_id']);
            $table->dropIndex(['student_id', 'status']);

            // Restore original structure
            $table->foreignId('class_id')->after('id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('enrollment_id')->after('student_id')->constrained('enrollments')->onDelete('cascade');

            // Drop session_id
            $table->dropColumn('session_id');

            // Restore original unique constraint
            $table->unique(['class_id', 'student_id']);
        });
    }
};
