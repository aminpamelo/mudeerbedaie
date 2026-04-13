<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'tiktok_url' => ['nullable', 'string', 'url', 'max:500'],
            'video_url' => ['nullable', 'string', 'url', 'max:500'],
            'stages' => ['nullable', 'array'],
            'stages.*.stage' => ['required', 'in:idea,shooting,editing,posting'],
            'stages.*.due_date' => ['nullable', 'date'],
            'stages.*.assignees' => ['nullable', 'array'],
            'stages.*.assignees.*.employee_id' => ['required', 'exists:employees,id'],
            'stages.*.assignees.*.role' => ['nullable', 'string', 'max:100'],
        ];
    }
}
