<?php

namespace App\Console\Commands\Hr;

use App\Models\ClaimApprover;
use App\Models\ClaimRequest;
use App\Notifications\Hr\ClaimPendingReminder;
use Illuminate\Console\Command;

class RemindPendingClaims extends Command
{
    protected $signature = 'hr:remind-pending-claims';

    protected $description = 'Remind approvers about pending claims';

    public function handle(): int
    {
        $pendingCount = ClaimRequest::where('status', 'pending')->count();

        if ($pendingCount === 0) {
            $this->info('No pending claims.');

            return self::SUCCESS;
        }

        // Get unique approvers
        $approvers = ClaimApprover::query()
            ->with('approver.user')
            ->get()
            ->pluck('approver.user')
            ->filter()
            ->unique('id');

        $count = 0;
        foreach ($approvers as $user) {
            $user->notify(new ClaimPendingReminder($pendingCount));
            $count++;
        }

        $this->info("Sent pending claim reminders to {$count} approvers.");

        return self::SUCCESS;
    }
}
