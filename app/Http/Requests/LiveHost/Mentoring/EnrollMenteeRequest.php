<?php

namespace App\Http\Requests\LiveHost\Mentoring;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnrollMenteeRequest extends FormRequest
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
            'mentee_user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($q) => $q->whereIn('role', ['live_host', 'livehost_assistant'])),
            ],
            'mentor_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', 'live_host')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mentee_user_id.exists' => 'The mentee must be an existing live host or live host assistant.',
            'mentor_user_id.exists' => 'The mentor must be an existing live host.',
        ];
    }
}
