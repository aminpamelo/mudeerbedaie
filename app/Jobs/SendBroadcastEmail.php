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

    /**
     * @param  array<int, int>|null  $onlyStudentIds  When set, (re)send to just
     *                                                these recipients — used by the
     *                                                report's per-row / bulk resend.
     */
    public function __construct(public Broadcast $broadcast, public ?array $onlyStudentIds = null)
    {
        //
    }

    public function handle(): void
    {
        $broadcast = $this->broadcast;

        // Targeted resend: (re)send to an explicit subset regardless of their
        // prior outcome, without touching the campaign's overall completion state.
        if ($this->onlyStudentIds !== null) {
            $this->resendTo($broadcast, $this->onlyStudentIds);

            return;
        }

        // Resume-safe: never re-process anyone already delivered or skipped.
        $done = $broadcast->logs()->whereIn('status', ['sent', 'skipped'])->pluck('student_id')->all();
        $recipientIds = array_values(array_diff($broadcast->recipientStudentIds(), $done));

        if (empty($recipientIds)) {
            // Nothing left to send: either an empty audience or a completed resume.
            $this->finalize($broadcast, empty($done) ? 'failed' : 'sent');

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

    /**
     * (Re)send to an explicit subset of the campaign's recipients. Unlike a
     * normal run this ignores the "already sent/skipped" guard so a delivered or
     * failed recipient can be sent again, and it never flips the broadcast's own
     * status — it only refreshes the per-recipient logs and totals.
     *
     * @param  array<int, int>  $studentIds
     */
    private function resendTo(Broadcast $broadcast, array $studentIds): void
    {
        // Constrain to genuine targets so the report can never email someone
        // outside the campaign's audience.
        $ids = array_values(array_intersect(
            array_map('intval', $broadcast->recipientStudentIds()),
            array_map('intval', $studentIds),
        ));

        if (empty($ids)) {
            return;
        }

        Student::whereIn('id', $ids)->with('user')->get()
            ->each(fn (Student $student) => $this->sendToStudent($broadcast, $student));

        $this->syncTotals($broadcast);
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

        if ($this->isUndeliverablePlaceholder($email)) {
            BroadcastLog::updateOrCreate(
                ['broadcast_id' => $broadcast->id, 'student_id' => $student->id],
                ['email' => $email, 'status' => 'skipped', 'error_message' => 'Placeholder email ('.$email.') — not sent'],
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

    /**
     * Reserved example domains never accept mail — they are used as placeholders
     * for imported contacts with no real email, so sending would only bounce.
     */
    private function isUndeliverablePlaceholder(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, '@') ?: '@', 1));

        return in_array($domain, ['example.com', 'example.net', 'example.org'], true);
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
