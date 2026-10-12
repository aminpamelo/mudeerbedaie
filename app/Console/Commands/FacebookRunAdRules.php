<?php

namespace App\Console\Commands;

use App\Models\FacebookAdRule;
use App\Services\Funnel\FacebookAdRuleEngine;
use Illuminate\Console\Command;

class FacebookRunAdRules extends Command
{
    protected $signature = 'facebook:run-ad-rules {--rule= : Run only this rule id (even if inactive)}';

    protected $description = 'Evaluate active Facebook Ads automation rules and pause / activate / re-budget matching objects';

    public function handle(FacebookAdRuleEngine $engine): int
    {
        $rules = FacebookAdRule::query()
            ->when($this->option('rule'), fn ($q, $id) => $q->whereKey($id), fn ($q) => $q->where('is_active', true))
            ->get();

        foreach ($rules as $rule) {
            $result = $engine->run($rule);
            $this->line("#{$rule->id} {$rule->name}: {$result['summary']}");
        }

        $this->info("Evaluated {$rules->count()} rule(s).");

        return self::SUCCESS;
    }
}
