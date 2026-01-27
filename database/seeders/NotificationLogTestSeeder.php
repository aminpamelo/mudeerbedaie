<?php

namespace Database\Seeders;

use App\Models\ClassModel;
use App\Models\NotificationLog;
use App\Models\ScheduledNotification;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NotificationLogTestSeeder extends Seeder
{
    public function run(): void
    {
        // Get some students and teachers
        $students = Student::with('user')->take(10)->get();
        $teachers = Teacher::with('user')->take(5)->get();
        $classes = ClassModel::take(5)->get();

        if ($students->isEmpty()) {
            $this->command->warn('No students found. Please seed students first.');

            return;
        }

        // Create scheduled notifications for context (optional)
        $scheduledNotification = null;
        if ($classes->isNotEmpty()) {
            $scheduledNotification = ScheduledNotification::create([
                'class_id' => $classes->first()->id,
                'status' => 'sent',
                'scheduled_at' => now()->subHours(2),
                'sent_at' => now()->subHours(1),
                'total_recipients' => 10,
                'total_sent' => 8,
                'total_failed' => 2,
            ]);
        }

        $statuses = ['sent', 'delivered', 'failed', 'pending'];
        $channels = ['email', 'whatsapp', 'sms'];

        // Create notification logs for students
        foreach ($students as $student) {
            $status = fake()->randomElement($statuses);
            $channel = fake()->randomElement($channels);

            $sentAt = $status !== 'pending' ? now()->subDays(fake()->numberBetween(0, 30))->subHours(fake()->numberBetween(1, 23)) : null;
            $openedAt = ($status === 'sent' || $status === 'delivered') && fake()->boolean(60) ? $sentAt?->copy()->addHours(fake()->numberBetween(1, 48)) : null;
            $clickedAt = $openedAt && fake()->boolean(40) ? $openedAt->copy()->addMinutes(fake()->numberBetween(1, 120)) : null;

            NotificationLog::create([
                'scheduled_notification_id' => $scheduledNotification?->id,
                'recipient_type' => 'student',
                'recipient_id' => $student->id,
                'channel' => $channel,
                'destination' => $channel === 'email'
                    ? $student->user->email
                    : ($channel === 'whatsapp' ? '+60123456789' : '+60123456789'),
                'status' => $status,
                'tracking_id' => Str::uuid()->toString(),
                'error_message' => $status === 'failed' ? fake()->randomElement([
                    'Email address not found',
                    'Connection timeout',
                    'Invalid phone number',
                    'Recipient blocked',
                    'Server error',
                ]) : null,
                'sent_at' => $sentAt,
                'delivered_at' => $status === 'delivered' ? $sentAt?->copy()->addMinutes(fake()->numberBetween(1, 30)) : null,
                'opened_at' => $openedAt,
                'open_count' => $openedAt ? fake()->numberBetween(1, 5) : 0,
                'clicked_at' => $clickedAt,
                'click_count' => $clickedAt ? fake()->numberBetween(1, 3) : 0,
                'created_at' => $sentAt ?? now(),
                'updated_at' => $sentAt ?? now(),
            ]);
        }

        // Create notification logs for teachers
        foreach ($teachers as $teacher) {
            $status = fake()->randomElement($statuses);
            $channel = fake()->randomElement(['email', 'whatsapp']);

            $sentAt = $status !== 'pending' ? now()->subDays(fake()->numberBetween(0, 30))->subHours(fake()->numberBetween(1, 23)) : null;
            $openedAt = ($status === 'sent' || $status === 'delivered') && fake()->boolean(70) ? $sentAt?->copy()->addHours(fake()->numberBetween(1, 24)) : null;
            $clickedAt = $openedAt && fake()->boolean(50) ? $openedAt->copy()->addMinutes(fake()->numberBetween(1, 60)) : null;

            NotificationLog::create([
                'scheduled_notification_id' => $scheduledNotification?->id,
                'recipient_type' => 'teacher',
                'recipient_id' => $teacher->id,
                'channel' => $channel,
                'destination' => $channel === 'email'
                    ? $teacher->user->email
                    : '+60198765432',
                'status' => $status,
                'tracking_id' => Str::uuid()->toString(),
                'error_message' => $status === 'failed' ? fake()->randomElement([
                    'Email address not found',
                    'Connection timeout',
                    'Server error',
                ]) : null,
                'sent_at' => $sentAt,
                'delivered_at' => $status === 'delivered' ? $sentAt?->copy()->addMinutes(fake()->numberBetween(1, 15)) : null,
                'opened_at' => $openedAt,
                'open_count' => $openedAt ? fake()->numberBetween(1, 3) : 0,
                'clicked_at' => $clickedAt,
                'click_count' => $clickedAt ? fake()->numberBetween(1, 2) : 0,
                'created_at' => $sentAt ?? now(),
                'updated_at' => $sentAt ?? now(),
            ]);
        }

        // Create some additional random logs for more data
        for ($i = 0; $i < 30; $i++) {
            $isStudent = fake()->boolean(70);
            $recipient = $isStudent ? $students->random() : ($teachers->isNotEmpty() ? $teachers->random() : $students->random());
            $recipientType = $isStudent ? 'student' : 'teacher';

            $status = fake()->randomElement($statuses);
            $channel = fake()->randomElement($channels);

            $sentAt = $status !== 'pending' ? now()->subDays(fake()->numberBetween(0, 60))->subHours(fake()->numberBetween(1, 23)) : null;
            $openedAt = ($status === 'sent' || $status === 'delivered') && fake()->boolean(50) ? $sentAt?->copy()->addHours(fake()->numberBetween(1, 72)) : null;
            $clickedAt = $openedAt && fake()->boolean(30) ? $openedAt->copy()->addMinutes(fake()->numberBetween(1, 180)) : null;

            NotificationLog::create([
                'scheduled_notification_id' => $scheduledNotification?->id,
                'recipient_type' => $recipientType,
                'recipient_id' => $recipient->id,
                'channel' => $channel,
                'destination' => $channel === 'email'
                    ? $recipient->user->email
                    : '+601' . fake()->numerify('########'),
                'status' => $status,
                'tracking_id' => Str::uuid()->toString(),
                'error_message' => $status === 'failed' ? fake()->randomElement([
                    'Email address not found',
                    'Connection timeout',
                    'Invalid phone number',
                    'Recipient blocked',
                    'Server error',
                    'Rate limit exceeded',
                    'Invalid credentials',
                ]) : null,
                'sent_at' => $sentAt,
                'delivered_at' => $status === 'delivered' ? $sentAt?->copy()->addMinutes(fake()->numberBetween(1, 30)) : null,
                'opened_at' => $openedAt,
                'open_count' => $openedAt ? fake()->numberBetween(1, 10) : 0,
                'clicked_at' => $clickedAt,
                'click_count' => $clickedAt ? fake()->numberBetween(1, 5) : 0,
                'created_at' => $sentAt ?? now(),
                'updated_at' => $sentAt ?? now(),
            ]);
        }

        $totalCreated = NotificationLog::count();
        $this->command->info("Created test notification logs. Total: {$totalCreated}");
    }
}
