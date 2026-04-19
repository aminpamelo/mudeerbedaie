<?php

namespace App\Http\Requests\LiveHost;

use Illuminate\Foundation\Http\FormRequest;

class StoreLiveSessionAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && in_array($user->role, ['admin_livehost', 'admin'], true);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
