<?php

namespace App\Observers;

use App\Models\Student;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Support\Facades\Log;

class StudentObserver
{
    public function __construct(
        protected WorkflowEngine $workflowEngine
    ) {}

    /**
     * Handle the Student "created" event.
     */
    public function created(Student $student): void
    {
        $this->triggerWorkflows('contact_created', $student, [
            'event' => 'student_created',
        ]);
    }

    /**
     * Handle the Student "updated" event.
     */
    public function updated(Student $student): void
    {
        $this->triggerWorkflows('contact_updated', $student, [
            'event' => 'student_updated',
            'changes' => $student->getChanges(),
        ]);
    }

    /**
     * Handle the Student "deleted" event.
     */
    public function deleted(Student $student): void
    {
        // Cancel any active workflow enrollments
        $student->workflowEnrollments()
            ->whereIn('status', ['active', 'waiting'])
            ->update([
                'status' => 'exited',
                'exit_reason' => 'student_deleted',
                'completed_at' => now(),
            ]);
    }

    /**
     * Handle the Student "restored" event.
     */
    public function restored(Student $student): void
    {
        //
    }

    /**
     * Handle the Student "force deleted" event.
     */
    public function forceDeleted(Student $student): void
    {
        //
    }

    /**
     * Trigger workflows for a specific trigger type.
     */
    protected function triggerWorkflows(string $triggerType, Student $student, array $context = []): void
    {
        try {
            $workflows = $this->workflowEngine->findWorkflowsForTrigger($triggerType);

            foreach ($workflows as $workflow) {
                Log::info("Triggering workflow {$workflow->id} for student {$student->id}", [
                    'trigger_type' => $triggerType,
                    'workflow_name' => $workflow->name,
                ]);

                // Dispatch to queue to avoid blocking
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
