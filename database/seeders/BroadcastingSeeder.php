<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BroadcastingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📢 Seeding broadcasting data...');

        $students = \App\Models\Student::all();

        if ($students->isEmpty()) {
            $this->command->warn('⚠️  No students found. Skipping broadcasting seeding.');

            return;
        }

        // Create audiences
        $this->command->info('Creating audiences...');

        $audienceNames = [
            'All Students',
            'Active Enrollments',
            'Quran Memorization Students',
            'Arabic Language Students',
            'New Students (Last 30 Days)',
        ];

        $audiences = collect();
        foreach ($audienceNames as $name) {
            $audience = \App\Models\Audience::factory()->create([
                'name' => $name,
                'description' => 'Audience for '.$name,
                'status' => 'active',
            ]);

            // Add random students to each audience
            $studentsToAdd = $students->random(min(rand(5, 20), $students->count()));
            foreach ($studentsToAdd as $student) {
                $audience->students()->attach($student->id, [
                    'subscribed_at' => now()->subDays(rand(1, 90)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $audiences->push($audience);
        }

        $this->command->info('✅ Created 5 audiences with student subscriptions');

        // Create broadcasts
        $this->command->info('Creating broadcasts...');

        $broadcastCount = 0;
        foreach ($audiences->random(min(3, $audiences->count())) as $audience) {
            \App\Models\Broadcast::factory()->create([
                'audience_id' => $audience->id,
                'subject' => fake()->sentence(),
                'message' => fake()->paragraph(3),
                'status' => fake()->randomElement(['draft', 'scheduled', 'sent', 'sent']), // 50% sent
                'scheduled_at' => fake()->optional()->dateTimeBetween('now', '+7 days'),
            ]);
            $broadcastCount++;
        }

        $this->command->info("✅ Created {$broadcastCount} broadcasts");
        $this->command->info('✨ Broadcasting seeding completed!');
    }
}
