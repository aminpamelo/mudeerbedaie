<?php

namespace App\Http\Requests\LiveHost\Recruitment;

use App\Models\LiveHostRecruitmentCampaign;
use App\Services\Recruitment\FormRuleBuilder;
use Illuminate\Foundation\Http\FormRequest;

class ApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $slug = $this->route('slug');
        $campaign = LiveHostRecruitmentCampaign::where('slug', $slug)->firstOrFail();

        $builder = new FormRuleBuilder;
        $schema = $campaign->form_schema ?? [];

        $rules = $builder->build($schema);
        $rules = array_merge($rules, $builder->buildArrayItemRules($schema));

        return $rules;
    }
}
