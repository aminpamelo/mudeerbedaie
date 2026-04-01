<?php

namespace App\Notifications\Hr;

use App\Models\OfficeExitPermission;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class ExitPermissionRejected extends BaseHrNotification
{
    public function __construct(
        public readonly OfficeExitPermission $permission,
        public readonly User $rejectedByUser,
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push'];
    }

    protected function title(): string
    {
        return 'Exit Permission Rejected';
    }

    protected function body(): string
    {
        return 'Your exit permission '.$this->permission->permission_number.' was rejected.';
    }

    protected function actionUrl(): string
    {
        return '/hr/my/exit-permissions';
    }

    protected function icon(): string
    {
        return 'x-circle';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Exit Permission Has Been Rejected')
            ->greeting('Hello, '.$this->permission->employee->full_name.'!')
            ->line('Unfortunately, your office exit permission request has been rejected.')
            ->line('**Permission No:** '.$this->permission->permission_number)
            ->line('**Date:** '.$this->permission->exit_date->format('d M Y'))
            ->line('**Reason:** '.$this->permission->rejection_reason)
            ->line('Rejected by: '.$this->rejectedByUser->name);
    }
}
