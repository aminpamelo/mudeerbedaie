<?php

namespace Database\Factories;

use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DisciplinaryInquiry> */
class DisciplinaryInquiryFactory extends Factory
{
    protected $model = DisciplinaryInquiry::class;

    public function definition(): array
    {
        return [
            'disciplinary_action_id' => DisciplinaryAction::factory(),
            'hearing_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'hearing_time' => '10:00',
            'location' => $this->faker->city().' Conference Room',
            'panel_members' => [1, 2, 3],
            'status' => 'scheduled',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'minutes' => $this->faker->paragraphs(3, true),
            'findings' => $this->faker->paragraph(),
            'decision' => $this->faker->randomElement(['guilty', 'not_guilty', 'partially_guilty']),
        ]);
    }
}
