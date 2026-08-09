<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user()->student;

        $active = collect();
        $canceled = collect();

        if ($student) {
            $active = Enrollment::where('student_id', $student->id)
                ->with(['course.feeSettings', 'orders' => fn ($q) => $q->latest()->limit(1)])
                ->where(function ($query) {
                    $query->whereIn('subscription_status', ['active', 'trialing', 'past_due'])
                        ->orWhere(function ($q) {
                            $q->whereIn('status', ['enrolled', 'active'])
                                ->whereNotNull('stripe_subscription_id');
                        });
                })
                ->get()
                ->map(fn ($e) => $this->transformEnrollment($e));

            $canceled = Enrollment::where('student_id', $student->id)
                ->with('course.feeSettings')
                ->where('subscription_status', 'canceled')
                ->get()
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'course_name' => $e->course->name,
                    'enrollment_date' => $e->enrollment_date?->format('M j, Y'),
                ]);
        }

        return Inertia::render('Subscriptions', [
            'activeSubscriptions' => $active->values(),
            'canceledSubscriptions' => $canceled->values(),
        ]);
    }

    public function cancel(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($request, $enrollment);

        if (! $enrollment->stripe_subscription_id) {
            return back()->with('error', 'This enrollment does not have an active subscription.');
        }

        try {
            $stripe = app(StripeService::class);
            $stripe->cancelSubscription($enrollment->stripe_subscription_id, false);

            return back()->with('success', 'Your subscription will be canceled at the end of the current billing period.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to cancel subscription: '.$e->getMessage());
        }
    }

    public function resume(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($request, $enrollment);

        if (! $enrollment->stripe_subscription_id || ! $enrollment->isPendingCancellation()) {
            return back()->with('error', 'This subscription is not scheduled for cancellation.');
        }

        try {
            $stripe = app(StripeService::class);
            $result = $stripe->undoCancellation($enrollment->stripe_subscription_id);

            if ($result['success']) {
                $enrollment->updateSubscriptionCancellation(null);

                return back()->with('success', 'Subscription cancellation has been undone.');
            }

            return back()->with('error', 'Failed to resume subscription.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to resume subscription: '.$e->getMessage());
        }
    }

    public function resumeCollection(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($request, $enrollment);

        if (! $enrollment->stripe_subscription_id || ! $enrollment->isCollectionPaused()) {
            return back()->with('error', 'Collection is not currently paused.');
        }

        try {
            $stripe = app(StripeService::class);
            $result = $stripe->resumeSubscriptionCollection($enrollment->stripe_subscription_id);

            if ($result['success']) {
                $enrollment->resumeCollection();

                return back()->with('success', 'Collection has been resumed successfully.');
            }

            return back()->with('error', 'Failed to resume collection.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to resume collection: '.$e->getMessage());
        }
    }

    private function authorizeEnrollment(Request $request, Enrollment $enrollment): void
    {
        if ($enrollment->student_id !== $request->user()->student?->id) {
            abort(403);
        }
    }

    private function transformEnrollment(Enrollment $e): array
    {
        $lastOrder = $e->orders->first();

        return [
            'id' => $e->id,
            'course_name' => $e->course->name,
            'course_description' => $e->course->description ? \Str::limit($e->course->description, 100) : null,
            'is_pending_cancellation' => $e->isPendingCancellation(),
            'is_active' => $e->isSubscriptionActive(),
            'is_collection_paused' => $e->isCollectionPaused(),
            'is_trialing' => $e->isSubscriptionTrialing(),
            'is_past_due' => $e->isSubscriptionPastDue(),
            'status_label' => $e->getSubscriptionStatusLabel(),
            'full_status_description' => $e->getFullStatusDescription(),
            'cancellation_date' => $e->isPendingCancellation() ? $e->getFormattedCancellationDate() : null,
            'collection_paused_date' => $e->isCollectionPaused() ? $e->getFormattedCollectionPausedDate() : null,
            'fee_formatted' => $e->course->feeSettings?->formatted_fee,
            'billing_cycle_label' => $e->course->feeSettings?->billing_cycle_label,
            'enrollment_date' => $e->enrollment_date?->format('M j, Y'),
            'last_order' => $lastOrder ? [
                'date' => $lastOrder->created_at->format('M j, Y'),
                'formatted_amount' => $lastOrder->formatted_amount,
                'is_paid' => $lastOrder->isPaid(),
                'is_failed' => $lastOrder->isFailed(),
            ] : null,
        ];
    }
}
