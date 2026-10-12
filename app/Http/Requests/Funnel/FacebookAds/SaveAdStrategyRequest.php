<?php

namespace App\Http\Requests\Funnel\FacebookAds;

use App\Models\FacebookAdRule;
use App\Models\FacebookAdStrategy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAdStrategyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'employee'], true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'color' => ['required', Rule::in(FacebookAdStrategy::COLORS)],
            'description' => ['nullable', 'string', 'max:500'],
            'playbook' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['boolean'],

            'launch_preset' => ['nullable', 'array'],
            'launch_preset.objective' => ['nullable', 'in:OUTCOME_SALES,OUTCOME_TRAFFIC'],
            'launch_preset.daily_budget' => ['nullable', 'numeric', 'min:1'],
            'launch_preset.age_min' => ['nullable', 'integer', 'min:18', 'max:65'],
            'launch_preset.age_max' => ['nullable', 'integer', 'min:18', 'max:65'],
            'launch_preset.countries' => ['nullable', 'string', 'max:100'],
            'launch_preset.advantage_audience' => ['nullable', 'boolean'],
            'launch_preset.call_to_action' => ['nullable', Rule::in(LaunchCampaignRequest::CALL_TO_ACTIONS)],

            'rule_presets' => ['nullable', 'array', 'max:10'],
            'rule_presets.*.name' => ['required', 'string', 'max:120'],
            'rule_presets.*.level' => ['required', Rule::in(FacebookAdRule::LEVELS)],
            'rule_presets.*.metric' => ['required', Rule::in(FacebookAdRule::METRICS)],
            'rule_presets.*.operator' => ['required', Rule::in(FacebookAdRule::OPERATORS)],
            'rule_presets.*.threshold' => ['required', 'numeric', 'min:0'],
            'rule_presets.*.min_spend' => ['nullable', 'numeric', 'min:0'],
            'rule_presets.*.lookback_days' => ['required', 'integer', 'min:1', 'max:30'],
            'rule_presets.*.action' => ['required', Rule::in(FacebookAdRule::ACTIONS)],
            'rule_presets.*.action_value' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'rule_presets.*.max_daily_budget' => ['nullable', 'numeric', 'min:1'],
            'rule_presets.*.min_daily_budget' => ['nullable', 'numeric', 'min:1'],
            'rule_presets.*.cooldown_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ];
    }

    /**
     * Budget presets need a % and (for increases) a cap, same as real rules.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('rule_presets', []) as $i => $preset) {
                $action = $preset['action'] ?? null;
                $isBudget = in_array($action, ['increase_budget', 'decrease_budget'], true);

                if ($isBudget && ($preset['level'] ?? null) === 'ad') {
                    $validator->errors()->add("rule_presets.{$i}.level", 'Budget rules must target campaigns or ad sets.');
                }
                if ($isBudget && empty($preset['action_value'])) {
                    $validator->errors()->add("rule_presets.{$i}.action_value", 'Enter the budget change percentage.');
                }
                if ($action === 'increase_budget' && empty($preset['max_daily_budget'])) {
                    $validator->errors()->add("rule_presets.{$i}.max_daily_budget", 'Scale-up rules need a maximum daily budget.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rule_presets.*.name.required' => 'Every bundled rule needs a name.',
        ];
    }
}
