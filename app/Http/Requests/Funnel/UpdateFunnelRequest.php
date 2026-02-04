<?php

namespace App\Http\Requests\Funnel;

use App\Models\Funnel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFunnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $funnel = Funnel::where('uuid', $this->route('uuid'))->first();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('funnels', 'slug')->ignore($funnel?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['sometimes', Rule::in(['sales', 'lead', 'webinar', 'course'])],
            'status' => ['sometimes', Rule::in(['draft', 'published', 'archived'])],
            'settings' => ['nullable', 'array'],
            'settings.meta_title' => ['nullable', 'string', 'max:255'],
            'settings.meta_description' => ['nullable', 'string', 'max:500'],
            'settings.favicon' => ['nullable', 'string', 'max:255'],
            'settings.custom_domain' => ['nullable', 'string', 'max:255'],
            'settings.product_selection_mode' => ['nullable', 'string', 'in:single,multi'],
            'settings.tracking_codes' => ['nullable', 'array'],
            // Pixel tracking settings
            'settings.pixel_settings' => ['nullable', 'array'],
            'settings.pixel_settings.facebook' => ['nullable', 'array'],
            'settings.pixel_settings.facebook.enabled' => ['nullable', 'boolean'],
            'settings.pixel_settings.facebook.pixel_id' => ['nullable', 'string', 'max:50'],
            'settings.pixel_settings.facebook.access_token' => ['nullable', 'string', 'max:500'],
            'settings.pixel_settings.facebook.test_event_code' => ['nullable', 'string', 'max:50'],
            'settings.pixel_settings.facebook.events' => ['nullable', 'array'],
            'settings.pixel_settings.tiktok' => ['nullable', 'array'],
            'show_orders_in_admin' => ['sometimes', 'boolean'],
            'disable_shipping' => ['sometimes', 'boolean'],
            'payment_settings' => ['nullable', 'array'],
            'payment_settings.enabled_methods' => ['nullable', 'array'],
            'payment_settings.enabled_methods.*' => ['string', 'in:stripe,bayarcash_fpx'],
            'payment_settings.default_method' => ['nullable', 'string', 'in:stripe,bayarcash_fpx'],
            'payment_settings.show_method_selector' => ['nullable', 'boolean'],
            'payment_settings.stripe_enabled' => ['nullable', 'boolean'],
            'payment_settings.bayarcash_fpx_enabled' => ['nullable', 'boolean'],
            'payment_settings.custom_labels' => ['nullable', 'array'],
            'payment_settings.custom_labels.stripe' => ['nullable', 'string', 'max:100'],
            'payment_settings.custom_labels.bayarcash_fpx' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Funnel name cannot exceed 255 characters.',
            'slug.unique' => 'This URL slug is already taken.',
            'type.in' => 'Invalid funnel type selected.',
            'status.in' => 'Invalid status selected.',
        ];
    }
}
