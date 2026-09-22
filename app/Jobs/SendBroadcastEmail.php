<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Models\BroadcastLog;
use App\Models\Student;
use App\Services\Broadcast\BroadcastTrackingInjector;
use App\Services\MergeTag\MergeTagEngine;
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
        $broadcast = $this->broadcast;

        // Resume-safe: never re-send anyone already delivered in a prior run.
        $alreadySent = $broadcast->logs()->where('status', 'sent')->pluck('student_id')->all();
        $recipientIds = array_values(array_diff($broadcast->recipientStudentIds(), $alreadySent));

        if (empty($recipientIds)) {
            // Nothing left to send: either an empty audience or a completed resume.
            $this->finalize($broadcast, empty($alreadySent) ? 'failed' : 'sent');

            return;
        }

        foreach (collect($recipientIds)->chunk(100) as $chunk) {
            // Honour pause/cancel requested from the UI mid-send.
            if (in_array($broadcast->fresh()->status, ['paused', 'cancelled'], true)) {
                $this->syncTotals($broadcast);

                return;
            }

            $students = Student::whereIn('id', $chunk)->with('user')->get();

            foreach ($students as $student) {
                $this->sendToStudent($broadcast, $student);
            }
        }

        $this->finalize($broadcast, 'sent');
    }

    private function sendToStudent(Broadcast $broadcast, Student $student): void
    {
        $email = $student->user?->email;

        if (! $email) {
            BroadcastLog::updateOrCreate(
                ['broadcast_id' => $broadcast->id, 'student_id' => $student->id],
                ['email' => '', 'status' => 'failed', 'error_message' => 'No email address'],
            );

            return;
        }

        $log = BroadcastLog::updateOrCreate(
            ['broadcast_id' => $broadcast->id, 'student_id' => $student->id],
            ['email' => $email, 'status' => 'pending', 'error_message' => null],
        );

        try {
            $content = $this->replaceMergeTags($broadcast->getEffectiveContent(), $student);
            $content = app(BroadcastTrackingInjector::class)->inject($content, $log);
            $subject = $this->replaceMergeTags($broadcast->subject, $student);

            Mail::html($content, function ($message) use ($broadcast, $student, $email, $subject) {
                $message->to($email, $student->user->name)
                    ->subject($subject)
                    ->from($broadcast->from_email, $broadcast->from_name);

                if ($broadcast->reply_to_email) {
                    $message->replyTo($broadcast->reply_to_email);
                }
            });

            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('SendBroadcastEmail: failed to send', [
                'broadcast_id' => $broadcast->id,
                'student_id' => $student->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }
    }

    private function finalize(Broadcast $broadcast, string $completedStatus): void
    {
        // A pause/cancel may have landed while the last chunk was sending.
        if (in_array($broadcast->fresh()->status, ['paused', 'cancelled'], true)) {
            $this->syncTotals($broadcast);

            return;
        }

        $sent = $broadcast->logs()->where('status', 'sent')->count();

        $broadcast->update([
            'status' => $sent > 0 ? $completedStatus : 'failed',
            'total_sent' => $sent,
            'total_failed' => $broadcast->logs()->where('status', 'failed')->count(),
            'sent_at' => now(),
        ]);
    }

    private function syncTotals(Broadcast $broadcast): void
    {
        $broadcast->update([
            'total_sent' => $broadcast->logs()->where('status', 'sent')->count(),
            'total_failed' => $broadcast->logs()->where('status', 'failed')->count(),
        ]);
    }

    private function replaceMergeTags(string $content, Student $student): string
    {
        // Legacy tags kept for broadcasts created with the older simple-tag picker.
        $legacy = [
            '{{name}}' => $student->user?->name ?? '',
            '{{email}}' => $student->user?->email ?? '',
            '{{student_id}}' => $student->student_id ?? '',
        ];
        $content = str_replace(array_keys($legacy), array_values($legacy), $content);

        // Modern tags (e.g. {{contact.first_name}}) advertised by the variable
        // picker and funnel email templates — resolved via MergeTagEngine.
        return (new MergeTagEngine)
            ->setContext(['student' => $student])
            ->resolve($content);
    }
}
