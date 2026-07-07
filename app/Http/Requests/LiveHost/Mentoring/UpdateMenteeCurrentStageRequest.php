<?php

namespace App\Http\Requests\LiveHost\Mentoring;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenteeCurrentStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && in_array($user->role, ['admin', 'admin_livehost', 'livehost_assistant'], true);
    }

    /**
     * The React modal sends `due_at` as an ISO-8601 UTC string. The controller
     * writes it via Query Builder bulk update (bypassing the model cast), so we
     * normalise to the app-timezone Y-m-d H:i:s format here to stay MySQL-safe.
     * Mirrors the recruitment UpdateApplicantCurrentStageRequest behaviour.
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
            // Leave the original value so the 'date' rule produces a clean error.
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mentor_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'live_host')),
            ],
            'due_at' => ['nullable', 'date'],
            'stage_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
