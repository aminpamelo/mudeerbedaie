<?php

namespace App\Observers;

use App\Models\StudentTag;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Support\Facades\Log;

class StudentTagObserver
{
    public function __construct(
        protected WorkflowEngine $workflowEngine
    ) {}

    /**
     * Handle the StudentTag "created" event (tag added).
     */
    public function created(StudentTag $studentTag): void
    {
        // Don't trigger workflows if this tag was added by a workflow (prevent loops)
        if ($studentTag->source === 'workflow') {
            return;
        }

        $student = $studentTag->student;
        $tag = $studentTag->tag;

        if (! $student || ! $tag) {
            return;
        }

        $this->triggerWorkflows('tag_added', $student, [
            'event' => 'tag_added',
            'tag_id' => $tag->id,
            'tag_name' => $tag->name,
            'source' => $studentTag->source,
        ]);
    }

    /**
     * Handle the StudentTag "deleted" event (tag removed).
     */
    public function deleted(StudentTag $studentTag): void
    {
        $student = $studentTag->student;
        $tag = $studentTag->tag;

        if (! $student || ! $tag) {
            return;
        }

        $this->triggerWorkflows('tag_removed', $student, [
            'event' => 'tag_removed',
            'tag_id' => $tag->id,
            'tag_name' => $tag->name,
        ]);
    }

    /**
     * Trigger workflows for a specific trigger type.
     */
    protected function triggerWorkflows(string $triggerType, $student, array $context = []): void
    {
        try {
            $workflows = $this->workflowEngine->findWorkflowsForTrigger($triggerType, [
                'tag_id' => $context['tag_id'] ?? null,
            ]);

            foreach ($workflows as $workflow) {
                // Check if workflow has specific tag condition
                $triggerConfig = $workflow->trigger_config ?? [];
                $requiredTagId = $triggerConfig['tag_id'] ?? null;

                // Skip if workflow requires a specific tag and this isn't it
                if ($requiredTagId && $requiredTagId != $context['tag_id']) {
                    continue;
                }

                Log::info("Triggering workflow {$workflow->id} for student {$student->id}", [
                    'trigger_type' => $triggerType,
                    'workflow_name' => $workflow->name,
                    'tag_id' => $context['tag_id'],
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
