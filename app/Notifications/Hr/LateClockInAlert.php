<?php

namespace App\Notifications\Hr;

use App\Models\AttendanceLog;

class LateClockInAlert extends BaseHrNotification
{
    public function __construct(
        public AttendanceLog $attendanceLog,
        public int $minutesLate
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Late Clock-In Alert';
    }

    protected function body(): string
    {
        $name = $this->attendanceLog->employee->full_name;

        return "{$name} clocked in {$this->minutesLate} minutes late today.";
    }

    protected function actionUrl(): string
    {
        return '/hr/attendance/records';
    }

    protected function icon(): string
    {
        return 'alert-triangle';
    }
}
