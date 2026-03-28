<?php

namespace App\Console\Commands\Hr;

use App\Models\EmployeeDocument;
use App\Models\User;
use App\Notifications\Hr\DocumentExpiring;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckExpiringDocuments extends Command
{
    protected $signature = 'hr:check-expiring-documents';

    protected $description = 'Notify about documents expiring within 30 days';

    public function handle(): int
    {
        $warningDate = Carbon::now()->addDays(30)->toDateString();

        $documents = EmployeeDocument::query()
            ->with(['employee.user'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', $warningDate)
            ->whereDate('expiry_date', '>=', Carbon::today())
            ->get();

        $admins = User::where('role', 'admin')->get();
        $count = 0;

        foreach ($documents as $document) {
            $daysLeft = (int) Carbon::now()->diffInDays($document->expiry_date, false);

            // Notify the employee
            if ($document->employee?->user) {
                $document->employee->user->notify(new DocumentExpiring($document, max(0, $daysLeft)));
            }

            // Also notify admins
            foreach ($admins as $admin) {
                $admin->notify(new DocumentExpiring($document, max(0, $daysLeft)));
            }

            $count++;
        }

        $this->info("Sent expiring document alerts for {$count} documents.");

        return self::SUCCESS;
    }
}
