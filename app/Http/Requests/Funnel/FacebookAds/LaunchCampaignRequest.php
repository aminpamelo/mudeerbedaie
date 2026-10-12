<?php

namespace App\Http\Requests\Funnel\FacebookAds;

use Illuminate\Foundation\Http\FormRequest;

class LaunchCampaignRequest extends FormRequest
{
    public const CALL_TO_ACTIONS = ['SHOP_NOW', 'LEARN_MORE', 'SIGN_UP', 'ORDER_NOW', 'BUY_NOW', 'GET_OFFER', 'CONTACT_US', 'SUBSCRIBE'];

    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'employee'], true);
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'objective' => ['required', 'in:OUTCOME_SALES,OUTCOME_TRAFFIC'],
            'funnel_id' => ['nullable', 'integer', 'exists:funnels,id'],
            'facebook_ad_strategy_id' => ['nullable', 'integer', 'exists:facebook_ad_strategies,id'],
            'link' => ['required', 'url', 'max:2000'],
            'daily_budget' => ['required', 'numeric', 'min:1', 'max:100000'],
            'page_id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'pixel_id' => ['required_if:objective,OUTCOME_SALES', 'nullable', 'string', 'regex:/^\d{5,30}$/'],
            'countries' => ['required', 'array', 'min:1'],
            'countries.*' => ['string', 'size:2'],
            'age_min' => ['required', 'integer', 'min:18', 'max:65'],
            'age_max' => ['required', 'integer', 'min:18', 'max:65', 'gte:age_min'],
            'advantage_audience' => ['boolean'],
            'primary_text' => ['required', 'string', 'max:2000'],
            'headline' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'call_to_action' => ['required', 'in:'.implode(',', self::CALL_TO_ACTIONS)],
            'image_hash' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pixel_id.required_if' => 'Sales campaigns need a pixel to optimise for purchases.',
            'image_hash.required' => 'Upload an image for the ad first.',
            'age_max.gte' => 'Maximum age must be at least the minimum age.',
        ];
    }
}
