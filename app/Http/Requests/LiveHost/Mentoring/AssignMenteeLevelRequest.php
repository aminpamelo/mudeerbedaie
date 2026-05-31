<?php

namespace App\Http\Requests\LiveHost\Mentoring;

use Illuminate\Foundation\Http\FormRequest;

class AssignMenteeLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && in_array($user->role, ['admin', 'admin_livehost'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'level_id' => ['nullable', 'integer', 'exists:live_host_mentoring_levels,id'],
            'source' => ['nullable', 'in:manual,auto'],
        ];
    }
}
