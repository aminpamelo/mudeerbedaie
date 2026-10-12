<?php

namespace App\Http\Requests\Funnel\FacebookAds;

use App\Models\FacebookAdRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAdRuleRequest extends FormRequest
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
        $isBudget = in_array($this->input('action'), ['increase_budget', 'decrease_budget'], true);

        return [
            'facebook_ad_account_id' => ['required', 'integer', 'exists:facebook_ad_accounts,id'],
            'facebook_ad_strategy_id' => ['nullable', 'integer', 'exists:facebook_ad_strategies,id'],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', Rule::in(FacebookAdRule::LEVELS)],
            'metric' => ['required', Rule::in(FacebookAdRule::METRICS)],
            'operator' => ['required', Rule::in(FacebookAdRule::OPERATORS)],
            'threshold' => ['required', 'numeric', 'min:0'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'lookback_days' => ['required', 'integer', 'min:1', 'max:30'],
            'action' => ['required', Rule::in(FacebookAdRule::ACTIONS), Rule::prohibitedIf($isBudget && $this->input('level') === 'ad')],
            'action_value' => [$isBudget ? 'required' : 'nullable', 'numeric', 'min:1', 'max:100'],
            'max_daily_budget' => [$this->input('action') === 'increase_budget' ? 'required' : 'nullable', 'numeric', 'min:1'],
            'min_daily_budget' => ['nullable', 'numeric', 'min:1'],
            'cooldown_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'is_active' => ['boolean'],
            'dry_run' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.prohibited' => 'Ads have no budget — use a campaign or ad set level for budget rules.',
            'action_value.required' => 'Enter the budget change percentage.',
            'max_daily_budget.required' => 'Set a maximum daily budget so the rule can never scale spend without limit.',
        ];
    }
}
