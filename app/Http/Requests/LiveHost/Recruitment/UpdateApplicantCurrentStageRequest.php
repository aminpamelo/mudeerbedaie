<?php

namespace App\Http\Requests\LiveHost\Recruitment;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicantCurrentStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && in_array($user->role, ['admin', 'admin_livehost', 'livehost_assistant'], true);
    }

    /**
     * The React modal sends `due_at` as an ISO-8601 UTC string (Date.toISOString() → 'YYYY-MM-DDTHH:MM:SS.000Z').
     * The controller updates the row via Query Builder bulk update which bypasses the model's
     * datetime cast, so the raw string would land in MySQL and trigger SQLSTATE[22007]. Convert
     * to the app-timezone Y-m-d H:i:s format here so it is MySQL-safe regardless of write path.
     */
    protected function prepareForValidation(): void
    {
        $dueAt = $this->input('due_at');

        if ($dueAt === null || $dueAt === '') {
            return;
        }

        try {
            $this->merge([
                'due_at' => Carbon::parse($dueAt)
                    ->setTimezone(config('app.timezone'))
                    ->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Leave the original value so the 'date' rule produces a clean validation error.
        }
    }

    public function rules(): array
    {
        return [
            'assignee_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($q) => $q->whereIn('role', ['admin', 'admin_livehost'])
                ),
            ],
            'due_at' => ['nullable', 'date'],
            'stage_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
