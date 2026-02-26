<?php

namespace App\Listeners\Workflow;

use App\Models\Student;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Support\Facades\Log;

class WorkflowTriggerListener
{
    public function __construct(
        protected WorkflowEngine $workflowEngine
    ) {}

    /**
     * Handle student created event.
     */
    public function handleStudentCreated($event): void
    {
        $student = $event->student ?? $event;

        if (! $student instanceof Student) {
            return;
        }

        $this->triggerWorkflows('contact_created', $student, [
            'event' => 'student_created',
        ]);
    }

    /**
     * Handle student updated event.
     */
    public function handleStudentUpdated($event): void
    {
        $student = $event->student ?? $event;

        if (! $student instanceof Student) {
            return;
        }

        $this->triggerWorkflows('contact_updated', $student, [
            'event' => 'student_updated',
            'changes' => $student->getChanges(),
        ]);
    }

    /**
     * Handle tag added event.
     */
    public function handleTagAdded($event): void
    {
        $student = $event->student ?? null;
        $tag = $event->tag ?? null;

        if (! $student instanceof Student) {
            return;
        }

        $this->triggerWorkflows('tag_added', $student, [
            'event' => 'tag_added',
            'tag_id' => $tag?->id,
            'tag_name' => $tag?->name,
        ]);
    }

    /**
     * Handle tag removed event.
     */
    public function handleTagRemoved($event): void
    {
        $student = $event->student ?? null;
        $tag = $event->tag ?? null;

        if (! $student instanceof Student) {
            return;
        }

        $this->triggerWorkflows('tag_removed', $student, [
            'event' => 'tag_removed',
            'tag_id' => $tag?->id,
            'tag_name' => $tag?->name,
        ]);
    }

    /**
     * Handle order created event.
     */
    public function handleOrderCreated($event): void
    {
        $order = $event->order ?? $event;
        $student = $order->student ?? null;

        if (! $student instanceof Student) {
            return;
        }

        $this->triggerWorkflows('order_created', $student, [
            'event' => 'order_created',
            'order_id' => $order->id,
            'order_total' => $order->total ?? 0,
        ]);
    }

    /**
     * Handle order paid event.
     */
    public function handleOrderPaid($event): void
    {
        $order = $event->order ?? $event;
        $student = $order->student ?? null;

        if (! $student instanceof Student) {
            return;
        }

        $this->triggerWorkflows('order_paid', $student, [
            'event' => 'order_paid',
            'order_id' => $order->id,
            'order_total' => $order->total ?? 0,
        ]);
    }

    /**
     * Handle enrollment created event.
     */
    public function handleEnrollmentCreated($event): void
    {
        $enrollment = $event->enrollment ?? $event;
        $student = $enrollment->student ?? null;

        if (! $student instanceof Student) {
            return;
        }

        $this->triggerWorkflows('enrollment_created', $student, [
            'event' => 'enrollment_created',
            'enrollment_id' => $enrollment->id,
            'class_id' => $enrollment->class_id ?? null,
        ]);
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

                $this->workflowEngine->enroll($workflow, $student, $context);
            }

        } catch (\Exception $e) {
            Log::error("Failed to trigger workflows for {$triggerType}", [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
