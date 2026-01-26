<?php

namespace App\Services\Workflow\Actions;

use App\Models\MessageTemplate;
use App\Models\Student;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailHandler implements ActionHandlerInterface
{
    public function execute(Student $student, array $config): array
    {
        $templateId = $config['template_id'] ?? null;
        $subject = $config['subject'] ?? null;
        $body = $config['body'] ?? null;

        // Get email from student
        $email = $student->email;
        if (! $email) {
            return [
                'success' => false,
                'message' => 'Student has no email address',
            ];
        }

        try {
            // If using a template
            if ($templateId) {
                $template = MessageTemplate::find($templateId);
                if (! $template) {
                    return [
                        'success' => false,
                        'message' => 'Email template not found',
                    ];
                }
                $subject = $template->subject;
                $body = $template->body;
            }

            if (! $subject || ! $body) {
                return [
                    'success' => false,
                    'message' => 'Email subject and body are required',
                ];
            }

            // Replace placeholders in subject and body
            $subject = $this->replacePlaceholders($subject, $student);
            $body = $this->replacePlaceholders($body, $student);

            // Send the email
            Mail::raw($body, function ($message) use ($email, $subject, $student) {
                $message->to($email, $student->name)
                    ->subject($subject);
            });

            Log::info("Sent email to student {$student->id}", [
                'email' => $email,
                'subject' => $subject,
            ]);

            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'data' => [
                    'email' => $email,
                    'subject' => $subject,
                ],
            ];

        } catch (\Exception $e) {
            Log::error("Failed to send email to student {$student->id}", [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send email: '.$e->getMessage(),
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
