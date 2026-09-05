<?php

namespace Database\Factories;

use App\Models\CekbotSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CekbotSession>
 */
class CekbotSessionFactory extends Factory
{
    protected $model = CekbotSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = $this->faker->unique()->words(2, true);

        return [
            'session_name' => Str::slug($label).'-'.Str::lower(Str::random(4)),
            'label' => Str::title($label),
            'phone_number' => null,
            'status' => CekbotSession::STATUS_SCAN_QR,
            'engine' => 'GOWS',
            'notes' => null,
            'created_by' => null,
            'last_synced_at' => now(),
        ];
    }

    public function working(): static
    {
        return $this->state(fn () => [
            'status' => CekbotSession::STATUS_WORKING,
            'phone_number' => '60'.$this->faker->numerify('#########'),
        ]);
    }
}
