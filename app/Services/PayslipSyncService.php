<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Payslip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayslipSyncService
{
    public function syncPayslip(Payslip $payslip): array
    {
        if (! $payslip->canBeEdited()) {
            throw new \Exception('Cannot sync non-draft payslip. Payslip must be in draft status.');
        }

        return DB::transaction(function () use ($payslip) {
            // Get newly eligible sessions for this teacher and month
            $newSessions = $this->getNewEligibleSessionsForPayslip($payslip);

            $addedCount = 0;
            $addedAmount = 0;

            foreach ($newSessions as $session) {
                try {
                    $payslip->addSession($session);
                    $addedCount++;
                    $addedAmount += $session->getTeacherAllowanceAmount();
                } catch (\Exception $e) {
                    // Log the error but continue with other sessions
                    \Log::warning("Failed to add session {$session->id} to payslip {$payslip->id}: ".$e->getMessage());
                }
            }

            return [
                'payslip_id' => $payslip->id,
                'sessions_added' => $addedCount,
                'amount_added' => $addedAmount,
                'new_total_sessions' => $payslip->fresh()->total_sessions,
                'new_total_amount' => $payslip->fresh()->total_amount,
            ];
        });
    }

    public function syncAllDraftPayslipsForMonth(string $month): Collection
    {
        $draftPayslips = Payslip::forMonth($month)->draft()->get();
        $results = collect();

        foreach ($draftPayslips as $payslip) {
            try {
                $result = $this->syncPayslip($payslip);
                $result['teacher_name'] = $payslip->teacher->name;
                $result['status'] = 'success';
                $results->push($result);
            } catch (\Exception $e) {
                $results->push([
                    'payslip_id' => $payslip->id,
                    'teacher_name' => $payslip->teacher->name,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'sessions_added' => 0,
                    'amount_added' => 0,
                ]);
            }
        }

        return $results;
    }

    public function syncPayslipsByTeacherAndMonth(array $teacherIds, string $month): Collection
    {
        $results = collect();

        foreach ($teacherIds as $teacherId) {
            $payslip = Payslip::forTeacher($teacherId)->forMonth($month)->draft()->first();

            if (! $payslip) {
                $teacher = User::find($teacherId);
                $results->push([
                    'teacher_id' => $teacherId,
                    'teacher_name' => $teacher?->name ?? 'Unknown',
                    'status' => 'skipped',
                    'error' => 'No draft payslip found for this teacher and month',
                    'sessions_added' => 0,
                    'amount_added' => 0,
                ]);

                continue;
            }

            try {
                $result = $this->syncPayslip($payslip);
                $result['teacher_name'] = $payslip->teacher->name;
                $result['teacher_id'] = $teacherId;
                $result['status'] = 'success';
                $results->push($result);
            } catch (\Exception $e) {
                $results->push([
                    'teacher_id' => $teacherId,
                    'teacher_name' => $payslip->teacher->name,
                    'payslip_id' => $payslip->id,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'sessions_added' => 0,
                    'amount_added' => 0,
                ]);
            }
        }

        return $results;
    }

    public function getNewEligibleSessionsForPayslip(Payslip $payslip): Collection
    {
        $startOfMonth = Carbon::createFromFormat('Y-m', $payslip->month)->startOfMonth();
        $endOfMonth = Carbon::createFromFormat('Y-m', $payslip->month)->endOfMonth();

        // Get current session IDs in the payslip
        $currentSessionIds = $payslip->payslipSessions()->pluck('session_id')->toArray();

        // Get all eligible sessions for this teacher and month that are NOT already in the payslip
        return ClassSession::with(['class.course', 'attendances'])
            ->whereHas('class', function ($query) use ($payslip) {
                $query->where('teacher_id', $payslip->teacher_id);
            })
            ->whereBetween('session_date', [$startOfMonth, $endOfMonth])
            ->eligibleForPayslip()
            ->whereNotIn('id', $currentSessionIds)
            ->orderBy('session_date')
            ->orderBy('session_time')
            ->get();
    }

    public function getPayslipSyncPreview(Payslip $payslip): array
    {
        $newSessions = $this->getNewEligibleSessionsForPayslip($payslip);

        return [
            'payslip_id' => $payslip->id,
            'teacher_name' => $payslip->teacher->name,
            'month' => $payslip->formatted_month,
            'current_sessions_count' => $payslip->total_sessions,
            'current_amount' => $payslip->total_amount,
            'new_sessions_count' => $newSessions->count(),
            'new_sessions_amount' => $newSessions->sum(fn ($session) => $session->getTeacherAllowanceAmount()),
            'projected_total_sessions' => $payslip->total_sessions + $newSessions->count(),
            'projected_total_amount' => $payslip->total_amount + $newSessions->sum(fn ($session) => $session->getTeacherAllowanceAmount()),
            'new_sessions' => $newSessions->map(function ($session) {
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
        ];
    }

    public function getSyncStatisticsForMonth(string $month): array
    {
        $draftPayslips = Payslip::forMonth($month)->draft()->get();

        $totalPayslips = $draftPayslips->count();
        $payslipsWithNewSessions = 0;
        $totalNewSessions = 0;
        $totalNewAmount = 0;

        foreach ($draftPayslips as $payslip) {
            $newSessions = $this->getNewEligibleSessionsForPayslip($payslip);

            if ($newSessions->count() > 0) {
                $payslipsWithNewSessions++;
                $totalNewSessions += $newSessions->count();
                $totalNewAmount += $newSessions->sum(fn ($session) => $session->getTeacherAllowanceAmount());
            }
        }

        return [
            'month' => $month,
            'total_draft_payslips' => $totalPayslips,
            'payslips_with_new_sessions' => $payslipsWithNewSessions,
            'payslips_without_new_sessions' => $totalPayslips - $payslipsWithNewSessions,
            'total_new_sessions' => $totalNewSessions,
            'total_new_amount' => $totalNewAmount,
            'average_new_sessions_per_payslip' => $totalPayslips > 0 ? round($totalNewSessions / $totalPayslips, 2) : 0,
        ];
    }

    public function removeSessionFromPayslip(Payslip $payslip, ClassSession $session): array
    {
        if (! $payslip->canBeEdited()) {
            throw new \Exception('Cannot modify non-draft payslip.');
        }

        $payslipSession = $payslip->payslipSessions()->where('session_id', $session->id)->first();

        if (! $payslipSession) {
            throw new \Exception('Session is not part of this payslip.');
        }

        $removedAmount = $payslipSession->amount;

        return DB::transaction(function () use ($payslip, $session, $removedAmount) {
            $payslip->removeSession($session);

            return [
                'payslip_id' => $payslip->id,
                'session_id' => $session->id,
                'removed_amount' => $removedAmount,
                'new_total_sessions' => $payslip->fresh()->total_sessions,
                'new_total_amount' => $payslip->fresh()->total_amount,
            ];
        });
    }
}
