<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $malaysianHolidays = [
            'Hari Raya Aidilfitri',
            'Hari Raya Haji',
            'Chinese New Year',
            'Deepavali',
            'Christmas Day',
            'Thaipusam',
            'Nuzul Al-Quran',
            'Maal Hijrah',
            'Maulidur Rasul',
            'Israk and Mikraj',
            'Malaysia Day',
            'Hari Kebangsaan',
            'Labour Day',
            'Yang di-Pertuan Agong Birthday',
            'Awal Muharram',
        ];

        $type = fake()->randomElement(['national', 'state']);

        return [
            'name' => fake()->randomElement($malaysianHolidays),
            'date' => fake()->dateTimeBetween('first day of January this year', 'last day of December this year'),
            'type' => $type,
            'states' => $type === 'state' ? fake()->randomElements(['selangor', 'kl', 'johor', 'penang', 'perak', 'sabah', 'sarawak'], fake()->numberBetween(1, 3)) : null,
            'year' => now()->year,
            'is_recurring' => fake()->boolean(),
        ];
    }
}
