<?php

namespace App\Services\Workflow\Actions;

use App\Models\Student;

interface ActionHandlerInterface
{
    /**
     * Execute the action for a student.
     *
     * @param  Student  $student  The student to execute the action for
     * @param  array  $config  The action configuration
     * @param  array  $context  Optional context data (order, funnel, session, etc.) for merge tag resolution
     * @return array{success: bool, message: string, data?: array}
     */
    public function execute(Student $student, array $config, array $context = []): array;
}
