<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobPostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'description' => ['required', 'string'],
            'requirements' => ['required', 'string'],
            'employment_type' => ['required', 'in:full_time,part_time,contract,intern'],
            'salary_range_min' => ['nullable', 'numeric', 'min:0'],
            'salary_range_max' => ['nullable', 'numeric', 'min:0', 'gte:salary_range_min'],
            'show_salary' => ['boolean'],
            'vacancies' => ['required', 'integer', 'min:1'],
            'closing_date' => ['nullable', 'date', 'after:today'],
        ];
    }
}
