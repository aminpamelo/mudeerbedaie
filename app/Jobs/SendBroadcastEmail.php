<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Models\BroadcastLog;
use App\Models\Student;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBroadcastEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(public Broadcast $broadcast)
    {
        //
    }

    public function handle(): void
    {
        // Get all unique recipients from selected audiences
        $recipients = $this->broadcast->recipients;

        if ($recipients->isEmpty()) {
            $this->broadcast->update([
                'status' => 'failed',
                'total_failed' => $this->broadcast->total_recipients,
            ]);

            return;
        }

        $totalSent = 0;
        $totalFailed = 0;

        // Send emails in chunks to avoid memory issues
        $recipients->chunk(100)->each(function ($chunk) use (&$totalSent, &$totalFailed) {
            foreach ($chunk as $student) {
                $email = $student->user?->email;

                // Skip students with no email address — the logs table requires one.
                if (! $email) {
                    $totalFailed++;
                    Log::warning('SendBroadcastEmail: skipping student with no email', [
                        'broadcast_id' => $this->broadcast->id,
                        'student_id' => $student->id,
                    ]);

                    continue;
                }

                $log = null;

                try {
                    $log = BroadcastLog::create([
                        'broadcast_id' => $this->broadcast->id,
                        'student_id' => $student->id,
                        'email' => $email,
                        'status' => 'pending',
                    ]);

                    $content = $this->replaceMergeTags($this->broadcast->getEffectiveContent(), $student);
                    $subject = $this->replaceMergeTags($this->broadcast->subject, $student);

                    Mail::html($content, function ($message) use ($student, $email, $subject) {
                        $message->to($email, $student->user->name)
                            ->subject($subject)
                            ->from($this->broadcast->from_email, $this->broadcast->from_name);

                        if ($this->broadcast->reply_to_email) {
                            $message->replyTo($this->broadcast->reply_to_email);
                        }
                    });

                    $log->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);

                    $totalSent++;
                } catch (\Throwable $e) {
                    $totalFailed++;

                    Log::error('SendBroadcastEmail: failed to send', [
                        'broadcast_id' => $this->broadcast->id,
                        'student_id' => $student->id,
                        'email' => $email,
                        'log_created' => (bool) $log,
                        'error' => $e->getMessage(),
                    ]);

                    if ($log) {
                        $log->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                        ]);
                    } else {
                        // Log row creation itself failed — record the failure separately so it's still visible in UI.
                        BroadcastLog::create([
                            'broadcast_id' => $this->broadcast->id,
                            'student_id' => $student->id,
                            'email' => $email,
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                        ]);
                    }
                }
            }
        });

        // Update broadcast stats
        $this->broadcast->update([
            'status' => $totalFailed === 0 ? 'sent' : ($totalSent > 0 ? 'sent' : 'failed'),
            'total_sent' => $totalSent,
            'total_failed' => $totalFailed,
            'sent_at' => now(),
        ]);
    }

    private function replaceMergeTags(string $content, Student $student): string
    {
        $replacements = [
            '{{name}}' => $student->user->name,
            '{{email}}' => $student->user->email,
            '{{student_id}}' => $student->student_id,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}
