<?php

namespace App\Jobs\Workflow;

use App\Models\WorkflowEnrollment;
use App\Models\WorkflowStep;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessWorkflowStep implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $enrollmentId,
        public int $stepId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WorkflowEngine $engine): void
    {
        $enrollment = WorkflowEnrollment::find($this->enrollmentId);
        $step = WorkflowStep::find($this->stepId);

        if (! $enrollment || ! $step) {
            Log::warning('ProcessWorkflowStep: Enrollment or step not found', [
                'enrollment_id' => $this->enrollmentId,
                'step_id' => $this->stepId,
            ]);

            return;
        }

        // Check if enrollment is still valid for processing
        if (! in_array($enrollment->status, ['active', 'waiting'])) {
            Log::info('ProcessWorkflowStep: Enrollment not active', [
                'enrollment_id' => $this->enrollmentId,
                'status' => $enrollment->status,
            ]);

            return;
        }

        Log::info('Processing workflow step', [
            'enrollment_id' => $this->enrollmentId,
            'step_id' => $this->stepId,
            'step_type' => $step->type,
            'action_type' => $step->action_type,
        ]);

        $engine->processStep($enrollment, $step);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessWorkflowStep failed', [
            'enrollment_id' => $this->enrollmentId,
            'step_id' => $this->stepId,
            'error' => $exception->getMessage(),
        ]);

        // Update enrollment status to failed if all retries exhausted
        $enrollment = WorkflowEnrollment::find($this->enrollmentId);
        if ($enrollment) {
            $enrollment->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);
        }
    }
}
