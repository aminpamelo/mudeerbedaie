<?php

namespace App\Notifications\Hr;

use App\Models\OfficeExitPermission;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class ExitPermissionApproved extends BaseHrNotification
{
    public function __construct(
        public readonly OfficeExitPermission $permission,
        public readonly User $approvedByUser,
        public readonly bool $isCc = false,
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push'];
    }

    protected function title(): string
    {
        return $this->isCc
            ? '[CC] Exit Permission Approved — '.$this->permission->employee->full_name
            : 'Exit Permission Approved';
    }

    protected function body(): string
    {
        return $this->isCc
            ? 'FYI: Exit permission '.$this->permission->permission_number.' for '.$this->permission->employee->full_name.' has been approved.'
            : 'Your exit permission '.$this->permission->permission_number.' has been approved.';
    }

    protected function actionUrl(): string
    {
        return '/hr/my/exit-permissions';
    }

    protected function icon(): string
    {
        return 'check-circle';
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'exit_permission_approved',
            'permission_id' => $this->permission->id,
            'permission_number' => $this->permission->permission_number,
            'message' => $this->body(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $employee = $this->permission->employee;
        $subject = $this->isCc
            ? '[CC] Exit Permission Approved — '.$employee->full_name
            : 'Your Exit Permission Has Been Approved';

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello'.($this->isCc ? '' : ', '.$employee->full_name).'!')
            ->line($this->isCc
                ? 'FYI: The following exit permission has been approved.'
                : 'Your office exit permission has been approved.')
            ->line('**Permission No:** '.$this->permission->permission_number)
            ->line('**Date:** '.$this->permission->exit_date->format('d M Y'))
            ->line('**Exit Time:** '.$this->permission->exit_time.' → '.$this->permission->return_time)
            ->line('**Type:** '.($this->permission->errand_type === 'company' ? 'Company Business' : 'Personal Business'))
            ->line('**Purpose:** '.$this->permission->purpose)
            ->line('**Approved by:** '.$this->approvedByUser->name);
    }
}
