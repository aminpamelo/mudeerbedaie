<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformPostStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'views' => ['sometimes', 'integer', 'min:0'],
            'likes' => ['sometimes', 'integer', 'min:0'],
            'comments' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
