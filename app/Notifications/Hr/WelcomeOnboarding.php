<?php

namespace App\Notifications\Hr;

use App\Models\Employee;
use Illuminate\Notifications\Messages\MailMessage;

class WelcomeOnboarding extends BaseHrNotification
{
    public function __construct(
        public Employee $employee
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail'];
    }

    protected function title(): string
    {
        return 'Welcome to the Team!';
    }

    protected function body(): string
    {
        return "Welcome {$this->employee->full_name}! Your HR portal account is ready. You can manage your attendance, leave, payslips, and more.";
    }

    protected function actionUrl(): string
    {
        return '/hr/clock';
    }

    protected function icon(): string
    {
        return 'user-plus';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to the Team!')
            ->greeting("Hello {$this->employee->full_name}!")
            ->line('Welcome to our company! Your HR portal account has been set up.')
            ->line('Through the portal, you can:')
            ->line('- Clock in and out for attendance')
            ->line('- Apply for leave')
            ->line('- View your payslips')
            ->line('- Submit expense claims')
            ->line('- Manage your profile and documents')
            ->action('Access HR Portal', url('/hr'))
            ->line('If you have any questions, please contact the HR department.');
    }
}
