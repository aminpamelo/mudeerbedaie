<?php

namespace App\Notifications\Hr;

use App\Models\OfficeExitPermission;

class ExitPermissionSubmitted extends BaseHrNotification
{
    public function __construct(
        public OfficeExitPermission $permission
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push'];
    }

    protected function title(): string
    {
        return 'New Exit Permission Request';
    }

    protected function body(): string
    {
        $name = $this->permission->employee->full_name;
        $date = $this->permission->date->format('M j, Y');
        $type = ucfirst($this->permission->type);

        return "{$name} requested {$type} exit permission on {$date}.";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/approvals/exit-permissions';
    }

    protected function icon(): string
    {
        return 'arrow-right-start-on-rectangle';
    }
}
