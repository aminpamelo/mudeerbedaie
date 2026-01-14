<?php

namespace Database\Seeders;

use App\Models\LiveTimeSlot;
use Illuminate\Database\Seeder;

class LiveTimeSlotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timeSlots = [
            ['start_time' => '06:30:00', 'end_time' => '08:30:00', 'sort_order' => 1],
            ['start_time' => '08:30:00', 'end_time' => '10:30:00', 'sort_order' => 2],
            ['start_time' => '10:30:00', 'end_time' => '12:30:00', 'sort_order' => 3],
            ['start_time' => '12:30:00', 'end_time' => '14:30:00', 'sort_order' => 4],
            ['start_time' => '14:30:00', 'end_time' => '16:30:00', 'sort_order' => 5],
            ['start_time' => '17:00:00', 'end_time' => '19:00:00', 'sort_order' => 6],
            ['start_time' => '20:00:00', 'end_time' => '22:00:00', 'sort_order' => 7],
            ['start_time' => '22:00:00', 'end_time' => '00:00:00', 'sort_order' => 8],
        ];

        foreach ($timeSlots as $slot) {
            LiveTimeSlot::updateOrCreate(
                [
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                ],
                [
                    'is_active' => true,
                    'sort_order' => $slot['sort_order'],
                ]
            );
        }
    }
}
