<?php

namespace App\Http\Requests\Funnel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFunnelPixelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'platform' => ['sometimes', 'required', Rule::in(\App\Models\FunnelPixel::PLATFORMS)],
            'group_name' => ['nullable', 'string', 'max:255'],
            'settings' => ['sometimes', 'required', 'array'],

            // Facebook credentials
            'settings.pixel_id' => ['nullable', 'string', 'max:50'],
            'settings.access_token' => ['nullable', 'string', 'max:500'],
            'settings.test_event_code' => ['nullable', 'string', 'max:50'],

            // Google credentials
            'settings.ga4_measurement_id' => ['nullable', 'string', 'max:50'],
            'settings.ads_conversion_id' => ['nullable', 'string', 'max:50'],
            'settings.ads_purchase_label' => ['nullable', 'string', 'max:100'],

            // Default event toggles applied to funnels
            'settings.events' => ['nullable', 'array'],
        ];
    }
}
