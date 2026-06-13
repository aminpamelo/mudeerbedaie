<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // A task may be assigned to one person (`assigned_to`, legacy/HR) or
            // co-owned by several (`assignee_ids`); at least one is required.
            'assigned_to' => ['required_without:assignee_ids', 'nullable', 'exists:employees,id'],
            'assignee_ids' => ['required_without:assigned_to', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', 'exists:employees,id'],
            'category_id' => ['nullable', 'exists:task_categories,id'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'deadline' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assigned_to.exists' => 'The selected assignee does not exist.',
            'deadline.after_or_equal' => 'Deadline must be today or in the future.',
        ];
    }
}
