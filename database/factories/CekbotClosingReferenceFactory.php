<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\CekbotClosingReference>
 */
class CekbotClosingReferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Closing '.fake()->words(3, true),
            'transcript' => "Pelanggan: Berapa harga?\nSales: Harga RM49 sahaja kak, ramai dah rasa manfaatnya.\nPelanggan: Ok saya ambil.",
            'notes' => fake()->sentence(),
            'flow_ids' => null,
            'is_active' => true,
        ];
    }
}
