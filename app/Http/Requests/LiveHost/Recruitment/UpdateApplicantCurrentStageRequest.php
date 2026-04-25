<?php

namespace App\Http\Requests\LiveHost\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicantCurrentStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->isLiveHostAssistant() === false;
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
