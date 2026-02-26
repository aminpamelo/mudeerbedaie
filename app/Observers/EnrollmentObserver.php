<?php

namespace App\Observers;

use App\Models\Enrollment;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Support\Facades\Log;

class EnrollmentObserver
{
    public function __construct(
        protected WorkflowEngine $workflowEngine
    ) {}

    /**
     * Handle the Enrollment "created" event.
     */
    public function created(Enrollment $enrollment): void
    {
        $student = $enrollment->student;

        if (! $student) {
            return;
        }

        $this->triggerWorkflows('enrollment_created', $student, [
            'event' => 'enrollment_created',
            'enrollment_id' => $enrollment->id,
            'course_id' => $enrollment->course_id,
            'course_name' => $enrollment->course?->name,
            'enrollment_date' => $enrollment->enrollment_date?->toDateString(),
            'enrollment_fee' => $enrollment->enrollment_fee,
        ]);
    }

    /**
     * Handle the Enrollment "updated" event.
     */
    public function updated(Enrollment $enrollment): void
    {
        $student = $enrollment->student;

        if (! $student) {
            return;
        }

        $changes = $enrollment->getChanges();

        // Check for status changes
        if (isset($changes['status'])) {
            $newStatus = $enrollment->status;

            // Trigger enrollment_completed when status changes to completed
            if ($newStatus === 'completed') {
                $this->triggerWorkflows('enrollment_completed', $student, [
                    'event' => 'enrollment_completed',
                    'enrollment_id' => $enrollment->id,
                    'course_id' => $enrollment->course_id,
                    'course_name' => $enrollment->course?->name,
                    'completion_date' => $enrollment->completion_date?->toDateString(),
                ]);
            }

            // Trigger enrollment_cancelled when status changes to cancelled/dropped
            if (in_array($newStatus, ['cancelled', 'dropped', 'withdrawn'])) {
                $this->triggerWorkflows('enrollment_cancelled', $student, [
                    'event' => 'enrollment_cancelled',
                    'enrollment_id' => $enrollment->id,
                    'course_id' => $enrollment->course_id,
                    'course_name' => $enrollment->course?->name,
                    'status' => $newStatus,
                ]);
            }
        }

        // Check for subscription status changes
        if (isset($changes['subscription_status'])) {
            $newSubStatus = $enrollment->subscription_status;

            // Trigger subscription_cancelled
            if ($newSubStatus === 'canceled') {
                $this->triggerWorkflows('subscription_cancelled', $student, [
                    'event' => 'subscription_cancelled',
                    'enrollment_id' => $enrollment->id,
                    'course_id' => $enrollment->course_id,
                    'course_name' => $enrollment->course?->name,
                ]);
            }

            // Trigger subscription_past_due
            if ($newSubStatus === 'past_due') {
                $this->triggerWorkflows('subscription_past_due', $student, [
                    'event' => 'subscription_past_due',
                    'enrollment_id' => $enrollment->id,
                    'course_id' => $enrollment->course_id,
                    'course_name' => $enrollment->course?->name,
                ]);
            }
        }
    }

    /**
     * Trigger workflows for a specific trigger type.
     */
    protected function triggerWorkflows(string $triggerType, $student, array $context = []): void
    {
        try {
            $workflows = $this->workflowEngine->findWorkflowsForTrigger($triggerType, [
                'course_id' => $context['course_id'] ?? null,
            ]);

            foreach ($workflows as $workflow) {
                // Check if workflow has specific course condition
                $triggerConfig = $workflow->trigger_config ?? [];
                $requiredCourseId = $triggerConfig['course_id'] ?? null;

                // Skip if workflow requires a specific course and this isn't it
                if ($requiredCourseId && $requiredCourseId != $context['course_id']) {
                    continue;
                }

                Log::info("Triggering workflow {$workflow->id} for student {$student->id}", [
                    'trigger_type' => $triggerType,
                    'workflow_name' => $workflow->name,
                    'course_id' => $context['course_id'],
                ]);

                dispatch(function () use ($workflow, $student, $context) {
                    app(WorkflowEngine::class)->enroll($workflow, $student, $context);
                })->afterResponse();
            }

        } catch (\Exception $e) {
            Log::error("Failed to trigger workflows for {$triggerType}", [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
