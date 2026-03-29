<?php

namespace Database\Seeders;

use App\Models\RatingScale;
use Illuminate\Database\Seeder;

class HrPerformanceSeeder extends Seeder
{
    public function run(): void
    {
        $scales = [
            ['score' => 1, 'label' => 'Unsatisfactory', 'description' => 'Performance is significantly below expectations', 'color' => '#EF4444'],
            ['score' => 2, 'label' => 'Needs Improvement', 'description' => 'Performance does not consistently meet expectations', 'color' => '#F97316'],
            ['score' => 3, 'label' => 'Meets Expectations', 'description' => 'Performance consistently meets job requirements', 'color' => '#EAB308'],
            ['score' => 4, 'label' => 'Exceeds Expectations', 'description' => 'Performance frequently exceeds job requirements', 'color' => '#22C55E'],
            ['score' => 5, 'label' => 'Outstanding', 'description' => 'Performance is exceptional in all areas', 'color' => '#3B82F6'],
        ];

        foreach ($scales as $scale) {
            RatingScale::updateOrCreate(['score' => $scale['score']], $scale);
        }
    }
}
