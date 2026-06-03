<?php

namespace App\Notifications\Hr;

use App\Models\DisciplinaryAction;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

class ShowCauseLetterIssued extends BaseHrNotification
{
    public function __construct(
        public DisciplinaryAction $action,
        public int $lateCount,
        public string $period
    ) {}

    protected function channels(): array
    {
        return ['database', 'push', 'mail'];
    }

    protected function title(): string
    {
        return 'Show Cause Letter Issued';
    }

    protected function body(): string
    {
        return "A show cause letter ({$this->action->reference_number}) has been issued for {$this->lateCount} late arrivals in {$this->period}. Please submit your response before the deadline.";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/disciplinary';
    }

    protected function icon(): string
    {
        return 'alert-triangle';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deadline = $this->action->response_deadline?->format('d/m/Y') ?? 'as advised';

        $message = (new MailMessage)
            ->subject("Show Cause Letter — {$this->action->reference_number}")
            ->greeting('Dear '.$this->action->employee->full_name.',')
            ->line("You have recorded {$this->lateCount} late arrivals in {$this->period}.")
            ->line("In accordance with company policy, a show cause letter ({$this->action->reference_number}) has been issued and is attached to this email.")
            ->line("You are required to submit a written explanation by {$deadline}.")
            ->action('Respond to Show Cause', url('/hr/my/disciplinary'))
            ->line('Please treat this matter with urgency.');

        if ($this->action->letter_pdf_path) {
            $fullPath = Storage::disk('public')->path($this->action->letter_pdf_path);

            if (file_exists($fullPath)) {
                $message->attach($fullPath, [
                    'as' => "show-cause-{$this->action->reference_number}.pdf",
                    'mime' => 'application/pdf',
                ]);
            }
        }

        return $message;
    }
}
