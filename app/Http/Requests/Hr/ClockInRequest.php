<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class ClockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'photo' => ['required_without:is_wfh', 'image', 'max:2048'],
            'is_wfh' => ['boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];

        // Location is required for WFH clock-in
        if ($this->boolean('is_wfh')) {
            $rules['latitude'] = ['required', 'numeric', 'between:-90,90'];
            $rules['longitude'] = ['required', 'numeric', 'between:-180,180'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required_without' => 'A photo is required when not working from home.',
            'photo.max' => 'Photo must not exceed 2MB.',
            'latitude.required' => 'Location is required for WFH clock-in. Please enable GPS.',
            'longitude.required' => 'Location is required for WFH clock-in. Please enable GPS.',
        ];
    }
}
