<?php

namespace Database\Factories;

use App\Models\FunnelAutomation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FunnelAutomationAction>
 */
class FunnelAutomationActionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'automation_id' => FunnelAutomation::factory(),
            'action_type' => 'send_email',
            'action_config' => [
                'subject' => 'Test Subject',
                'content' => 'Test Content',
                'email_field' => 'contact.email',
            ],
            'delay_minutes' => 0,
            'sort_order' => 0,
        ];
    }
}
