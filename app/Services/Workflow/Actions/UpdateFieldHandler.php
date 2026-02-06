<?php

namespace App\Services\Workflow\Actions;

use App\Models\Student;
use Illuminate\Support\Facades\Log;

class UpdateFieldHandler implements ActionHandlerInterface
{
    /**
     * Fields that can be updated via workflow.
     */
    protected array $allowedFields = [
        'status',
        'notes',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
    ];

    public function execute(Student $student, array $config): array
    {
        $field = $config['field'] ?? null;
        $value = $config['value'] ?? null;
        $operation = $config['operation'] ?? 'set'; // set, append, prepend

        if (! $field) {
            return [
                'success' => false,
                'message' => 'No field specified',
            ];
        }

        // Check if field is allowed
        if (! in_array($field, $this->allowedFields)) {
            return [
                'success' => false,
                'message' => "Field '{$field}' cannot be updated via workflow",
            ];
        }

        try {
            $oldValue = $student->{$field};

            // Apply the operation
            $newValue = match ($operation) {
                'append' => ($oldValue ?? '').$value,
                'prepend' => $value.($oldValue ?? ''),
                'clear' => null,
                default => $value,
            };

            $student->update([$field => $newValue]);

            Log::info("Updated field {$field} for student {$student->id}", [
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ]);

            return [
                'success' => true,
                'message' => "Field '{$field}' updated successfully",
                'data' => [
                    'field' => $field,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                ],
            ];

        } catch (\Exception $e) {
            Log::error("Failed to update field for student {$student->id}", [
                'field' => $field,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update field: '.$e->getMessage(),
            ];
        }
    }
}
