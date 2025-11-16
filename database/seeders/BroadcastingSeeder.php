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
            $status = fake()->randomElement(['draft', 'scheduled', 'sent', 'sent']); // 50% sent
            $broadcast = \App\Models\Broadcast::factory()->create([
                'name' => fake()->words(3, true).' Campaign',
                'type' => 'standard',
                'status' => $status,
                'from_name' => 'Admin Team',
                'from_email' => 'admin@example.com',
                'subject' => fake()->sentence(),
                'content' => fake()->paragraph(3),
                'scheduled_at' => $status === 'scheduled' ? fake()->dateTimeBetween('now', '+7 days') : null,
                'sent_at' => $status === 'sent' ? fake()->dateTimeBetween('-30 days', 'now') : null,
            ]);

            // Attach the audience to the broadcast using the pivot table
            $broadcast->audiences()->attach($audience->id, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $broadcastCount++;
        }

        $this->command->info("✅ Created {$broadcastCount} broadcasts");
        $this->command->info('✨ Broadcasting seeding completed!');
    }
}
