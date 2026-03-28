<?php

namespace App\Console\Commands\Hr;

use App\Models\ClaimRequest;
use App\Notifications\Hr\ClaimExpiringSoon;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckExpiringClaims extends Command
{
    protected $signature = 'hr:check-expiring-claims';

    protected $description = 'Notify employees about claims with expiring receipts';

    public function handle(): int
    {
        $warningDate = Carbon::now()->addDays(7)->toDateString();

        $claims = ClaimRequest::query()
            ->with(['employee.user', 'claimType'])
            ->where('status', 'draft')
            ->whereNotNull('receipt_date')
            ->whereDate('receipt_date', '<=', $warningDate)
            ->get();

        $count = 0;
        foreach ($claims as $claim) {
            if ($claim->employee?->user && $claim->receipt_date) {
                $daysLeft = Carbon::now()->diffInDays(Carbon::parse($claim->receipt_date)->addMonths(3), false);
                if ($daysLeft > 0 && $daysLeft <= 7) {
                    $claim->employee->user->notify(new ClaimExpiringSoon($claim, (int) $daysLeft));
                    $count++;
                }
            }
        }

        $this->info("Sent {$count} expiring claim notifications.");

        return self::SUCCESS;
    }
}
