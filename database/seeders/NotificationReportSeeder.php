<?php

namespace Database\Seeders;

use App\Models\Broadcast;
use App\Models\BroadcastLog;
use App\Models\ClassModel;
use App\Models\ClassNotificationSetting;
use App\Models\NotificationLog;
use App\Models\ScheduledNotification;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NotificationReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📊 Seeding notification report data...');

        $students = Student::with('user')->get();
        $teachers = Teacher::with('user')->get();
        $classes = ClassModel::all();

        if ($students->isEmpty()) {
            $this->command->warn('⚠️  No students found. Skipping notification seeding.');

            return;
        }

        // Create class notification settings if not exist
        $this->command->info('Creating class notification settings...');
        $settings = $this->createNotificationSettings($classes);

        // Create scheduled notifications with logs
        $this->command->info('Creating scheduled notifications...');
        $this->createScheduledNotifications($settings, $students, $teachers);

        // Create broadcast logs with tracking data
        $this->command->info('Creating broadcast logs with tracking...');
        $this->createBroadcastLogs($students);

        $this->command->info('✨ Notification report seeding completed!');
    }

    private function createNotificationSettings($classes): array
    {
        $settings = [];
        $notificationTypes = ['reminder_24h', 'reminder_3h', 'followup_immediate'];

        foreach ($classes->take(5) as $class) {
            foreach ($notificationTypes as $type) {
                $existing = ClassNotificationSetting::where('class_id', $class->id)
                    ->where('notification_type', $type)
                    ->first();

                if (!$existing) {
                    $settings[] = ClassNotificationSetting::create([
                        'class_id' => $class->id,
                        'notification_type' => $type,
                        'is_enabled' => true,
                        'send_to_students' => true,
                        'send_to_teacher' => $type === 'reminder_24h',
                        'custom_subject' => 'Peringatan Kelas: ' . $class->title,
                        'custom_content' => 'Salam, ini adalah peringatan untuk kelas anda.',
                        'whatsapp_enabled' => fake()->boolean(70),
                    ]);
                } else {
                    $settings[] = $existing;
                }
            }
        }

        $this->command->info('✅ Created/found ' . count($settings) . ' notification settings');

        return $settings;
    }

    private function createScheduledNotifications(array $settings, $students, $teachers): void
    {
        $count = 0;
        $logCount = 0;

        // Generate notifications over the last 60 days
        foreach ($settings as $setting) {
            // Create 3-8 scheduled notifications per setting
            $notificationCount = rand(3, 8);

            for ($i = 0; $i < $notificationCount; $i++) {
                $scheduledAt = fake()->dateTimeBetween('-60 days', 'now');
                $status = $this->getRandomStatus();
                $sentAt = in_array($status, ['sent', 'failed']) ? Carbon::parse($scheduledAt)->addMinutes(rand(1, 30)) : null;

                $scheduledNotification = ScheduledNotification::create([
                    'class_id' => $setting->class_id,
                    'session_id' => null,
                    'scheduled_session_date' => Carbon::parse($scheduledAt)->format('Y-m-d'),
                    'scheduled_session_time' => fake()->randomElement(['09:00:00', '14:00:00', '19:00:00', '20:30:00']),
                    'class_notification_setting_id' => $setting->id,
                    'status' => $status,
                    'scheduled_at' => $scheduledAt,
                    'sent_at' => $sentAt,
                    'total_recipients' => 0,
                    'total_sent' => 0,
                    'total_failed' => 0,
                    'failure_reason' => $status === 'failed' ? fake()->randomElement([
                        'SMTP connection timeout',
                        'Invalid email address',
                        'Rate limit exceeded',
                        'Template not found',
                    ]) : null,
                ]);

                // Create notification logs for sent notifications
                if (in_array($status, ['sent', 'failed'])) {
                    $logCount += $this->createNotificationLogs($scheduledNotification, $students, $teachers, $sentAt);
                }

                $count++;
            }
        }

        $this->command->info("✅ Created {$count} scheduled notifications with {$logCount} logs");
    }

    private function createNotificationLogs($scheduledNotification, $students, $teachers, $sentAt): int
    {
        $logCount = 0;
        $recipientCount = rand(5, 20);
        $selectedStudents = $students->random(min($recipientCount, $students->count()));

        $totalSent = 0;
        $totalFailed = 0;

        foreach ($selectedStudents as $student) {
            // Create email log
            $emailLog = $this->createSingleLog(
                $scheduledNotification->id,
                'student',
                $student->id,
                'email',
                $student->user->email ?? fake()->email(),
                $sentAt
            );
            $logCount++;

            if ($emailLog['status'] === 'sent') {
                $totalSent++;
            } else {
                $totalFailed++;
            }

            // Optionally create WhatsApp log (50% chance)
            if (fake()->boolean(50)) {
                $waLog = $this->createSingleLog(
                    $scheduledNotification->id,
                    'student',
                    $student->id,
                    'whatsapp',
                    $student->phone ?? fake()->phoneNumber(),
                    $sentAt
                );
                $logCount++;

                if ($waLog['status'] === 'sent') {
                    $totalSent++;
                } else {
                    $totalFailed++;
                }
            }
        }

        // Optionally add teacher notification
        if ($teachers->isNotEmpty() && fake()->boolean(30)) {
            $teacher = $teachers->random();
            $this->createSingleLog(
                $scheduledNotification->id,
                'teacher',
                $teacher->id,
                'email',
                $teacher->user->email ?? fake()->email(),
                $sentAt
            );
            $logCount++;
            $totalSent++;
        }

        // Update scheduled notification stats
        $scheduledNotification->update([
            'total_recipients' => $logCount,
            'total_sent' => $totalSent,
            'total_failed' => $totalFailed,
        ]);

        return $logCount;
    }

    private function createSingleLog(
        int $scheduledNotificationId,
        string $recipientType,
        int $recipientId,
        string $channel,
        string $destination,
        $sentAt
    ): array {
        $status = $this->getRandomLogStatus();
        $sentAtCarbon = Carbon::parse($sentAt);

        // Calculate open/click data
        $wasOpened = $status === 'sent' && fake()->boolean(45); // 45% open rate
        $wasClicked = $wasOpened && fake()->boolean(30); // 30% of opened emails clicked

        $openedAt = $wasOpened ? $sentAtCarbon->copy()->addMinutes(rand(5, 1440)) : null; // opened within 24h
        $clickedAt = $wasClicked ? $openedAt->copy()->addMinutes(rand(1, 60)) : null;
        $openCount = $wasOpened ? rand(1, 5) : 0;
        $clickCount = $wasClicked ? rand(1, 3) : 0;

        $errorMessages = [
            'Connection refused',
            'Invalid recipient address',
            'Mailbox full',
            'Message rejected',
            'Rate limit exceeded',
            'Timeout error',
        ];

        NotificationLog::create([
            'scheduled_notification_id' => $scheduledNotificationId,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'channel' => $channel,
            'destination' => $destination,
            'status' => $status,
            'message_id' => $status === 'sent' ? Str::uuid()->toString() : null,
            'tracking_id' => Str::uuid()->toString(),
            'error_message' => $status === 'failed' ? fake()->randomElement($errorMessages) : null,
            'sent_at' => $status === 'sent' ? $sentAtCarbon : null,
            'delivered_at' => $status === 'sent' && fake()->boolean(80) ? $sentAtCarbon->copy()->addSeconds(rand(1, 60)) : null,
            'opened_at' => $openedAt,
            'open_count' => $openCount,
            'clicked_at' => $clickedAt,
            'click_count' => $clickCount,
            'created_at' => $sentAtCarbon->copy()->subMinutes(rand(1, 5)),
            'updated_at' => $openedAt ?? $sentAtCarbon,
        ]);

        return ['status' => $status];
    }

    private function createBroadcastLogs($students): void
    {
        $broadcasts = Broadcast::where('status', 'sent')->get();

        if ($broadcasts->isEmpty()) {
            // Create sample broadcasts if none exist
            $broadcasts = $this->createSampleBroadcasts();
        }

        $logCount = 0;

        foreach ($broadcasts as $broadcast) {
            $recipientCount = rand(10, min(50, $students->count()));
            $selectedStudents = $students->random($recipientCount);

            $totalSent = 0;
            $totalFailed = 0;
            $totalOpened = 0;

            foreach ($selectedStudents as $student) {
                $status = fake()->boolean(92) ? 'sent' : 'failed'; // 92% success rate
                $sentAt = $broadcast->sent_at ?? Carbon::parse($broadcast->created_at);

                $wasOpened = $status === 'sent' && fake()->boolean(35); // 35% open rate
                $wasClicked = $wasOpened && fake()->boolean(25); // 25% of opened emails clicked

                $openedAt = $wasOpened ? Carbon::parse($sentAt)->addMinutes(rand(10, 2880)) : null;
                $clickedAt = $wasClicked ? $openedAt->copy()->addMinutes(rand(1, 120)) : null;

                BroadcastLog::create([
                    'broadcast_id' => $broadcast->id,
                    'student_id' => $student->id,
                    'email' => $student->user->email ?? fake()->email(),
                    'status' => $status,
                    'error_message' => $status === 'failed' ? fake()->randomElement([
                        'Email bounced',
                        'Invalid address',
                        'Mailbox not found',
                    ]) : null,
                    'tracking_id' => Str::uuid()->toString(),
                    'sent_at' => $status === 'sent' ? $sentAt : null,
                    'opened_at' => $openedAt,
                    'open_count' => $wasOpened ? rand(1, 4) : 0,
                    'clicked_at' => $clickedAt,
                    'click_count' => $wasClicked ? rand(1, 2) : 0,
                    'created_at' => $sentAt,
                    'updated_at' => $openedAt ?? $sentAt,
                ]);

                $logCount++;

                if ($status === 'sent') {
                    $totalSent++;
                    if ($wasOpened) {
                        $totalOpened++;
                    }
                } else {
                    $totalFailed++;
                }
            }

            // Update broadcast stats
            $broadcast->update([
                'total_recipients' => $recipientCount,
                'total_sent' => $totalSent,
                'total_failed' => $totalFailed,
            ]);
        }

        $this->command->info("✅ Created {$logCount} broadcast logs");
    }

    private function createSampleBroadcasts(): \Illuminate\Support\Collection
    {
        $broadcasts = collect();

        $subjects = [
            'Pengumuman Penting: Jadual Kelas Baharu',
            'Promosi Khas Akhir Tahun',
            'Peringatan: Pembayaran Yuran',
            'Jemputan Majlis Tahunan',
            'Kemas Kini Sistem Pembelajaran',
        ];

        foreach ($subjects as $index => $subject) {
            $sentAt = fake()->dateTimeBetween('-45 days', '-5 days');

            $broadcast = Broadcast::create([
                'name' => 'Broadcast ' . ($index + 1),
                'type' => 'standard',
                'status' => 'sent',
                'from_name' => 'Admin BeDaie',
                'from_email' => 'admin@example.com',
                'subject' => $subject,
                'content' => fake()->paragraphs(2, true),
                'scheduled_at' => null,
                'sent_at' => $sentAt,
                'total_recipients' => 0,
                'total_sent' => 0,
                'total_failed' => 0,
            ]);

            $broadcasts->push($broadcast);
        }

        return $broadcasts;
    }

    private function getRandomStatus(): string
    {
        $statuses = ['sent', 'sent', 'sent', 'sent', 'failed', 'pending', 'cancelled'];

        return fake()->randomElement($statuses);
    }

    private function getRandomLogStatus(): string
    {
        // 88% sent, 8% failed, 4% pending
        $rand = rand(1, 100);
        if ($rand <= 88) {
            return 'sent';
        }
        if ($rand <= 96) {
            return 'failed';
        }

        return 'pending';
    }
}
