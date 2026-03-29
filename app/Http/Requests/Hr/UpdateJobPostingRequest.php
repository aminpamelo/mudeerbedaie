<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJobPostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'department_id' => ['sometimes', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'description' => ['sometimes', 'string'],
            'requirements' => ['sometimes', 'string'],
            'employment_type' => ['sometimes', 'in:full_time,part_time,contract,intern'],
            'salary_range_min' => ['nullable', 'numeric', 'min:0'],
            'salary_range_max' => ['nullable', 'numeric', 'min:0'],
            'show_salary' => ['boolean'],
            'vacancies' => ['sometimes', 'integer', 'min:1'],
            'closing_date' => ['nullable', 'date'],
        ];
    }
}
