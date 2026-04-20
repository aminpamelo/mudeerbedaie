<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Foundation\Http\FormRequest;

class StoreLiveHostPayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin_livehost', 'admin'], true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after:period_start'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_start.required' => 'The payroll period start date is required.',
            'period_start.date_format' => 'The payroll period start date must be in YYYY-MM-DD format.',
            'period_end.required' => 'The payroll period end date is required.',
            'period_end.date_format' => 'The payroll period end date must be in YYYY-MM-DD format.',
            'period_end.after' => 'The payroll period end date must be after the period start date.',
        ];
    }
}
