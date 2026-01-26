<?php

namespace App\Observers;

use App\Models\ClassAttendance;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Support\Facades\Log;

class ClassAttendanceObserver
{
    public function __construct(
        protected WorkflowEngine $workflowEngine
    ) {}

    /**
     * Handle the ClassAttendance "created" event.
     */
    public function created(ClassAttendance $attendance): void
    {
        $this->handleAttendanceChange($attendance, 'created');
    }

    /**
     * Handle the ClassAttendance "updated" event.
     */
    public function updated(ClassAttendance $attendance): void
    {
        $changes = $attendance->getChanges();

        // Only trigger if status changed
        if (isset($changes['status'])) {
            $this->handleAttendanceChange($attendance, 'updated');
        }
    }

    /**
     * Handle attendance status change and trigger appropriate workflows.
     */
    protected function handleAttendanceChange(ClassAttendance $attendance, string $event): void
    {
        $student = $attendance->student;
        $session = $attendance->session;

        if (! $student) {
            return;
        }

        $context = [
            'attendance_id' => $attendance->id,
            'session_id' => $attendance->session_id,
            'class_id' => $session?->class_id,
            'class_name' => $session?->class?->title,
            'session_date' => $session?->scheduled_date?->toDateString(),
            'status' => $attendance->status,
        ];

        // Determine which trigger to fire based on status
        $triggerType = match ($attendance->status) {
            'present' => 'attendance_present',
            'absent' => 'attendance_absent',
            'late' => 'attendance_late',
            'excused' => 'attendance_excused',
            default => 'attendance_marked',
        };

        // Also trigger generic attendance_marked for any attendance record
        $this->triggerWorkflows('attendance_marked', $student, array_merge($context, [
            'event' => 'attendance_marked',
        ]));

        // Then trigger specific status-based workflow
        if ($triggerType !== 'attendance_marked') {
            $this->triggerWorkflows($triggerType, $student, array_merge($context, [
                'event' => $triggerType,
            ]));
        }
    }

    /**
     * Trigger workflows for a specific trigger type.
     */
    protected function triggerWorkflows(string $triggerType, $student, array $context = []): void
    {
        try {
            $workflows = $this->workflowEngine->findWorkflowsForTrigger($triggerType, [
                'class_id' => $context['class_id'] ?? null,
            ]);

            foreach ($workflows as $workflow) {
                // Check if workflow has specific class condition
                $triggerConfig = $workflow->trigger_config ?? [];
                $requiredClassId = $triggerConfig['class_id'] ?? null;

                // Skip if workflow requires a specific class and this isn't it
                if ($requiredClassId && $requiredClassId != $context['class_id']) {
                    continue;
                }

                // Check for specific status condition
                $requiredStatus = $triggerConfig['status'] ?? null;
                if ($requiredStatus && $requiredStatus !== $context['status']) {
                    continue;
                }

                Log::info("Triggering workflow {$workflow->id} for student {$student->id}", [
                    'trigger_type' => $triggerType,
                    'workflow_name' => $workflow->name,
                    'class_id' => $context['class_id'],
                    'status' => $context['status'],
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
