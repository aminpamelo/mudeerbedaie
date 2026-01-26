<?php

namespace App\Services\Workflow;

use App\Jobs\Workflow\ProcessWorkflowStep;
use App\Models\Student;
use App\Models\Workflow;
use App\Models\WorkflowEnrollment;
use App\Models\WorkflowStep;
use App\Models\WorkflowStepExecution;
use Illuminate\Support\Facades\Log;

class WorkflowEngine
{
    /**
     * Enroll a student in a workflow.
     */
    public function enroll(Workflow $workflow, Student $student, array $context = []): ?WorkflowEnrollment
    {
        // Check if workflow is active
        if (! $workflow->isActive()) {
            Log::info("Cannot enroll student {$student->id} - workflow {$workflow->id} is not active");

            return null;
        }

        // Check if student is already enrolled
        $existingEnrollment = $workflow->enrollments()
            ->where('student_id', $student->id)
            ->whereIn('status', ['active', 'waiting'])
            ->first();

        if ($existingEnrollment) {
            Log::info("Student {$student->id} is already enrolled in workflow {$workflow->id}");

            return $existingEnrollment;
        }

        // Create enrollment
        $enrollment = WorkflowEnrollment::create([
            'workflow_id' => $workflow->id,
            'student_id' => $student->id,
            'status' => 'active',
            'context' => $context,
            'enrolled_at' => now(),
        ]);

        Log::info("Enrolled student {$student->id} in workflow {$workflow->id}", [
            'enrollment_id' => $enrollment->id,
        ]);

        // Start processing from the trigger step
        $this->processFromTrigger($enrollment);

        return $enrollment;
    }

    /**
     * Process workflow from the trigger step.
     */
    public function processFromTrigger(WorkflowEnrollment $enrollment): void
    {
        $workflow = $enrollment->workflow;
        $triggerStep = $workflow->steps()->where('type', 'trigger')->first();

        if (! $triggerStep) {
            Log::warning("No trigger step found for workflow {$workflow->id}");
            $this->completeEnrollment($enrollment);

            return;
        }

        // Find the next steps after the trigger
        $nextSteps = $this->getNextSteps($triggerStep);

        foreach ($nextSteps as $nextStep) {
            $this->scheduleStep($enrollment, $nextStep);
        }
    }

    /**
     * Process a specific workflow step.
     */
    public function processStep(WorkflowEnrollment $enrollment, WorkflowStep $step): void
    {
        // Check if enrollment is still active
        if ($enrollment->status !== 'active' && $enrollment->status !== 'waiting') {
            Log::info("Skipping step {$step->id} - enrollment {$enrollment->id} is {$enrollment->status}");

            return;
        }

        // Create step execution record
        $execution = WorkflowStepExecution::create([
            'enrollment_id' => $enrollment->id,
            'step_id' => $step->id,
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            $result = $this->executeStep($enrollment, $step);

            $execution->update([
                'status' => 'completed',
                'result' => $result,
                'completed_at' => now(),
            ]);

            // Update enrollment's current step
            $enrollment->update([
                'current_step_id' => $step->id,
                'status' => 'active',
            ]);

            // Process next steps based on the result
            $this->processNextSteps($enrollment, $step, $result);

        } catch (\Exception $e) {
            Log::error("Failed to execute step {$step->id} for enrollment {$enrollment->id}", [
                'error' => $e->getMessage(),
            ]);

            $execution->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            // Check retry logic or fail the enrollment
            $this->handleStepFailure($enrollment, $step, $e);
        }
    }

    /**
     * Execute a workflow step based on its type.
     */
    protected function executeStep(WorkflowEnrollment $enrollment, WorkflowStep $step): array
    {
        $student = $enrollment->student;
        $config = $step->config ?? [];

        return match ($step->type) {
            'trigger' => $this->executeTrigger($student, $step, $config),
            'action' => $this->executeAction($student, $step, $config),
            'condition' => $this->executeCondition($student, $step, $config),
            'delay' => $this->executeDelay($enrollment, $step, $config),
            default => ['success' => true, 'message' => 'Unknown step type'],
        };
    }

    /**
     * Execute a trigger step (usually just passes through).
     */
    protected function executeTrigger(Student $student, WorkflowStep $step, array $config): array
    {
        return [
            'success' => true,
            'message' => 'Trigger activated',
        ];
    }

    /**
     * Execute an action step.
     */
    protected function executeAction(Student $student, WorkflowStep $step, array $config): array
    {
        $actionType = $step->action_type;
        $handler = $this->getActionHandler($actionType);

        if (! $handler) {
            return [
                'success' => false,
                'message' => "Unknown action type: {$actionType}",
            ];
        }

        return $handler->execute($student, $config);
    }

    /**
     * Execute a condition step.
     */
    protected function executeCondition(Student $student, WorkflowStep $step, array $config): array
    {
        $field = $config['field'] ?? null;
        $operator = $config['operator'] ?? 'equals';
        $value = $config['value'] ?? null;

        if (! $field) {
            return ['success' => true, 'result' => false, 'branch' => 'no'];
        }

        $studentValue = $this->getStudentFieldValue($student, $field);
        $conditionMet = $this->evaluateCondition($studentValue, $operator, $value);

        return [
            'success' => true,
            'result' => $conditionMet,
            'branch' => $conditionMet ? 'yes' : 'no',
        ];
    }

    /**
     * Execute a delay step.
     */
    protected function executeDelay(WorkflowEnrollment $enrollment, WorkflowStep $step, array $config): array
    {
        $delay = $config['delay'] ?? 1;
        $unit = $config['unit'] ?? 'hours';

        $delaySeconds = match ($unit) {
            'minutes' => $delay * 60,
            'hours' => $delay * 3600,
            'days' => $delay * 86400,
            'weeks' => $delay * 604800,
            default => $delay * 3600,
        };

        // Mark enrollment as waiting
        $enrollment->update(['status' => 'waiting']);

        return [
            'success' => true,
            'delay_seconds' => $delaySeconds,
            'resume_at' => now()->addSeconds($delaySeconds)->toIso8601String(),
        ];
    }

    /**
     * Get the next steps after a given step.
     */
    protected function getNextSteps(WorkflowStep $step, ?string $branch = null): array
    {
        $query = $step->workflow->connections()
            ->where('source_step_id', $step->id);

        if ($branch) {
            $query->where('source_handle', $branch);
        }

        $connections = $query->get();
        $nextSteps = [];

        foreach ($connections as $connection) {
            $nextStep = WorkflowStep::find($connection->target_step_id);
            if ($nextStep) {
                $nextSteps[] = $nextStep;
            }
        }

        return $nextSteps;
    }

    /**
     * Process the next steps after a step completes.
     */
    protected function processNextSteps(WorkflowEnrollment $enrollment, WorkflowStep $step, array $result): void
    {
        // Handle delay steps specially
        if ($step->type === 'delay' && isset($result['delay_seconds'])) {
            $nextSteps = $this->getNextSteps($step);
            foreach ($nextSteps as $nextStep) {
                $this->scheduleStep($enrollment, $nextStep, $result['delay_seconds']);
            }

            return;
        }

        // Handle condition branching
        $branch = $result['branch'] ?? null;
        $nextSteps = $this->getNextSteps($step, $branch);

        if (empty($nextSteps)) {
            // No more steps, check if workflow is complete
            $this->checkWorkflowCompletion($enrollment);

            return;
        }

        foreach ($nextSteps as $nextStep) {
            $this->scheduleStep($enrollment, $nextStep);
        }
    }

    /**
     * Schedule a step for processing.
     */
    protected function scheduleStep(WorkflowEnrollment $enrollment, WorkflowStep $step, int $delaySeconds = 0): void
    {
        $job = new ProcessWorkflowStep($enrollment->id, $step->id);

        if ($delaySeconds > 0) {
            dispatch($job)->delay(now()->addSeconds($delaySeconds));
        } else {
            dispatch($job);
        }
    }

    /**
     * Handle step execution failure.
     */
    protected function handleStepFailure(WorkflowEnrollment $enrollment, WorkflowStep $step, \Exception $e): void
    {
        // For now, mark the enrollment as failed
        // In the future, implement retry logic
        $enrollment->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Check if the workflow is complete.
     */
    protected function checkWorkflowCompletion(WorkflowEnrollment $enrollment): void
    {
        // Check if there are any pending step executions
        $pendingExecutions = $enrollment->stepExecutions()
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        if (! $pendingExecutions) {
            $this->completeEnrollment($enrollment);
        }
    }

    /**
     * Mark an enrollment as completed.
     */
    public function completeEnrollment(WorkflowEnrollment $enrollment): void
    {
        $enrollment->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        Log::info("Workflow enrollment {$enrollment->id} completed");
    }

    /**
     * Exit a student from a workflow.
     */
    public function exitEnrollment(WorkflowEnrollment $enrollment, string $reason = 'manual'): void
    {
        $enrollment->update([
            'status' => 'exited',
            'exit_reason' => $reason,
            'completed_at' => now(),
        ]);

        Log::info("Workflow enrollment {$enrollment->id} exited", ['reason' => $reason]);
    }

    /**
     * Get an action handler for a given action type.
     */
    protected function getActionHandler(string $actionType): ?Actions\ActionHandlerInterface
    {
        $handlers = [
            'send_email' => Actions\SendEmailHandler::class,
            'send_whatsapp' => Actions\SendWhatsAppHandler::class,
            'add_tag' => Actions\AddTagHandler::class,
            'remove_tag' => Actions\RemoveTagHandler::class,
            'update_field' => Actions\UpdateFieldHandler::class,
            'add_score' => Actions\AddScoreHandler::class,
            'webhook' => Actions\WebhookHandler::class,
            'send_notification' => Actions\SendNotificationHandler::class,
        ];

        $handlerClass = $handlers[$actionType] ?? null;

        if ($handlerClass && class_exists($handlerClass)) {
            return app($handlerClass);
        }

        return null;
    }

    /**
     * Get a student field value for condition evaluation.
     */
    protected function getStudentFieldValue(Student $student, string $field): mixed
    {
        // Handle nested fields with dot notation
        if (str_contains($field, '.')) {
            $parts = explode('.', $field);
            $value = $student;
            foreach ($parts as $part) {
                $value = $value->{$part} ?? null;
                if ($value === null) {
                    break;
                }
            }

            return $value;
        }

        return $student->{$field} ?? null;
    }

    /**
     * Evaluate a condition.
     */
    protected function evaluateCondition(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'equals', '=' => $actual == $expected,
            'not_equals', '!=' => $actual != $expected,
            'contains' => is_string($actual) && str_contains($actual, $expected),
            'not_contains' => is_string($actual) && ! str_contains($actual, $expected),
            'starts_with' => is_string($actual) && str_starts_with($actual, $expected),
            'ends_with' => is_string($actual) && str_ends_with($actual, $expected),
            'greater_than', '>' => $actual > $expected,
            'less_than', '<' => $actual < $expected,
            'greater_or_equal', '>=' => $actual >= $expected,
            'less_or_equal', '<=' => $actual <= $expected,
            'is_empty' => empty($actual),
            'is_not_empty' => ! empty($actual),
            'is_true' => (bool) $actual === true,
            'is_false' => (bool) $actual === false,
            default => false,
        };
    }

    /**
     * Find workflows that match a specific trigger.
     */
    public function findWorkflowsForTrigger(string $triggerType, array $conditions = []): array
    {
        return Workflow::query()
            ->where('status', 'active')
            ->where('trigger_type', $triggerType)
            ->get()
            ->filter(function ($workflow) use ($conditions) {
                return $this->matchesTriggerConditions($workflow, $conditions);
            })
            ->all();
    }

    /**
     * Check if a workflow matches trigger conditions.
     */
    protected function matchesTriggerConditions(Workflow $workflow, array $conditions): bool
    {
        $triggerConfig = $workflow->trigger_config ?? [];

        foreach ($conditions as $key => $value) {
            if (isset($triggerConfig[$key]) && $triggerConfig[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}
