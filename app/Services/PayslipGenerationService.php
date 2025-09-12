<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Payslip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayslipGenerationService
{
    public function generateForTeacher(User $teacher, string $month, User $generatedBy): Payslip
    {
        return DB::transaction(function () use ($teacher, $month, $generatedBy) {
            // Check if payslip already exists
            $existingPayslip = Payslip::forTeacher($teacher->id)
                ->forMonth($month)
                ->first();

            if ($existingPayslip) {
                throw new \Exception("Payslip already exists for {$teacher->name} for {$month}");
            }

            // Create the payslip
            $payslip = Payslip::createForTeacher($teacher, $month, $generatedBy);

            // Get eligible sessions for this teacher and month
            $sessions = $this->getEligibleSessionsForTeacherAndMonth($teacher, $month);

            // Add sessions to payslip
            foreach ($sessions as $session) {
                $payslip->addSession($session);
            }

            return $payslip->fresh();
        });
    }

    public function generateForAllTeachers(string $month, User $generatedBy): Collection
    {
        // Get all teachers who have eligible sessions for this month
        $teachersWithSessions = $this->getTeachersWithEligibleSessionsForMonth($month);

        $generatedPayslips = collect();
        $errors = collect();

        foreach ($teachersWithSessions as $teacher) {
            try {
                $payslip = $this->generateForTeacher($teacher, $month, $generatedBy);
                $generatedPayslips->push($payslip);
            } catch (\Exception $e) {
                $errors->push([
                    'teacher' => $teacher->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return collect([
            'generated' => $generatedPayslips,
            'errors' => $errors,
            'total_teachers' => $teachersWithSessions->count(),
            'successful' => $generatedPayslips->count(),
            'failed' => $errors->count(),
        ]);
    }

    public function generatePayslipsPreview(array $teacherIds, string $month): Collection
    {
        $previews = collect();

        foreach ($teacherIds as $teacherId) {
            $teacher = User::find($teacherId);
            if (! $teacher) {
                continue;
            }

            $sessions = $this->getEligibleSessionsForTeacherAndMonth($teacher, $month);
            $totalAmount = $sessions->sum(fn ($session) => $session->getTeacherAllowanceAmount());

            $previews->push([
                'teacher_id' => $teacher->id,
                'teacher_name' => $teacher->name,
                'total_sessions' => $sessions->count(),
                'total_amount' => $totalAmount,
                'sessions' => $sessions->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'date' => $session->session_date->format('M d, Y'),
                        'time' => $session->session_time->format('g:i A'),
                        'class_title' => $session->class->title,
                        'course_name' => $session->class->course->name,
                        'amount' => $session->getTeacherAllowanceAmount(),
                        'present_count' => $session->present_count,
                        'verified_at' => $session->verified_at?->format('M d, Y g:i A'),
                    ];
                }),
            ]);
        }

        return $previews;
    }

    public function getEligibleSessionsForTeacherAndMonth(User $teacher, string $month): Collection
    {
        $startOfMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endOfMonth = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        return ClassSession::with(['class.course', 'class.teacher.user', 'attendances'])
            ->whereHas('class', function ($query) use ($teacher) {
                $query->where('teacher_id', $teacher->teacher->id);
            })
            ->whereBetween('session_date', [$startOfMonth, $endOfMonth])
            ->eligibleForPayslip()
            ->orderBy('session_date')
            ->orderBy('session_time')
            ->get();
    }

    public function getTeachersWithEligibleSessionsForMonth(string $month): Collection
    {
        $startOfMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endOfMonth = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        return User::whereHas('teacher.classes.sessions', function ($query) use ($startOfMonth, $endOfMonth) {
            $query->whereBetween('session_date', [$startOfMonth, $endOfMonth])
                ->eligibleForPayslip();
        })->get();
    }

    public function getAvailableMonthsForPayslips(): Collection
    {
        // Get months that have completed and verified sessions
        $months = ClassSession::whereNotNull('verified_at')
            ->where('status', 'completed')
            ->whereNotNull('allowance_amount')
            ->selectRaw('DATE_FORMAT(session_date, "%Y-%m") as month')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->pluck('month')
            ->map(function ($month) {
                $carbon = Carbon::createFromFormat('Y-m', $month);

                return [
                    'value' => $month,
                    'label' => $carbon->format('F Y'),
                    'year' => $carbon->year,
                    'month_number' => $carbon->month,
                ];
            });

        return $months;
    }

    public function getPayslipStatisticsForMonth(string $month): array
    {
        $startOfMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endOfMonth = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        $totalEligibleSessions = ClassSession::whereBetween('session_date', [$startOfMonth, $endOfMonth])
            ->eligibleForPayslip()
            ->count();

        $sessionsInPayslips = ClassSession::whereBetween('session_date', [$startOfMonth, $endOfMonth])
            ->includedInPayslip()
            ->count();

        $paidOutSessions = ClassSession::whereBetween('session_date', [$startOfMonth, $endOfMonth])
            ->paidOut()
            ->count();

        $totalAmount = ClassSession::whereBetween('session_date', [$startOfMonth, $endOfMonth])
            ->eligibleForPayslip()
            ->sum('allowance_amount');

        $amountInPayslips = ClassSession::whereBetween('session_date', [$startOfMonth, $endOfMonth])
            ->includedInPayslip()
            ->sum('allowance_amount');

        $paidAmount = ClassSession::whereBetween('session_date', [$startOfMonth, $endOfMonth])
            ->paidOut()
            ->sum('allowance_amount');

        return [
            'total_eligible_sessions' => $totalEligibleSessions,
            'sessions_in_payslips' => $sessionsInPayslips,
            'paid_sessions' => $paidOutSessions,
            'remaining_sessions' => $totalEligibleSessions - $sessionsInPayslips,
            'total_amount' => $totalAmount,
            'amount_in_payslips' => $amountInPayslips,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $totalAmount - $amountInPayslips,
            'payslips_count' => Payslip::forMonth($month)->count(),
            'draft_payslips' => Payslip::forMonth($month)->draft()->count(),
            'finalized_payslips' => Payslip::forMonth($month)->finalized()->count(),
            'paid_payslips' => Payslip::forMonth($month)->paid()->count(),
        ];
    }

    public function canGeneratePayslipForTeacher(User $teacher, string $month): array
    {
        // Check if payslip already exists
        $existingPayslip = Payslip::forTeacher($teacher->id)->forMonth($month)->first();
        if ($existingPayslip) {
            return [
                'can_generate' => false,
                'reason' => 'Payslip already exists for this teacher and month',
                'existing_payslip_id' => $existingPayslip->id,
            ];
        }

        // Check if teacher has eligible sessions
        $eligibleSessions = $this->getEligibleSessionsForTeacherAndMonth($teacher, $month);
        if ($eligibleSessions->isEmpty()) {
            return [
                'can_generate' => false,
                'reason' => 'No eligible sessions found for this teacher and month',
            ];
        }

        return [
            'can_generate' => true,
            'eligible_sessions_count' => $eligibleSessions->count(),
            'total_amount' => $eligibleSessions->sum(fn ($session) => $session->getTeacherAllowanceAmount()),
        ];
    }
}
