<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerifyLinkLiveSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin_livehost', 'admin'], true);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'actual_live_record_id' => ['required', 'array', 'min:1'],
            'actual_live_record_id.*' => ['integer', 'distinct', 'exists:actual_live_records,id'],
        ];
    }
}
