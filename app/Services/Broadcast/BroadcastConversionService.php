<?php

namespace App\Services\Broadcast;

use App\Models\BroadcastConversion;
use App\Models\BroadcastLog;
use App\Models\Order;
use App\Models\ProductOrder;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Attributes a completed purchase back to an email broadcast.
 *
 * A purchase is credited to at most one campaign (last touch). Confidence:
 *  - "clicked"  → the recipient clicked the email, or bought in the same browser
 *                 session that clicked (the bcast_ref cookie).
 *  - "assisted" → a recipient bought within the window without a recorded click.
 *
 * Called from the paid-transition model events so it covers every payment path
 * (Stripe, Bayarcash/FPX, COD, manual) whether sync (buyer request) or async
 * (webhook / queued job). It is always best-effort — never let attribution break
 * a payment flow.
 */
class BroadcastConversionService
{
    /** Days after a send within which a recipient's purchase still counts. */
    private const WINDOW_DAYS = 7;

    public function attributeProductOrder(ProductOrder $order): void
    {
        $student = $order->student
            ?: ($order->customer_id ? Student::query()->where('user_id', $order->customer_id)->first() : null);

        $this->attribute(
            orderType: 'product',
            orderId: (int) $order->id,
            amount: (float) $order->total_amount,
            student: $student,
            convertedAt: $order->paid_time ?? now(),
        );
    }

    public function attributeCourseOrder(Order $order): void
    {
        $this->attribute(
            orderType: 'course',
            orderId: (int) $order->id,
            amount: (float) $order->amount,
            student: $order->student,
            convertedAt: $order->paid_at ?? now(),
        );
    }

    private function attribute(string $orderType, int $orderId, float $amount, ?Student $student, Carbon|string|null $convertedAt): void
    {
        try {
            $convertedAt = $convertedAt ? Carbon::parse($convertedAt) : now();

            // One attribution per order — never double-count revenue.
            if (BroadcastConversion::query()->where('order_type', $orderType)->where('order_id', $orderId)->exists()) {
                return;
            }

            $log = $this->resolveLog($student, $convertedAt);

            if (! $log) {
                return; // buyer wasn't an in-window recipient of any campaign
            }

            BroadcastConversion::create([
                'broadcast_id' => $log->broadcast_id,
                'broadcast_log_id' => $log->id,
                'student_id' => $student?->id,
                'order_type' => $orderType,
                'order_id' => $orderId,
                'amount' => $amount,
                'attribution' => $log->clicked_at ? BroadcastConversion::ATTR_CLICKED : BroadcastConversion::ATTR_ASSISTED,
                'converted_at' => $convertedAt,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Broadcast conversion attribution failed', [
                'order_type' => $orderType,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pick the recipient log to credit: the exact log from the click cookie
     * (same browser session, highest confidence), else the most recent campaign
     * the student was sent within the window — preferring one they clicked.
     */
    private function resolveLog(?Student $student, Carbon $convertedAt): ?BroadcastLog
    {
        // 1. Same-session click cookie (set by the email click redirect).
        $cookieLogId = request()?->cookie('bcast_ref');
        if ($cookieLogId && ($log = BroadcastLog::query()->find((int) $cookieLogId))) {
            return $log;
        }

        if (! $student) {
            return null;
        }

        // 2. Recipient match within the window — clicked campaigns win.
        return BroadcastLog::query()
            ->where('student_id', $student->id)
            ->where('status', 'sent')
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', $convertedAt->copy()->subDays(self::WINDOW_DAYS))
            ->where('sent_at', '<=', $convertedAt)
            ->orderByRaw('clicked_at IS NOT NULL DESC')
            ->orderByDesc('clicked_at')
            ->orderByDesc('sent_at')
            ->first();
    }
}
