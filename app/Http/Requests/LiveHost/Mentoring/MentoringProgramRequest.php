<?php

namespace App\Http\Requests\LiveHost\Mentoring;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MentoringProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && in_array($user->role, ['admin', 'admin_livehost', 'livehost_assistant'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $programId = $this->route('program')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('live_host_mentoring_programs', 'slug')->ignore($programId),
            ],
            'description' => ['nullable', 'string'],
            'leader_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(
                    fn ($q) => $q->whereIn('role', ['live_host', 'admin_livehost', 'livehost_assistant'])
                ),
            ],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'checklist_template' => ['nullable', 'array'],
            'checklist_template.*.title' => ['required', 'string', 'max:255'],
            'checklist_template.*.is_required' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'leader_user_id.exists' => 'The selected leader must be an existing live host or Live Host Desk staff member.',
            'ends_at.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }
}
