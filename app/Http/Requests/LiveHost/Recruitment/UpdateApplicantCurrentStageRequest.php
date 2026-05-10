<?php

namespace App\Http\Requests\LiveHost\Recruitment;

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
