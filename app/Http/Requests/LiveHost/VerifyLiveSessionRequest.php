<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyLiveSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin_livehost', 'admin'], true);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'verification_status' => ['required', Rule::in(['pending', 'verified', 'rejected'])],
            'verification_notes' => ['nullable', 'string', 'max:1000'],
            'gmv_amount_override' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ];
    }
}
