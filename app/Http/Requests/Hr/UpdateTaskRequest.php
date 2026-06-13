<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'assigned_to' => ['sometimes', 'exists:employees,id'],
            'assignee_ids' => ['sometimes', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', 'exists:employees,id'],
            'category_id' => ['nullable', 'exists:task_categories,id'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'status' => ['sometimes', 'in:pending,in_progress,completed,cancelled'],
            'deadline' => ['sometimes', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assigned_to.exists' => 'The selected assignee does not exist.',
        ];
    }
}
