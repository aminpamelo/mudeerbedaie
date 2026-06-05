<?php

namespace App\Notifications\LiveHost;

use App\Models\LiveScheduleAssignment;
use Carbon\Carbon;

/**
 * Fired when the PIC assigns a host to a dated session slot or changes the
 * time / creator account / platform / status of a slot the host is already on
 * (see SessionSlotController). Push-only by design: PIC slot edits are frequent
 * and we don't want to fill the database `notifications` table with churn.
 */
class ScheduleSlotChangedNotification extends BasePocketNotification
{
    private const DAY_NAMES = ['Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu'];

    public function __construct(
        public LiveScheduleAssignment $assignment,
        public string $action = 'updated',
    ) {}

    protected function channels(): array
    {
        return ['push'];
    }

    protected function title(): string
    {
        return $this->action === 'assigned' ? 'Slot baharu ditetapkan' : 'Jadual dikemas kini';
    }

    protected function body(): string
    {
        $assignment = $this->assignment->loadMissing(['timeSlot', 'liveAccount', 'platformAccount']);

        $start = substr((string) ($assignment->timeSlot?->start_time ?? ''), 0, 5);
        $end = substr((string) ($assignment->timeSlot?->end_time ?? ''), 0, 5);
        $time = $start !== '' && $end !== '' ? "{$start}–{$end}" : '';

        $when = $assignment->schedule_date
            ? Carbon::parse($assignment->schedule_date)->format('j M')
            : (self::DAY_NAMES[(int) $assignment->day_of_week] ?? '');

        $account = $assignment->liveAccount?->nickname
            ?: $assignment->liveAccount?->display_name
            ?: $assignment->platformAccount?->name;

        $slot = trim("{$when} {$time}");

        return $account ? trim("{$account} · {$slot}") : $slot;
    }

    protected function actionUrl(): string
    {
        return route('live-host.schedule');
    }
}
