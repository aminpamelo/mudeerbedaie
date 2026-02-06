<?php

namespace App\Services\Workflow\Actions;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SendNotificationHandler implements ActionHandlerInterface
{
    public function execute(Student $student, array $config): array
    {
        $title = $config['title'] ?? 'Workflow Notification';
        $message = $config['message'] ?? '';
        $notifyAdmins = $config['notify_admins'] ?? true;
        $specificUserIds = $config['user_ids'] ?? [];

        if (! $message) {
            return [
                'success' => false,
                'message' => 'Notification message is required',
            ];
        }

        try {
            // Replace placeholders
            $title = $this->replacePlaceholders($title, $student);
            $message = $this->replacePlaceholders($message, $student);

            // Get users to notify
            $usersToNotify = collect();

            if (! empty($specificUserIds)) {
                $usersToNotify = User::whereIn('id', $specificUserIds)->get();
            } elseif ($notifyAdmins) {
                $usersToNotify = User::where('role', 'admin')->get();
            }

            if ($usersToNotify->isEmpty()) {
                return [
                    'success' => true,
                    'message' => 'No users to notify',
                ];
            }

            // Send database notifications
            foreach ($usersToNotify as $user) {
                $user->notify(new \App\Notifications\WorkflowNotification(
                    $title,
                    $message,
                    $student
                ));
            }

            Log::info("Sent notification for student {$student->id}", [
                'title' => $title,
                'users_notified' => $usersToNotify->count(),
            ]);

            return [
                'success' => true,
                'message' => "Notification sent to {$usersToNotify->count()} user(s)",
                'data' => [
                    'users_notified' => $usersToNotify->count(),
                ],
            ];

        } catch (\Exception $e) {
            Log::error("Failed to send notification for student {$student->id}", [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send notification: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Replace placeholders in text with student data.
     */
    protected function replacePlaceholders(string $text, Student $student): string
    {
        $placeholders = [
            '{{name}}' => $student->name ?? '',
            '{{first_name}}' => $student->first_name ?? $student->name ?? '',
            '{{email}}' => $student->email ?? '',
            '{{phone}}' => $student->phone ?? '',
            '{{student_id}}' => $student->student_id ?? '',
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $text);
    }
}
