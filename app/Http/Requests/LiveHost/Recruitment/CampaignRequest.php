<?php

namespace App\Http\Requests\LiveHost\Recruitment;

use App\Models\LiveHostRecruitmentCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
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
        $campaign = $this->route('campaign');
        $campaignId = $campaign instanceof LiveHostRecruitmentCampaign ? $campaign->id : null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'alpha_dash',
                'max:255',
                Rule::unique('live_host_recruitment_campaigns', 'slug')->ignore($campaignId),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,open'],
            'target_count' => ['nullable', 'integer', 'min:1'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
            'form_schema' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Another campaign already uses this URL slug.',
            'slug.alpha_dash' => 'The slug may only contain letters, numbers, dashes, and underscores.',
        ];
    }
}
