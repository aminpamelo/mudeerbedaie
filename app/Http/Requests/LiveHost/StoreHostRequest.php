<?php

namespace App\Http\Requests\LiveHost;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin_livehost', 'admin'], true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $existingUserId = $this->resolveExistingUserId();

        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($existingUserId)],
            'phone' => ['required', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($existingUserId)],
            'status' => ['required', 'in:active,inactive,suspended'],
        ];
    }

    public function resolveExistingUserId(): ?int
    {
        $userId = $this->integer('user_id');
        if (! $userId) {
            return null;
        }

        return User::where('id', $userId)
            ->where('role', 'live_host')
            ->value('id');
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Another user already has this email address.',
            'phone.unique' => 'Another user already has this phone number.',
        ];
    }
}
