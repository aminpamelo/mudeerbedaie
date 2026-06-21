<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateHostRequest extends FormRequest
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
        $host = $this->route('host');
        $hostId = is_object($host) ? $host->id : $host;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($hostId)],
            'phone' => ['required', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($hostId)],
            'status' => ['required', 'in:active,inactive,suspended'],
            'role' => ['sometimes', 'in:live_host,livehost_assistant'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
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
            'role.in' => 'You can only assign the Live Host or Live Host Assistant role here.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }
}
