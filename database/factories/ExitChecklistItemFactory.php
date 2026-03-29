<?php

namespace Database\Factories;

use App\Models\ExitChecklist;
use App\Models\ExitChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExitChecklistItem> */
class ExitChecklistItemFactory extends Factory
{
    protected $model = ExitChecklistItem::class;

    public function definition(): array
    {
        return [
            'exit_checklist_id' => ExitChecklist::factory(),
            'title' => $this->faker->sentence(3),
            'category' => $this->faker->randomElement(['asset_return', 'system_access', 'documentation', 'clearance']),
            'status' => 'pending',
            'sort_order' => $this->faker->numberBetween(1, 20),
        ];
    }
}
