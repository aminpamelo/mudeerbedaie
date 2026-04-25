<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReplacementRequest;
use App\Models\LiveScheduleAssignment;
use App\Models\SessionReplacementRequest;
use App\Models\User;
use App\Notifications\ReplacementRequestedNotification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ReplacementRequestController extends Controller
{
    public function store(StoreReplacementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $assignment = LiveScheduleAssignment::with('timeSlot')->findOrFail($data['live_schedule_assignment_id']);

        $expiresAt = $this->computeExpiresAt($data, $assignment);

        $replacement = SessionReplacementRequest::create([
            'live_schedule_assignment_id' => $assignment->id,
            'original_host_id' => $request->user()->id,
            'scope' => $data['scope'],
            'target_date' => $data['target_date'] ?? null,
            'reason_category' => $data['reason_category'],
            'reason_note' => $data['reason_note'] ?? null,
            'status' => SessionReplacementRequest::STATUS_PENDING,
            'requested_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        Notification::send(
            User::query()->whereIn('role', ['admin', 'admin_livehost'])->get(),
            new ReplacementRequestedNotification($replacement)
        );

        return redirect()->route('live-host.schedule')
            ->with('success', 'Permohonan ganti telah dihantar.');
    }

    public function destroy(Request $request, SessionReplacementRequest $replacementRequest): RedirectResponse
    {
        abort_unless(
            $replacementRequest->original_host_id === $request->user()->id,
            403,
            'Anda tidak dibenarkan menarik balik permohonan ini.'
        );
        abort_unless(
            $replacementRequest->isPending(),
            422,
            'Permohonan ini tidak lagi boleh ditarik balik.'
        );

        $replacementRequest->update([
            'status' => SessionReplacementRequest::STATUS_WITHDRAWN,
        ]);

        return redirect()->route('live-host.schedule')
            ->with('success', 'Permohonan telah ditarik balik.');
    }

    private function computeExpiresAt(array $data, LiveScheduleAssignment $assignment): Carbon
    {
        if ($data['scope'] === SessionReplacementRequest::SCOPE_ONE_DATE) {
            $startTime = $assignment->timeSlot?->start_time ?? '00:00:00';
            $time = $startTime instanceof Carbon ? $startTime->format('H:i:s') : substr((string) $startTime, 0, 8);

            return Carbon::parse($data['target_date'])->setTimeFromTimeString($time);
        }

        return now()->addHours(24);
    }
}
