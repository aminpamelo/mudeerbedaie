<?php

namespace App\Console\Commands\Hr;

use App\Models\AttendancePenalty;
use App\Models\DisciplinaryAction;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\Hr\ShowCauseAutoIssuedAdminAlert;
use App\Notifications\Hr\ShowCauseLetterIssued;
use App\Services\Hr\DisciplinaryLetterService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class IssueLateShowCause extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:issue-late-show-cause
        {--threshold=3 : Number of late arrivals in the month that triggers a show cause letter}
        {--deadline-days=7 : Days the employee is given to respond}
        {--month= : Target month (1-12), defaults to the current month}
        {--year= : Target year, defaults to the current year}
        {--dry-run : Report who would be issued without creating anything}';

    /**
     * The console command description.
     */
    protected $description = 'Auto-issue show cause letters to employees with too many late arrivals in a month';

    public function handle(DisciplinaryLetterService $letterService): int
    {
        $threshold = max(1, (int) $this->option('threshold'));
        $deadlineDays = max(1, (int) $this->option('deadline-days'));
        $dryRun = (bool) $this->option('dry-run');

        $now = Carbon::now();
        $month = (int) ($this->option('month') ?: $now->month);
        $year = (int) ($this->option('year') ?: $now->year);
        $periodLabel = Carbon::create($year, $month, 1)->format('F Y');
        $sourcePeriod = sprintf('%04d-%02d', $year, $month);

        $employees = Employee::query()
            ->where('status', 'active')
            ->whereHas('attendancePenalties', function ($query) use ($month, $year) {
                $query->where('month', $month)
                    ->where('year', $year)
                    ->where('penalty_type', 'late_arrival');
            }, '>=', $threshold)
            ->with(['user'])
            ->get();

        $issued = 0;
        $skipped = 0;
        $admins = User::where('role', 'admin')->get();

        foreach ($employees as $employee) {
            $alreadyIssued = DisciplinaryAction::query()
                ->where('employee_id', $employee->id)
                ->where('source', DisciplinaryAction::SOURCE_ATTENDANCE_LATE)
                ->where('source_period', $sourcePeriod)
                ->exists();

            if ($alreadyIssued) {
                $skipped++;

                continue;
            }

            $penalties = AttendancePenalty::query()
                ->where('employee_id', $employee->id)
                ->where('month', $month)
                ->where('year', $year)
                ->where('penalty_type', 'late_arrival')
                ->with('attendanceLog:id,date')
                ->get();

            $lateDates = $penalties
                ->map(fn (AttendancePenalty $p) => optional($p->attendanceLog)->date ?? $p->created_at)
                ->filter()
                ->map(fn ($date) => Carbon::parse($date))
                ->sort()
                ->values();

            $lateCount = $penalties->count();
            $incidentDate = $lateDates->get($threshold - 1) ?? $lateDates->last() ?? $now;

            if ($dryRun) {
                $this->line("  [DRY] {$employee->full_name} ({$employee->employee_id}) — {$lateCount} late arrivals");
                $issued++;

                continue;
            }

            $datesList = $lateDates->map(fn (Carbon $d) => $d->format('d/m/Y'))->implode(', ');

            $action = DisciplinaryAction::create([
                'reference_number' => DisciplinaryAction::generateReferenceNumber(),
                'employee_id' => $employee->id,
                'type' => 'show_cause',
                'reason' => "Automated show cause: {$lateCount} late arrivals recorded in {$periodLabel}".
                    ($lateDates->isNotEmpty() ? " on the following dates: {$datesList}." : '.'),
                'incident_date' => $incidentDate->toDateString(),
                'issued_date' => $now->toDateString(),
                'issued_by' => null,
                'response_required' => true,
                'response_deadline' => $now->copy()->addDays($deadlineDays)->toDateString(),
                'status' => 'pending_response',
                'source' => DisciplinaryAction::SOURCE_ATTENDANCE_LATE,
                'source_period' => $sourcePeriod,
            ]);

            try {
                $letterService->generatePdf($action);
            } catch (\RuntimeException $e) {
                $this->warn("  Could not generate letter PDF for {$employee->full_name}: {$e->getMessage()}");
            }

            if ($employee->user) {
                $employee->user->notify(new ShowCauseLetterIssued($action, $lateCount, $periodLabel));
            } else {
                $this->warn("  {$employee->full_name} has no linked user account; in-app/email not sent.");
            }

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new ShowCauseAutoIssuedAdminAlert($action, $employee->full_name, $lateCount, $periodLabel));
            }

            $this->info("  Issued {$action->reference_number} to {$employee->full_name} ({$lateCount} lates).");
            $issued++;
        }

        $verb = $dryRun ? 'Would issue' : 'Issued';
        $this->info("{$verb} {$issued} show cause letter(s) for {$periodLabel}. Skipped {$skipped} already-issued.");

        return self::SUCCESS;
    }
}
