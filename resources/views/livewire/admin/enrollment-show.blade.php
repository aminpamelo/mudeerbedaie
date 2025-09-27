<?php

use App\Models\Enrollment;
use App\Services\StripeService;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Enrollment $enrollment;

    public $subscriptionEvents;

    // Manual payment properties
    public $showManualPaymentModal = false;

    public $showCreateManualOrderModal = false;

    public $showApprovalModal = false;

    public $selectedOrderForApproval = null;

    public $paymentDate = '';

    public $receiptFile = null;

    // Schedule management properties
    public $showScheduleModal = false;

    public $scheduleForm = [
        'billing_cycle_anchor' => null,
        'trial_end_at' => null,
        'subscription_timezone' => 'Asia/Kuala_Lumpur',
        'proration_behavior' => 'create_prorations',
        'next_payment_date' => null,
        'next_payment_time' => '07:23',
        'end_date' => null,
        'end_time' => null,
        'subscription_fee' => null,
    ];

    // Subscription creation properties
    public $showCreateSubscriptionModal = false;

    public $createForm = [
        'trial_end_at' => null,
        'billing_cycle_anchor' => null,
        'subscription_timezone' => 'Asia/Kuala_Lumpur',
        'proration_behavior' => 'create_prorations',
        'start_date' => null,
        'start_time' => '07:23',
        'next_payment_date' => null,
        'next_payment_time' => '07:23',
        'end_date' => null,
        'end_time' => null,
        'payment_method_id' => null,
        'notes' => null,
        'subscription_fee' => null,
    ];

    public function mount(): void
    {
        $this->enrollment->load(['student.user', 'student.classStudents.class', 'course.feeSettings', 'course.classes', 'enrolledBy', 'orders']);

        // Load subscription events
        $this->refreshSubscriptionEvents();

        // Refresh subscription status from Stripe if there's a subscription
        if ($this->enrollment->stripe_subscription_id) {
            $this->refreshSubscriptionStatus();
        }

        // Initialize schedule form
        $this->initializeScheduleForm();

        // Initialize create form
        $this->initializeCreateForm();
    }

    protected function initializeScheduleForm(): void
    {
        // Use the same reliable calculation as the main view
        $nextPaymentDate = null;
        $nextPaymentTime = '07:23';

        // Get next payment date from enrollment model (same as main view)
        $nextPaymentCarbon = $this->enrollment->getNextPaymentDate();
        if ($nextPaymentCarbon) {
            $nextPaymentDate = $nextPaymentCarbon->format('Y-m-d');
            $nextPaymentTime = $nextPaymentCarbon->format('H:i');
        }

        if ($this->enrollment->stripe_subscription_id) {
            try {
                $stripeService = app(StripeService::class);
                $schedule = $stripeService->getDetailedSubscriptionSchedule($this->enrollment->stripe_subscription_id);

                $this->scheduleForm = [
                    'billing_cycle_anchor' => $schedule['billing_cycle_anchor'] ? date('Y-m-d', $schedule['billing_cycle_anchor']) : null,
                    'trial_end_at' => ($schedule['trial_end'] && $schedule['trial_end'] > time()) ? date('Y-m-d', $schedule['trial_end']) : null,
                    'subscription_timezone' => $this->enrollment->getSubscriptionTimezone(),
                    'proration_behavior' => $this->enrollment->proration_behavior ?? 'create_prorations',
                    'next_payment_date' => $nextPaymentDate,
                    'next_payment_time' => $nextPaymentTime,
                    'end_date' => $schedule['cancel_at'] ? date('Y-m-d', $schedule['cancel_at']) : null,
                    'end_time' => $schedule['cancel_at'] ? date('H:i', $schedule['cancel_at']) : null,
                    'subscription_fee' => $this->enrollment->enrollment_fee,
                ];
            } catch (\Exception $e) {
                // Fallback to basic form with next payment date only
                $this->scheduleForm = [
                    'billing_cycle_anchor' => null,
                    'trial_end_at' => null,
                    'subscription_timezone' => $this->enrollment->getSubscriptionTimezone(),
                    'proration_behavior' => $this->enrollment->proration_behavior ?? 'create_prorations',
                    'next_payment_date' => $nextPaymentDate,
                    'next_payment_time' => $nextPaymentTime,
                    'end_date' => null,
                    'end_time' => null,
                    'subscription_fee' => $this->enrollment->enrollment_fee,
                ];

                \Log::warning('Could not load subscription schedule details, using fallback', [
                    'enrollment_id' => $this->enrollment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            // No subscription, just set basic form
            $this->scheduleForm = [
                'billing_cycle_anchor' => null,
                'trial_end_at' => null,
                'subscription_timezone' => 'Asia/Kuala_Lumpur',
                'proration_behavior' => 'create_prorations',
                'next_payment_date' => $nextPaymentDate,
                'next_payment_time' => $nextPaymentTime,
                'end_date' => null,
                'end_time' => null,
                'subscription_fee' => $this->enrollment->enrollment_fee,
            ];
        }
    }

    protected function initializeCreateForm(): void
    {
        // Get student's available payment methods
        $defaultPaymentMethod = $this->enrollment->student->user->paymentMethods()
            ->active()
            ->default()
            ->first();

        // Calculate default start date based on course billing day
        $defaultStartDate = $this->calculateDefaultStartDate();

        $this->createForm = [
            'trial_end_at' => null,
            'billing_cycle_anchor' => null,
            'subscription_timezone' => 'Asia/Kuala_Lumpur',
            'proration_behavior' => 'create_prorations',
            'start_date' => $defaultStartDate->format('Y-m-d'),
            'start_time' => '07:23',
            'next_payment_date' => null,
            'next_payment_time' => '07:23',
            'end_date' => null,
            'end_time' => null,
            'payment_method_id' => $defaultPaymentMethod?->id,
            'notes' => null,
            'subscription_fee' => $this->enrollment->enrollment_fee,
        ];
    }

    /**
     * Calculate the default start date based on course billing day
     */
    private function calculateDefaultStartDate(): \Carbon\Carbon
    {
        $feeSettings = $this->enrollment->course->feeSettings;

        if (! $feeSettings || ! $feeSettings->hasBillingDay()) {
            // No billing day set, default to today
            return now()->startOfDay();
        }

        $billingDay = $feeSettings->getValidatedBillingDay();
        $now = now();
        $currentDay = $now->day;

        try {
            // Determine if we should use this month or next month
            if ($currentDay < $billingDay) {
                // Billing day hasn't occurred this month, use this month
                $targetDate = $now->copy();
            } else {
                // Billing day has passed this month, use next month
                $targetDate = $now->copy()->addMonth();
            }

            // Check days in target month before setting the day
            $daysInTargetMonth = $targetDate->daysInMonth;

            if ($billingDay > $daysInTargetMonth) {
                // Use the last day of the month if billing day exceeds the month's days
                $defaultDate = $targetDate->endOfMonth()->startOfDay();
            } else {
                // Set the specific billing day
                $defaultDate = $targetDate->day($billingDay)->startOfDay();
            }

            \Log::info('Calculated default start date from course billing day', [
                'course_id' => $this->enrollment->course->id,
                'billing_day' => $billingDay,
                'current_date' => $now->toDateString(),
                'calculated_start_date' => $defaultDate->toDateString(),
            ]);

            return $defaultDate;

        } catch (\Exception $e) {
            \Log::warning('Failed to calculate start date from billing day, using today', [
                'course_id' => $this->enrollment->course->id,
                'billing_day' => $billingDay,
                'error' => $e->getMessage(),
            ]);

            return now()->startOfDay();
        }
    }

    protected function rules(): array
    {
        return [
            'scheduleForm.billing_cycle_anchor' => 'nullable|date',
            'scheduleForm.trial_end_at' => 'nullable|date|after_or_equal:today',
            'scheduleForm.subscription_timezone' => 'required|string|max:50',
            'scheduleForm.proration_behavior' => 'required|in:create_prorations,none,always_invoice',
            'scheduleForm.next_payment_date' => 'nullable|date|after_or_equal:today',
            'scheduleForm.next_payment_time' => 'nullable|date_format:H:i',
            'scheduleForm.end_date' => 'nullable|date|after:today',
            'scheduleForm.end_time' => 'nullable|date_format:H:i',
            'scheduleForm.subscription_fee' => 'nullable|numeric|min:0.01|max:9999999.99',
            // Creation form rules
            'createForm.trial_end_at' => 'nullable|date|after_or_equal:today',
            'createForm.billing_cycle_anchor' => 'nullable|date|after_or_equal:today',
            'createForm.subscription_timezone' => 'required|string|max:50',
            'createForm.proration_behavior' => 'required|in:create_prorations,none,always_invoice',
            'createForm.start_date' => 'nullable|date|after_or_equal:today',
            'createForm.start_time' => 'nullable|date_format:H:i',
            'createForm.next_payment_date' => 'nullable|date|after_or_equal:today',
            'createForm.next_payment_time' => 'nullable|date_format:H:i',
            'createForm.end_date' => 'nullable|date|after:today',
            'createForm.end_time' => 'nullable|date_format:H:i',
            'createForm.payment_method_id' => 'required|exists:payment_methods,id',
            'createForm.notes' => 'nullable|string|max:1000',
            'createForm.subscription_fee' => 'nullable|numeric|min:0.01|max:9999999.99',
        ];
    }

    public function refreshSubscriptionEvents(): void
    {
        $this->subscriptionEvents = $this->enrollment->getSubscriptionEvents();
    }

    public function refreshSubscriptionStatus(): void
    {
        try {
            $stripeService = app(StripeService::class);
            $details = $stripeService->getSubscriptionDetails($this->enrollment->stripe_subscription_id);

            // Update subscription status if needed
            if ($details['status'] !== $this->enrollment->subscription_status) {
                $this->enrollment->updateSubscriptionStatus($details['status']);
            }

            // Update cancellation timestamp based on Stripe data
            $cancelAt = null;
            if ($details['cancel_at_period_end'] && $details['cancel_at']) {
                $cancelAt = \Carbon\Carbon::createFromTimestamp($details['cancel_at']);
            }

            // Only update if the cancellation timestamp has changed
            $currentCancelAt = $this->enrollment->subscription_cancel_at;
            if (($currentCancelAt && ! $cancelAt) ||
                (! $currentCancelAt && $cancelAt) ||
                ($currentCancelAt && $cancelAt && ! $currentCancelAt->equalTo($cancelAt))) {
                $this->enrollment->updateSubscriptionCancellation($cancelAt);
            }

            $this->enrollment->refresh();

        } catch (\Exception $e) {
            \Log::error('Failed to refresh subscription status from Stripe', [
                'enrollment_id' => $this->enrollment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getPaymentReportData()
    {
        if (! $this->enrollment->stripe_subscription_id) {
            return collect([]);
        }

        // Use enrollment start date as the foundation for billing periods
        if (! $this->enrollment->start_date) {
            return collect([]);
        }

        // Get all paid and failed orders ordered by period
        $orders = $this->enrollment->orders()
            ->whereNotNull('period_start')
            ->whereNotNull('period_end')
            ->orderBy('period_start', 'asc')
            ->get();

        // Get subscription interval from course fee settings
        $billingCycle = $this->enrollment->course->feeSettings->billing_cycle ?? 'monthly';
        $interval = str_contains($billingCycle, 'month') ? 'month' : (str_contains($billingCycle, 'year') ? 'year' : 'month');
        $intervalCount = 1; // Default to 1 for standard monthly/yearly cycles

        // Generate all expected payment periods from enrollment start to appropriate end date
        $paymentPeriods = collect([]);
        $startDate = $this->enrollment->start_date->copy();

        // Determine appropriate end date for subscription billing periods
        $endDate = $this->enrollment->subscription_cancel_at ?? now();

        // For subscription scenarios, extend beyond course end date if needed
        if ($this->enrollment->end_date && $this->enrollment->end_date->lt($endDate)) {
            // If this is an ongoing subscription, extend beyond course end date
            $endDate = now()->addMonths(6); // Show future periods for active subscriptions
        }

        $currentPeriodStart = $startDate->copy();
        $maxPeriods = 60; // Limit to prevent infinite loops
        $periodCount = 0;

        while ($currentPeriodStart->lte($endDate) && $periodCount < $maxPeriods) {
            // Calculate period end based on interval
            $currentPeriodEnd = $currentPeriodStart->copy();
            if ($interval === 'month') {
                $currentPeriodEnd->addMonths($intervalCount)->subDay();
            } elseif ($interval === 'year') {
                $currentPeriodEnd->addYears($intervalCount)->subDay();
            } else {
                $currentPeriodEnd->addDays($intervalCount * 30)->subDay(); // Fallback
            }

            // Find matching order for this period
            $matchingOrder = $orders->first(function ($order) use ($currentPeriodStart, $currentPeriodEnd) {
                return $order->period_start->equalTo($currentPeriodStart) ||
                       ($order->period_start->gte($currentPeriodStart) && $order->period_start->lte($currentPeriodEnd));
            });

            // Determine period status
            $status = 'unpaid';
            $amount = $this->enrollment->enrollment_fee ?? 0;
            $order = null;

            if ($matchingOrder) {
                $order = $matchingOrder;
                $status = $matchingOrder->status;
                $amount = $matchingOrder->amount;
            } elseif ($currentPeriodEnd->isFuture()) {
                $status = 'upcoming';
            }

            // Format period name based on interval
            $periodName = $this->formatPeriodName($currentPeriodStart, $currentPeriodEnd, $interval);

            $paymentPeriods->push([
                'period_name' => $periodName,
                'period_start' => $currentPeriodStart->copy(),
                'period_end' => $currentPeriodEnd->copy(),
                'status' => $status,
                'amount' => $amount,
                'order' => $order,
                'is_current' => $currentPeriodStart->lte(now()) && $currentPeriodEnd->gte(now()),
                'is_future' => $currentPeriodStart->isFuture(),
            ]);

            // Move to next period
            $currentPeriodStart = $currentPeriodEnd->copy()->addDay();
            $periodCount++;
        }

        return $paymentPeriods;
    }

    private function formatPeriodName($startDate, $endDate, $interval)
    {
        if ($interval === 'year') {
            return $startDate->format('Y');
        } elseif ($interval === 'month') {
            return $startDate->format('M Y');
        } else {
            return $startDate->format('M d').' - '.$endDate->format('M d, Y');
        }
    }

    public function syncSubscriptionData(): void
    {
        try {
            if (! $this->enrollment->stripe_subscription_id) {
                session()->flash('error', 'No subscription ID found to sync with Stripe.');

                return;
            }

            $stripeService = app(StripeService::class);

            // Get comprehensive subscription details from Stripe
            $details = $stripeService->getSubscriptionDetails($this->enrollment->stripe_subscription_id);

            \Log::info('Syncing subscription data from Stripe', [
                'enrollment_id' => $this->enrollment->id,
                'subscription_id' => $this->enrollment->stripe_subscription_id,
                'stripe_status' => $details['status'] ?? 'unknown',
            ]);

            // Update subscription status
            if (isset($details['status']) && $details['status'] !== $this->enrollment->subscription_status) {
                $this->enrollment->updateSubscriptionStatus($details['status']);
            }

            // Sync collection status if available
            if (isset($details['pause_collection'])) {
                $pauseCollection = $details['pause_collection'];

                if ($pauseCollection && isset($pauseCollection['behavior']) && $pauseCollection['behavior'] === 'void') {
                    // Collection is paused
                    if (! $this->enrollment->isCollectionPaused()) {
                        $this->enrollment->pauseCollection();
                    }
                } else {
                    // Collection is active
                    if ($this->enrollment->isCollectionPaused()) {
                        $this->enrollment->resumeCollection();
                    }
                }
            }

            // Update next payment date
            if (in_array($details['status'] ?? '', ['active', 'trialing']) && isset($details['current_period_end'])) {
                $nextPaymentDate = \Carbon\Carbon::createFromTimestamp($details['current_period_end'])->addDay();
                $this->enrollment->updateNextPaymentDate($nextPaymentDate);
            } elseif (in_array($details['status'] ?? '', ['canceled', 'incomplete_expired', 'past_due', 'unpaid'])) {
                $this->enrollment->updateNextPaymentDate(null);
            }

            // Update cancellation information
            $cancelAt = null;
            if ($details['cancel_at_period_end'] && $details['cancel_at']) {
                $cancelAt = \Carbon\Carbon::createFromTimestamp($details['cancel_at']);
            }

            $currentCancelAt = $this->enrollment->subscription_cancel_at;
            if (($currentCancelAt && ! $cancelAt) ||
                (! $currentCancelAt && $cancelAt) ||
                ($currentCancelAt && $cancelAt && ! $currentCancelAt->equalTo($cancelAt))) {
                $this->enrollment->updateSubscriptionCancellation($cancelAt);
            }

            // Update trial information if available
            if (isset($details['trial_end']) && $details['trial_end']) {
                $trialEnd = \Carbon\Carbon::createFromTimestamp($details['trial_end']);
                // Update trial end if different
                if (! $this->enrollment->trial_end_at || ! $this->enrollment->trial_end_at->equalTo($trialEnd)) {
                    $this->enrollment->update(['trial_end_at' => $trialEnd]);
                }
            } elseif (isset($details['trial_end']) && ! $details['trial_end'] && $this->enrollment->trial_end_at) {
                // Clear trial end if no longer in trial
                $this->enrollment->update(['trial_end_at' => null]);
            }

            // Refresh enrollment data
            $this->enrollment->refresh();

            // Refresh subscription events
            $this->refreshSubscriptionEvents();

            session()->flash('success', 'Subscription data synchronized successfully from Stripe!');

            \Log::info('Subscription data synced successfully', [
                'enrollment_id' => $this->enrollment->id,
                'subscription_id' => $this->enrollment->stripe_subscription_id,
                'new_status' => $this->enrollment->subscription_status,
                'collection_status' => $this->enrollment->collection_status ?? 'active',
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to sync subscription data from Stripe', [
                'enrollment_id' => $this->enrollment->id,
                'subscription_id' => $this->enrollment->stripe_subscription_id,
                'error' => $e->getMessage(),
            ]);

            session()->flash('error', 'Failed to sync subscription data: '.$e->getMessage());
        }
    }

    public function createSubscription()
    {
        \Log::info('Create subscription button clicked', [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => auth()->user()->id,
        ]);

        try {
            \Log::info('Checking course fee settings', [
                'enrollment_id' => $this->enrollment->id,
                'has_fee_settings' => (bool) $this->enrollment->course->feeSettings,
            ]);

            if (! $this->enrollment->course->feeSettings) {
                \Log::warning('Course missing fee settings', ['enrollment_id' => $this->enrollment->id]);
                session()->flash('error', 'Course must have fee settings configured first.');

                return;
            }

            \Log::info('Checking Stripe price ID', [
                'enrollment_id' => $this->enrollment->id,
                'stripe_price_id' => $this->enrollment->course->feeSettings->stripe_price_id,
            ]);

            if (! $this->enrollment->course->feeSettings->stripe_price_id) {
                \Log::warning('Course missing Stripe price ID', ['enrollment_id' => $this->enrollment->id]);
                session()->flash('error', 'Course must be synced with Stripe first. Go to Course Edit page and click"Sync to Stripe".');

                return;
            }

            // Get student's default payment method
            \Log::info('Looking for student payment methods', [
                'enrollment_id' => $this->enrollment->id,
                'student_id' => $this->enrollment->student->id,
                'user_id' => $this->enrollment->student->user->id,
            ]);

            $paymentMethod = $this->enrollment->student->user->paymentMethods()
                ->active()
                ->default()
                ->first();

            \Log::info('Payment method check result', [
                'enrollment_id' => $this->enrollment->id,
                'payment_method_found' => (bool) $paymentMethod,
                'payment_method_id' => $paymentMethod?->id,
            ]);

            if (! $paymentMethod) {
                \Log::warning('Student has no default payment method', ['enrollment_id' => $this->enrollment->id]);
                session()->flash('warning', 'Student must add a payment method first. You can add one for them or direct them to their Payment Methods page.');

                return;
            }

            // Create subscription using StripeService
            \Log::info('Creating Stripe subscription', [
                'enrollment_id' => $this->enrollment->id,
                'payment_method_id' => $paymentMethod->id,
            ]);

            $stripeService = app(StripeService::class);
            $result = $stripeService->createSubscription($this->enrollment, $paymentMethod);

            \Log::info('Stripe subscription creation result', [
                'enrollment_id' => $this->enrollment->id,
                'subscription_id' => $result['subscription']->id ?? null,
            ]);

            // Refresh enrollment to get updated subscription data
            $this->enrollment->refresh();

            \Log::info('Subscription created successfully', [
                'enrollment_id' => $this->enrollment->id,
                'stripe_subscription_id' => $this->enrollment->stripe_subscription_id,
            ]);

            session()->flash('success', 'Subscription created successfully! The student will be charged according to the billing cycle.');

        } catch (\Exception $e) {
            \Log::error('Failed to create subscription', [
                'enrollment_id' => $this->enrollment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            session()->flash('error', 'Failed to create subscription: '.$e->getMessage());
        }
    }

    public function createSubscriptionWithOptions()
    {
        \Log::info('Create subscription with options submitted', [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => auth()->user()->id,
            'form_data' => $this->createForm,
        ]);

        $this->validate();

        try {
            // Validate course settings
            if (! $this->enrollment->course->feeSettings || ! $this->enrollment->course->feeSettings->stripe_price_id) {
                session()->flash('error', 'Course must have fee settings configured and synced with Stripe.');

                return;
            }

            // Get selected payment method
            $paymentMethod = $this->enrollment->student->user->paymentMethods()
                ->active()
                ->find($this->createForm['payment_method_id']);

            if (! $paymentMethod) {
                session()->flash('error', 'Selected payment method not found or inactive.');

                return;
            }

            $stripeService = app(StripeService::class);

            // Handle custom subscription fee if provided
            if (isset($this->createForm['subscription_fee']) &&
                $this->createForm['subscription_fee'] !== null &&
                $this->createForm['subscription_fee'] != $this->enrollment->enrollment_fee) {

                // Update the enrollment fee before creating subscription
                $this->enrollment->update(['enrollment_fee' => (float) $this->createForm['subscription_fee']]);
                $this->enrollment->refresh();
            }

            // Use the enhanced createSubscriptionWithOptions method that handles start dates properly
            $options = [
                'start_date' => $this->createForm['start_date'],
                'start_time' => $this->createForm['start_time'],
                'next_payment_date' => $this->createForm['next_payment_date'],
                'next_payment_time' => $this->createForm['next_payment_time'],
                'trial_end_at' => $this->createForm['trial_end_at'],
                'billing_cycle_anchor' => $this->createForm['billing_cycle_anchor'],
                'proration_behavior' => $this->createForm['proration_behavior'],
                'end_date' => $this->createForm['end_date'],
                'end_time' => $this->createForm['end_time'],
                'timezone' => $this->createForm['subscription_timezone'],
            ];

            $result = $stripeService->createSubscriptionWithOptions($this->enrollment, $paymentMethod, $options);

            // Refresh enrollment to get the new subscription ID and updated status
            $this->enrollment->refresh();

            // Update enrollment with next payment date if it was calculated by the service
            if (isset($result['start_date']) && $result['start_date']) {
                $nextPaymentDate = $result['start_date'];
                if ($this->createForm['next_payment_date'] && $this->createForm['next_payment_date'] !== $this->createForm['start_date']) {
                    // Next payment date was explicitly set differently from start date
                    $nextPaymentTime = $this->createForm['next_payment_time'] ?? '07:23';
                    $nextPaymentDate = \Carbon\Carbon::parse(
                        $this->createForm['next_payment_date'].' '.$nextPaymentTime,
                        $this->createForm['subscription_timezone']
                    );
                }

                // Update stored next payment date in enrollment
                $this->enrollment->updateNextPaymentDate($nextPaymentDate);

                \Log::info('Next payment date updated for new subscription', [
                    'enrollment_id' => $this->enrollment->id,
                    'next_payment_date' => $nextPaymentDate->toDateTimeString(),
                    'subscription_status' => $this->enrollment->subscription_status,
                ]);
            }

            // Store additional metadata if notes provided
            if ($this->createForm['notes']) {
                $this->enrollment->update(['notes' => $this->createForm['notes']]);
            }

            // Update enrollment with timezone preference
            $this->enrollment->update([
                'subscription_timezone' => $this->createForm['subscription_timezone'],
            ]);

            // Final refresh to get all updated data
            $this->enrollment->refresh();

            // Close modal
            $this->showCreateSubscriptionModal = false;

            \Log::info('Subscription created successfully with options', [
                'enrollment_id' => $this->enrollment->id,
                'stripe_subscription_id' => $this->enrollment->stripe_subscription_id,
            ]);

            session()->flash('success', 'Subscription created successfully with your configured options!');

        } catch (\Exception $e) {
            \Log::error('Failed to create subscription with options', [
                'enrollment_id' => $this->enrollment->id,
                'error' => $e->getMessage(),
                'form_data' => $this->createForm,
            ]);
            session()->flash('error', 'Failed to create subscription: '.$e->getMessage());
        }
    }

    public function cancelSubscription()
    {
        try {
            if (! $this->enrollment->stripe_subscription_id) {
                session()->flash('error', 'No active subscription found.');

                return;
            }

            $stripeService = app(StripeService::class);
            $result = $stripeService->cancelSubscription($this->enrollment->stripe_subscription_id, false);

            // Refresh enrollment to get updated status
            $this->enrollment->refresh();

            // Force refresh of the subscription log
            $this->refreshSubscriptionEvents();

            // Show appropriate message based on cancellation type
            if ($result['immediately']) {
                session()->flash('success', $result['message']);
            } else {
                // Store the cancellation timestamp for pending cancellation
                if (isset($result['cancel_at'])) {
                    $cancelAt = \Carbon\Carbon::createFromTimestamp($result['cancel_at']);
                    $this->enrollment->updateSubscriptionCancellation($cancelAt);
                    $this->enrollment->refresh(); // Refresh to get updated data
                }

                session()->flash('info', $result['message'].' The subscription will automatically end at that time and no further charges will occur.');
            }

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to cancel subscription: '.$e->getMessage());
        }
    }

    public function undoCancellation()
    {
        \Log::info('Undo cancellation button clicked', [
            'enrollment_id' => $this->enrollment->id,
            'subscription_id' => $this->enrollment->stripe_subscription_id,
            'user_id' => auth()->user()->id,
        ]);

        try {
            if (! $this->enrollment->stripe_subscription_id) {
                session()->flash('error', 'No subscription found to undo cancellation.');

                return;
            }

            if (! $this->enrollment->isPendingCancellation()) {
                session()->flash('info', 'Subscription is not pending cancellation.');

                return;
            }

            $stripeService = app(StripeService::class);
            $result = $stripeService->undoCancellation($this->enrollment->stripe_subscription_id);

            if ($result['success']) {
                // Clear the cancellation timestamp
                $this->enrollment->updateSubscriptionCancellation(null);

                // Refresh enrollment to get updated status
                $this->enrollment->refresh();

                // Force refresh of the subscription log
                $this->mount();

                session()->flash('success', $result['message']);

                \Log::info('Cancellation undone successfully', [
                    'enrollment_id' => $this->enrollment->id,
                    'subscription_id' => $this->enrollment->stripe_subscription_id,
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Exception during cancellation undo', [
                'enrollment_id' => $this->enrollment->id,
                'subscription_id' => $this->enrollment->stripe_subscription_id,
                'error' => $e->getMessage(),
            ]);

            session()->flash('error', 'Failed to undo cancellation: '.$e->getMessage());
        }
    }

    public function resumeCanceledSubscription()
    {
        \Log::info('Resume canceled subscription button clicked', [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => auth()->user()->id,
        ]);

        try {
            // Verify that subscription is in a resumable state
            if (! in_array($this->enrollment->subscription_status, ['canceled', 'incomplete_expired', 'incomplete'])) {
                session()->flash('error', 'Subscription is not in a state that can be resumed.');

                return;
            }

            // Check course fee settings and Stripe configuration
            if (! $this->enrollment->course->feeSettings) {
                session()->flash('error', 'Course must have fee settings configured first.');

                return;
            }

            if (! $this->enrollment->course->feeSettings->stripe_price_id) {
                session()->flash('error', 'Course must be synced with Stripe first. Go to Course Edit page and click"Sync to Stripe".');

                return;
            }

            // Get student's default payment method
            $paymentMethod = $this->enrollment->student->user->paymentMethods()
                ->active()
                ->default()
                ->first();

            if (! $paymentMethod) {
                session()->flash('warning', 'Student must add a payment method first. You can add one for them or direct them to their Payment Methods page.');

                return;
            }

            // Create new subscription using StripeService (this will replace the old subscription ID)
            $stripeService = app(StripeService::class);
            $result = $stripeService->createSubscription($this->enrollment, $paymentMethod);

            // Refresh enrollment to get updated subscription data
            $this->enrollment->refresh();

            // Force refresh of the subscription log to show events for the new subscription
            $this->mount();

            session()->flash('success', 'Subscription resumed successfully! A new subscription has been created and the student will be charged according to the billing cycle.');

        } catch (\Exception $e) {
            \Log::error('Failed to resume canceled subscription', [
                'enrollment_id' => $this->enrollment->id,
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'Failed to resume subscription: '.$e->getMessage());
        }
    }

    public function forceRecreateSubscription()
    {
        \Log::info('Force recreate subscription button clicked', [
            'enrollment_id' => $this->enrollment->id,
            'subscription_id' => $this->enrollment->stripe_subscription_id,
            'user_id' => auth()->user()->id,
        ]);

        try {
            // First, cancel the problematic subscription
            if ($this->enrollment->stripe_subscription_id) {
                $stripeService = app(StripeService::class);
                $stripeService->cancelSubscription($this->enrollment->stripe_subscription_id, true); // Cancel immediately

                \Log::info('Problematic subscription canceled', [
                    'enrollment_id' => $this->enrollment->id,
                    'old_subscription_id' => $this->enrollment->stripe_subscription_id,
                ]);
            }

            // Get student's default payment method
            $paymentMethod = $this->enrollment->student->user->paymentMethods()
                ->active()
                ->default()
                ->first();

            if (! $paymentMethod) {
                session()->flash('error', 'Student must have a valid payment method before recreating subscription.');

                return;
            }

            // Create a new subscription
            $stripeService = app(StripeService::class);
            $result = $stripeService->createSubscription($this->enrollment, $paymentMethod);

            // Refresh enrollment to get updated subscription data
            $this->enrollment->refresh();

            // Force refresh of the subscription log
            $this->mount();

            session()->flash('success', 'Subscription recreated successfully! The old problematic subscription has been canceled and a new one created.');

            \Log::info('Subscription successfully recreated', [
                'enrollment_id' => $this->enrollment->id,
                'new_subscription_id' => $this->enrollment->stripe_subscription_id,
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to recreate subscription', [
                'enrollment_id' => $this->enrollment->id,
                'error' => $e->getMessage(),
            ]);

            session()->flash('error', 'Failed to recreate subscription: '.$e->getMessage());
        }
    }

    public function confirmPayment()
    {
        \Log::info('Confirm payment button clicked', [
            'enrollment_id' => $this->enrollment->id,
            'subscription_id' => $this->enrollment->stripe_subscription_id,
            'user_id' => auth()->user()->id,
        ]);

        try {
            if (! $this->enrollment->stripe_subscription_id) {
                session()->flash('error', 'No subscription found to confirm payment.');

                return;
            }

            if ($this->enrollment->subscription_status !== 'incomplete') {
                session()->flash('info', 'Subscription is not in incomplete status - no payment confirmation needed.');

                return;
            }

            $stripeService = app(StripeService::class);
            $result = $stripeService->confirmSubscriptionPayment($this->enrollment->stripe_subscription_id);

            if ($result['success']) {
                // Refresh enrollment to get updated status from webhooks
                $this->enrollment->refresh();

                // Force refresh of the subscription log
                $this->mount();

                session()->flash('success', $result['message'] ?? 'Payment confirmed successfully! Subscription should activate shortly.');

                \Log::info('Payment confirmed successfully', [
                    'enrollment_id' => $this->enrollment->id,
                    'subscription_id' => $this->enrollment->stripe_subscription_id,
                ]);

            } else {
                if (isset($result['requires_action']) && $result['requires_action']) {
                    // Payment requires customer action (3D Secure, etc.)
                    session()->flash('warning', $result['error'].' The customer must complete payment setup themselves.');
                } elseif (isset($result['requires_manual_action']) && $result['requires_manual_action']) {
                    // Payment intent missing and couldn't be recovered - provide detailed guidance
                    $errorMessage = $result['error'];
                    if (isset($result['suggested_actions'])) {
                        $errorMessage .= "\n\nSuggested actions:\n• ".implode("\n• ", $result['suggested_actions']);
                    }
                    session()->flash('error', $errorMessage);
                } else {
                    session()->flash('error', 'Failed to confirm payment: '.$result['error']);
                }

                \Log::warning('Payment confirmation failed', [
                    'enrollment_id' => $this->enrollment->id,
                    'subscription_id' => $this->enrollment->stripe_subscription_id,
                    'error' => $result['error'],
                    'requires_manual_action' => $result['requires_manual_action'] ?? false,
                    'suggested_actions' => $result['suggested_actions'] ?? [],
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Exception during payment confirmation', [
                'enrollment_id' => $this->enrollment->id,
                'subscription_id' => $this->enrollment->stripe_subscription_id,
                'error' => $e->getMessage(),
            ]);

            session()->flash('error', 'Failed to confirm payment: '.$e->getMessage());
        }
    }

    // Schedule management methods
    public function openScheduleModal()
    {
        $this->showScheduleModal = true;
        $this->initializeScheduleForm();
        $this->resetErrorBag();
    }

    public function closeScheduleModal()
    {
        $this->showScheduleModal = false;
        $this->resetErrorBag();
    }

    // Subscription creation methods
    public function openCreateSubscriptionModal()
    {
        $this->showCreateSubscriptionModal = true;
        $this->initializeCreateForm();
        $this->resetErrorBag();
    }

    public function closeCreateSubscriptionModal()
    {
        $this->showCreateSubscriptionModal = false;
        $this->resetErrorBag();
    }

    // Called when modal is opened via wire:model
    public function updatedShowScheduleModal($value)
    {
        if ($value) {
            $this->initializeScheduleForm();
            $this->resetErrorBag();
        } else {
            $this->resetErrorBag();
        }
    }

    public function updateSubscriptionSchedule()
    {
        // Add debugging
        Log::info('Form submission started', [
            'enrollment_id' => $this->enrollment->id,
            'schedule_form' => $this->scheduleForm,
        ]);

        $this->validate();

        Log::info('Validation passed', [
            'enrollment_id' => $this->enrollment->id,
        ]);

        try {
            if (! $this->enrollment->stripe_subscription_id) {
                session()->flash('error', 'No subscription found to update schedule.');
                Log::error('No subscription ID found');

                return;
            }

            $stripeService = app(StripeService::class);

            // Prepare schedule data for Stripe
            $scheduleData = [];
            $enrollmentData = [];

            // Prioritize next payment date over billing cycle anchor
            if ($this->scheduleForm['next_payment_date']) {
                // Handle next payment date - this takes precedence
                $nextPaymentDateTime = $this->scheduleForm['next_payment_date'].' '.($this->scheduleForm['next_payment_time'] ?? '07:23');
                $nextPaymentTimestamp = \Carbon\Carbon::parse($nextPaymentDateTime)
                    ->setTimezone($this->scheduleForm['subscription_timezone'])
                    ->timestamp;
                $scheduleData['next_payment_date'] = $nextPaymentTimestamp;
                $enrollmentData['billing_cycle_anchor'] = \Carbon\Carbon::createFromTimestamp($nextPaymentTimestamp);
            } else {
                // Handle billing cycle anchor only if next payment date is not set
                if ($this->scheduleForm['billing_cycle_anchor']) {
                    $billingAnchor = \Carbon\Carbon::parse($this->scheduleForm['billing_cycle_anchor'])->timestamp;
                    $scheduleData['billing_cycle_anchor'] = $billingAnchor;
                    $enrollmentData['billing_cycle_anchor'] = \Carbon\Carbon::parse($this->scheduleForm['billing_cycle_anchor']);
                }
            }

            // Handle trial end date (both setting and removal)
            // Always process trial end changes regardless of other settings
            if (array_key_exists('trial_end_at', $this->scheduleForm)) {
                if ($this->scheduleForm['trial_end_at']) {
                    // Setting a new trial end date
                    $trialEnd = \Carbon\Carbon::parse($this->scheduleForm['trial_end_at'])->timestamp;
                    $scheduleData['trial_end_at'] = $trialEnd;
                    $enrollmentData['trial_end_at'] = \Carbon\Carbon::parse($this->scheduleForm['trial_end_at']);
                } else {
                    // Removing trial end date (empty field)
                    $scheduleData['trial_end_at'] = null;
                    $enrollmentData['trial_end_at'] = null;
                }
            }

            // Handle proration behavior
            if ($this->scheduleForm['proration_behavior']) {
                $scheduleData['proration_behavior'] = $this->scheduleForm['proration_behavior'];
                $enrollmentData['proration_behavior'] = $this->scheduleForm['proration_behavior'];
            }

            // Handle subscription timezone
            if ($this->scheduleForm['subscription_timezone']) {
                $enrollmentData['subscription_timezone'] = $this->scheduleForm['subscription_timezone'];
            }

            // Handle subscription end date
            if ($this->scheduleForm['end_date']) {
                $endDateTime = $this->scheduleForm['end_date'].' '.($this->scheduleForm['end_time'] ?? '23:59');
                $endTimestamp = \Carbon\Carbon::parse($endDateTime)
                    ->setTimezone($this->scheduleForm['subscription_timezone'])
                    ->timestamp;
                $scheduleData['cancel_at'] = $endTimestamp;
                $enrollmentData['subscription_cancel_at'] = \Carbon\Carbon::createFromTimestamp($endTimestamp);
            }

            // Handle subscription fee update
            $feeUpdateResult = null;
            if (isset($this->scheduleForm['subscription_fee']) &&
                $this->scheduleForm['subscription_fee'] !== null &&
                $this->scheduleForm['subscription_fee'] != $this->enrollment->enrollment_fee) {

                try {
                    $feeUpdateResult = $stripeService->updateSubscriptionFee($this->enrollment, (float) $this->scheduleForm['subscription_fee']);
                    Log::info('Subscription fee updated', [
                        'enrollment_id' => $this->enrollment->id,
                        'old_fee' => $this->enrollment->enrollment_fee,
                        'new_fee' => $this->scheduleForm['subscription_fee'],
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to update subscription fee', [
                        'enrollment_id' => $this->enrollment->id,
                        'new_fee' => $this->scheduleForm['subscription_fee'],
                        'error' => $e->getMessage(),
                    ]);
                    session()->flash('error', 'Failed to update subscription fee: '.$e->getMessage());

                    return;
                }
            }

            // Update schedule in Stripe
            $result = $stripeService->updateSubscriptionSchedule($this->enrollment->stripe_subscription_id, $scheduleData);

            if ($result['success']) {
                // Update local enrollment data
                $this->enrollment->updateSubscriptionSchedule($enrollmentData);

                // Update stored next payment date directly
                if ($this->scheduleForm['next_payment_date']) {
                    $nextPaymentDateTime = \Carbon\Carbon::parse($this->scheduleForm['next_payment_date'].' '.($this->scheduleForm['next_payment_time'] ?? '07:23'))
                        ->setTimezone($this->scheduleForm['subscription_timezone']);
                    $this->enrollment->updateNextPaymentDate($nextPaymentDateTime);
                }

                $this->enrollment->refresh();

                // Refresh subscription events
                $this->refreshSubscriptionEvents();

                // Combine messages if fee was updated
                $message = $result['message'];
                if ($feeUpdateResult) {
                    $message .= ' '.$feeUpdateResult['message'];
                }

                session()->flash('success', $message);
                $this->showScheduleModal = false;

                Log::info('Subscription schedule updated successfully', [
                    'enrollment_id' => $this->enrollment->id,
                    'subscription_id' => $this->enrollment->stripe_subscription_id,
                    'schedule_data' => $scheduleData,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to update subscription schedule', [
                'enrollment_id' => $this->enrollment->id,
                'error' => $e->getMessage(),
                'schedule_form' => $this->scheduleForm,
            ]);
            session()->flash('error', 'Failed to update subscription schedule: '.$e->getMessage());
        }
    }

    public function rescheduleNextPayment()
    {
        try {
            if (! $this->enrollment->stripe_subscription_id) {
                session()->flash('error', 'No subscription found to reschedule payment.');

                return;
            }

            if (! $this->scheduleForm['next_payment_date']) {
                session()->flash('error', 'Next payment date is required.');

                return;
            }

            $nextPaymentDateTime = \Carbon\Carbon::parse($this->scheduleForm['next_payment_date'].' '.($this->scheduleForm['next_payment_time'] ?? '07:23'))
                ->setTimezone($this->scheduleForm['subscription_timezone'])
                ->timestamp;

            $stripeService = app(StripeService::class);
            $result = $stripeService->rescheduleNextPayment($this->enrollment->stripe_subscription_id, $nextPaymentDateTime);

            if ($result['success']) {
                // Update local data
                $this->enrollment->update([
                    'billing_cycle_anchor' => \Carbon\Carbon::createFromTimestamp($nextPaymentDateTime),
                ]);

                // Update stored next payment date
                $this->enrollment->updateNextPaymentDate(\Carbon\Carbon::createFromTimestamp($nextPaymentDateTime));
                $this->enrollment->refresh();

                // Refresh subscription events
                $this->refreshSubscriptionEvents();

                session()->flash('success', $result['message']);
                $this->showScheduleModal = false;

                Log::info('Next payment rescheduled successfully', [
                    'enrollment_id' => $this->enrollment->id,
                    'subscription_id' => $this->enrollment->stripe_subscription_id,
                    'next_payment_timestamp' => $nextPaymentDateTime,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to reschedule next payment', [
                'enrollment_id' => $this->enrollment->id,
                'error' => $e->getMessage(),
                'next_payment_data' => [
                    'date' => $this->scheduleForm['next_payment_date'],
                    'time' => $this->scheduleForm['next_payment_time'],
                ],
            ]);
            session()->flash('error', 'Failed to reschedule next payment: '.$e->getMessage());
        }
    }

    public function updateTrialEnd()
    {
        try {
            if (! $this->enrollment->stripe_subscription_id) {
                session()->flash('error', 'No subscription found to update trial.');

                return;
            }

            $trialEndTimestamp = null;
            if ($this->scheduleForm['trial_end_at']) {
                $trialEndTimestamp = \Carbon\Carbon::parse($this->scheduleForm['trial_end_at'])
                    ->setTimezone($this->scheduleForm['subscription_timezone'])
                    ->timestamp;
            }

            $stripeService = app(StripeService::class);
            $result = $stripeService->updateTrialEnd($this->enrollment->stripe_subscription_id, $trialEndTimestamp);

            if ($result['success']) {
                // Update local data
                $this->enrollment->update([
                    'trial_end_at' => $trialEndTimestamp ? \Carbon\Carbon::createFromTimestamp($trialEndTimestamp) : null,
                ]);
                $this->enrollment->refresh();

                // Refresh subscription events
                $this->refreshSubscriptionEvents();

                session()->flash('success', $result['message']);
                $this->showScheduleModal = false;

                Log::info('Trial end updated successfully', [
                    'enrollment_id' => $this->enrollment->id,
                    'subscription_id' => $this->enrollment->stripe_subscription_id,
                    'trial_end_timestamp' => $trialEndTimestamp,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to update trial end', [
                'enrollment_id' => $this->enrollment->id,
                'error' => $e->getMessage(),
                'trial_end_at' => $this->scheduleForm['trial_end_at'],
            ]);
            session()->flash('error', 'Failed to update trial end: '.$e->getMessage());
        }
    }

    public function updateSubscriptionEndDate()
    {
        try {
            if (! $this->enrollment->stripe_subscription_id) {
                session()->flash('error', 'No subscription found to update end date.');

                return;
            }

            $endTimestamp = null;
            if ($this->scheduleForm['end_date']) {
                $endDateTime = $this->scheduleForm['end_date'].' '.($this->scheduleForm['end_time'] ?? '23:59');
                $endTimestamp = \Carbon\Carbon::parse($endDateTime)
                    ->setTimezone($this->scheduleForm['subscription_timezone'])
                    ->timestamp;
            }

            $stripeService = app(StripeService::class);
            $result = $stripeService->updateSubscriptionEndDate($this->enrollment->stripe_subscription_id, $endTimestamp);

            if ($result['success']) {
                // Update local data
                $this->enrollment->update([
                    'subscription_cancel_at' => $endTimestamp ? \Carbon\Carbon::createFromTimestamp($endTimestamp) : null,
                ]);
                $this->enrollment->refresh();

                // Refresh subscription events
                $this->refreshSubscriptionEvents();

                session()->flash('success', $result['message']);
                $this->showScheduleModal = false;

                Log::info('Subscription end date updated successfully', [
                    'enrollment_id' => $this->enrollment->id,
                    'subscription_id' => $this->enrollment->stripe_subscription_id,
                    'end_timestamp' => $endTimestamp,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to update subscription end date', [
                'enrollment_id' => $this->enrollment->id,
                'error' => $e->getMessage(),
                'end_date_data' => [
                    'date' => $this->scheduleForm['end_date'],
                    'time' => $this->scheduleForm['end_time'],
                ],
            ]);
            session()->flash('error', 'Failed to update subscription end date: '.$e->getMessage());
        }
    }

    // Manual payment methods
    public function createManualSubscription()
    {
        if (! $this->enrollment->isManualPaymentType()) {
            session()->flash('error', 'This enrollment is not set up for manual payments.');

            return;
        }

        try {
            $stripeService = app(StripeService::class);
            $result = $stripeService->createManualSubscription($this->enrollment);

            if ($result['success']) {
                $this->enrollment->refresh();
                session()->flash('success', $result['message']);
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create manual subscription: '.$e->getMessage());
        }
    }

    public function openManualPaymentModal()
    {
        $this->showManualPaymentModal = true;
    }

    public function closeManualPaymentModal()
    {
        $this->showManualPaymentModal = false;
    }

    public function openCreateManualOrderModal()
    {
        $this->showCreateManualOrderModal = true;
    }

    public function closeCreateManualOrderModal()
    {
        $this->showCreateManualOrderModal = false;
    }

    public function generateManualPaymentOrder()
    {
        try {
            $stripeService = app(StripeService::class);
            $order = $stripeService->generateNextManualPaymentOrder($this->enrollment);

            $this->enrollment->refresh();
            $this->closeCreateManualOrderModal();

            session()->flash('success', "Manual payment order generated successfully! Order ID: {$order->order_number}");
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to generate manual payment order: '.$e->getMessage());
        }
    }

    public function openApprovalModal($orderId)
    {
        $this->selectedOrderForApproval = $orderId;
        $this->paymentDate = now()->format('Y-m-d');
        $this->receiptFile = null;
        $this->showApprovalModal = true;
    }

    public function closeApprovalModal()
    {
        $this->showApprovalModal = false;
        $this->selectedOrderForApproval = null;
        $this->paymentDate = '';
        $this->receiptFile = null;
    }

    public function approveManualPayment()
    {
        $this->validate([
            'paymentDate' => 'required|date',
            'receiptFile' => 'required|file|max:10240', // 10MB max
        ]);

        try {
            $order = $this->enrollment->orders()->findOrFail($this->selectedOrderForApproval);

            if ($order->isPaid()) {
                session()->flash('warning', 'This order has already been paid.');
                $this->closeApprovalModal();

                return;
            }

            // Handle receipt file upload if provided
            $receiptPath = null;
            if ($this->receiptFile) {
                $receiptPath = $this->receiptFile->store('receipts', 'public');
            }

            $stripeService = app(StripeService::class);

            if ($this->enrollment->stripe_subscription_id) {
                $result = $stripeService->processManualSubscriptionPayment($this->enrollment, $order);
            } else {
                $order->markAsPaid();
                $result = ['success' => true, 'message' => 'Manual payment approved successfully'];
            }

            // Update order with payment details
            if ($result['success']) {
                $order->update([
                    'paid_at' => $this->paymentDate,
                    'metadata' => array_merge($order->metadata ?? [], [
                        'manual_approval' => true,
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                        'receipt_file' => $receiptPath,
                        'payment_date' => $this->paymentDate,
                    ]),
                ]);

                $this->enrollment->refresh();
                session()->flash('success', $result['message']);
                $this->closeApprovalModal();
            } else {
                session()->flash('error', $result['message']);
            }
        } catch (\Exception $e) {
            \Log::error('Error approving manual payment', [
                'order_id' => $this->selectedOrderForApproval,
                'enrollment_id' => $this->enrollment->id,
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'Failed to approve payment: '.$e->getMessage());
        }
    }

    public function hasReceiptAttachment($order): bool
    {
        return isset($order->metadata['receipt_file']) && ! empty($order->metadata['receipt_file']);
    }

    public function getReceiptUrl($order): ?string
    {
        if (! $this->hasReceiptAttachment($order)) {
            return null;
        }

        return asset('storage/'.$order->metadata['receipt_file']);
    }

    public function downloadReceipt($orderId)
    {
        $order = $this->enrollment->orders()->findOrFail($orderId);

        if (! $this->hasReceiptAttachment($order)) {
            session()->flash('error', 'No receipt attachment found.');

            return;
        }

        $filePath = storage_path('app/public/'.$order->metadata['receipt_file']);

        if (! file_exists($filePath)) {
            session()->flash('error', 'Receipt file not found.');

            return;
        }

        return response()->download($filePath, 'receipt-'.$order->order_number.'.'.pathinfo($filePath, PATHINFO_EXTENSION));
    }

    public function getApprover($order)
    {
        if (! isset($order->metadata['approved_by'])) {
            return null;
        }

        return \App\Models\User::find($order->metadata['approved_by']);
    }

    public function switchToAutomaticPayments($paymentMethodId = null)
    {
        if (! $this->enrollment->canSwitchPaymentMethod()) {
            session()->flash('error', 'Cannot switch payment method at this time.');

            return;
        }

        try {
            $stripeService = app(\App\Services\StripeService::class);

            // Ensure we have the latest student data with proper relationships
            $this->enrollment->load('student.user');

            // Check if student's user has a Stripe customer account
            $user = $this->enrollment->student->user;
            $stripeCustomer = $user->stripeCustomer ?? null;

            if (! $stripeCustomer) {
                // First try to find existing customer by email
                try {
                    $existingCustomer = $stripeService->findCustomerByEmail($user->email);

                    if ($existingCustomer) {
                        // Create StripeCustomer record linking to this user
                        $stripeCustomer = \App\Models\StripeCustomer::create([
                            'user_id' => $user->id,
                            'stripe_customer_id' => $existingCustomer->id,
                            'metadata' => json_encode($existingCustomer->toArray()),
                            'last_synced_at' => now(),
                        ]);

                        session()->flash('success', 'Found and linked existing Stripe customer account. Checking payment methods...');
                    } else {
                        // Create new customer in Stripe
                        $customer = $stripeService->createCustomer($user->email, $user->name);

                        // Create StripeCustomer record
                        $stripeCustomer = \App\Models\StripeCustomer::create([
                            'user_id' => $user->id,
                            'stripe_customer_id' => $customer->id,
                            'metadata' => json_encode($customer->toArray()),
                            'last_synced_at' => now(),
                        ]);

                        session()->flash('info', 'Created new Stripe customer account. Student needs to add a payment method.');

                        return;
                    }

                } catch (\Exception $e) {
                    session()->flash('error', 'Failed to setup Stripe customer account: '.$e->getMessage());

                    return;
                }
            }

            // Now check if the user has payment methods
            $hasPaymentMethods = $user->paymentMethods()->where('is_active', true)->exists();
            if (! $hasPaymentMethods) {
                session()->flash('error', 'Student has a Stripe customer account but no active payment methods found. Please ask the student to add a card in their account settings.');

                return;
            }

            // At this point, we know the student has a Stripe customer account and payment methods
            // (either pre-existing or newly linked)

            // If there's an active subscription, use the existing Stripe method
            if ($this->enrollment->stripe_subscription_id) {
                // Get the student's default payment method
                $student = $this->enrollment->student;
                $defaultPaymentMethod = $student->user->paymentMethods()->where('is_default', true)->first();

                if (! $defaultPaymentMethod) {
                    // Try to get the first available payment method
                    $defaultPaymentMethod = $student->user->paymentMethods()->first();
                }

                if (! $defaultPaymentMethod) {
                    session()->flash('error', 'No payment method found for student. Please ask them to add a payment method first.');

                    return;
                }

                // Use the StripeService method to switch to automatic payments
                $result = $stripeService->switchToAutomaticPayments($this->enrollment, $defaultPaymentMethod);

                session()->flash('success', 'Payment method switched to automatic payments successfully. Future payments will be charged automatically.');
            } else {
                // For enrollments without active subscriptions, just update the enrollment type
                $this->enrollment->update([
                    'payment_method_type' => 'automatic',
                    'manual_payment_required' => false,
                ]);

                session()->flash('success', 'Payment method switched to automatic. A subscription will be created when the next payment is due.');
            }

            // Refresh enrollment data
            $this->enrollment->refresh();

        } catch (\Exception $e) {
            \Log::error('Failed to switch payment method to automatic', [
                'enrollment_id' => $this->enrollment->id,
                'error' => $e->getMessage(),
            ]);

            session()->flash('error', 'Failed to switch payment method: '.$e->getMessage());
        }
    }

    public function switchToManualPayments()
    {
        if (! $this->enrollment->canSwitchPaymentMethod()) {
            session()->flash('error', 'Cannot switch payment method at this time.');

            return;
        }

        try {
            $stripeService = app(\App\Services\StripeService::class);

            // If there's an active subscription, use the StripeService method
            if ($this->enrollment->stripe_subscription_id) {
                $result = $stripeService->switchToManualPayments($this->enrollment);

                session()->flash('success', 'Payment method switched to manual payments successfully. Collection has been paused and future payments will require manual processing.');
            } else {
                // For enrollments without active subscriptions, just update the enrollment type
                $this->enrollment->update([
                    'payment_method_type' => 'manual',
                    'manual_payment_required' => true,
                ]);

                session()->flash('success', 'Payment method switched to manual. Future payments will require manual processing.');
            }

            // Refresh enrollment data
            $this->enrollment->refresh();

        } catch (\Exception $e) {
            \Log::error('Failed to switch payment method to manual', [
                'enrollment_id' => $this->enrollment->id,
                'error' => $e->getMessage(),
            ]);

            session()->flash('error', 'Failed to switch payment method: '.$e->getMessage());
        }
    }

    public function getStudentPaymentMethodsDetails(): array
    {
        if (! $this->enrollment->student || ! $this->enrollment->student->user) {
            return [
                'ready' => false,
                'has_customer' => false,
                'payment_methods' => [],
                'status' => 'No user account',
                'message' => 'Student needs a valid user account first',
            ];
        }

        try {
            $user = $this->enrollment->student->user;
            $stripeService = app(\App\Services\StripeService::class);

            // Check if user has a linked Stripe customer
            $stripeCustomer = $user->stripeCustomer;

            // If no linked customer, try to find existing customer by email
            if (! $stripeCustomer) {
                try {
                    $existingCustomer = $stripeService->findCustomerByEmail($user->email);
                    if ($existingCustomer) {
                        // Found existing customer - we can link it
                        return [
                            'ready' => true, // We can link this customer and their payment methods
                            'has_customer' => false, // Not yet linked in our database
                            'payment_methods' => [],
                            'status' => 'Stripe customer found, not yet linked',
                            'message' => 'Found existing Stripe customer. Click"Switch to Automatic Payment" to link and activate.',
                        ];
                    } else {
                        // No existing customer found
                        return [
                            'ready' => false,
                            'has_customer' => false,
                            'payment_methods' => [],
                            'status' => 'No Stripe customer account',
                            'message' => 'Student needs to be set up in Stripe first',
                        ];
                    }
                } catch (\Exception $e) {
                    return [
                        'ready' => false,
                        'has_customer' => false,
                        'payment_methods' => [],
                        'status' => 'Cannot check Stripe customer',
                        'message' => 'Error checking for existing customer account',
                    ];
                }
            }

            // We have a linked customer, check their payment methods using our database
            $hasPaymentMethods = $user->paymentMethods()->where('is_active', true)->exists();
            $paymentMethodCount = $user->paymentMethods()->where('is_active', true)->count();

            return [
                'ready' => $hasPaymentMethods && $this->enrollment->canSwitchPaymentMethod(),
                'has_customer' => true,
                'payment_methods' => $user->paymentMethods()->where('is_active', true)->get(),
                'payment_method_count' => $paymentMethodCount,
                'status' => $hasPaymentMethods ? 'Ready for automatic payments' : 'No payment methods found',
                'message' => $hasPaymentMethods
                    ? 'Student has '.$paymentMethodCount.' payment method(s) on file'
                    : 'Student must add a payment method first. Ask them to set up a card in their account settings.',
            ];
        } catch (\Exception $e) {
            \Log::warning('Failed to check customer payment methods for readiness display', [
                'student_id' => $this->enrollment->student_id,
                'user_id' => $this->enrollment->student->user->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'ready' => false,
                'has_customer' => false,
                'payment_methods' => [],
                'status' => 'Unable to check payment methods',
                'message' => 'Error checking payment methods. Please try again.',
            ];
        }
    }

    public function getPaymentMethodsDetailsProperty(): array
    {
        return $this->getStudentPaymentMethodsDetails();
    }

    public function joinClass($classId)
    {
        try {
            $class = \App\Models\ClassModel::findOrFail($classId);

            // Validate the enrollment can join this class
            if (! $this->enrollment->canJoinClass($class)) {
                session()->flash('error', 'Cannot join this class. Please check enrollment status and class availability.');

                return;
            }

            // Check if already enrolled
            $existingEnrollment = \App\Models\ClassStudent::where('class_id', $classId)
                ->where('student_id', $this->enrollment->student_id)
                ->where('status', 'active')
                ->first();

            if ($existingEnrollment) {
                session()->flash('warning', 'Student is already enrolled in this class.');

                return;
            }

            // Join the class using the enrollment method
            $classStudent = $this->enrollment->joinClass($class);

            if ($classStudent) {
                session()->flash('success', "Successfully enrolled student in '{$class->title}'.");

                // Refresh the enrollment to show updated data
                $this->enrollment->refresh();
            } else {
                session()->flash('error', 'Failed to enroll student in class.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error joining class: '.$e->getMessage());
        }
    }

    public function leaveClass($classId, $reason = 'Left by admin')
    {
        try {
            $class = \App\Models\ClassModel::findOrFail($classId);

            // Find the active class enrollment
            $classStudent = \App\Models\ClassStudent::where('class_id', $classId)
                ->where('student_id', $this->enrollment->student_id)
                ->where('status', 'active')
                ->first();

            if (! $classStudent) {
                session()->flash('warning', 'Student is not enrolled in this class.');

                return;
            }

            // Leave the class using the enrollment method
            $result = $this->enrollment->leaveClass($class, $reason);

            if ($result) {
                session()->flash('success', "Successfully removed student from '{$class->title}'.");

                // Refresh the enrollment to show updated data
                $this->enrollment->refresh();
            } else {
                session()->flash('error', 'Failed to remove student from class.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error leaving class: '.$e->getMessage());
        }
    }
}; ?>

<div>
    <!-- Flash Messages -->
    @if (session('success'))
        <div class="mb-6 rounded-md bg-green-50 p-4 /20">
            <div class="flex">
                <div class="flex-shrink-0">
                    <flux:icon.check-circle class="h-5 w-5 text-green-400" />
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="mb-6 rounded-md bg-red-50 p-4 /20">
            <div class="flex">
                <div class="flex-shrink-0">
                    <flux:icon.x-circle class="h-5 w-5 text-red-400" />
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session('warning'))
        <div class="mb-6 rounded-md bg-yellow-50 p-4 /20">
            <div class="flex">
                <div class="flex-shrink-0">
                    <flux:icon.exclamation-triangle class="h-5 w-5 text-yellow-400" />
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-yellow-800">{{ session('warning') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session('info'))
        <div class="mb-6 rounded-md bg-blue-50 p-4 /20">
            <div class="flex">
                <div class="flex-shrink-0">
                    <flux:icon.information-circle class="h-5 w-5 text-blue-400" />
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-blue-800">{{ session('info') }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Enrollment Details</flux:heading>
            <flux:text class="mt-2">{{ $enrollment->student->user->name }} - {{ $enrollment->course->name }}</flux:text>
        </div>
        <div class="flex space-x-3">
            <flux:button variant="ghost" href="{{ route('enrollments.index') }}">
                Back to Enrollments
            </flux:button>
            <flux:button variant="primary" href="{{ route('enrollments.edit', $enrollment) }}" icon="pencil">
                Edit Enrollment
            </flux:button>
        </div>
    </div>

    <div class="mt-6 space-y-8">
        <!-- Status Badge -->
        <div class="flex justify-center">
            <flux:badge size="lg" :class="$enrollment->academic_status->badgeClass()">
                {{ $enrollment->academic_status->label() }}
            </flux:badge>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Student Information -->
            <flux:card>
                <flux:heading size="lg">Student Information</flux:heading>
                
                <div class="mt-6 space-y-4">
                    <div class="flex items-center space-x-4">
                        <flux:avatar size="lg">
                            {{ $enrollment->student->user->initials() }}
                        </flux:avatar>
                        <div>
                            <p class="text-lg font-medium">{{ $enrollment->student->user->name }}</p>
                            <p class="text-gray-500">{{ $enrollment->student->user->email }}</p>
                            <p class="text-gray-500">ID: {{ $enrollment->student->student_id }}</p>
                        </div>
                    </div>

                    <div class="pt-4 border-t">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Phone</p>
                                <p class="text-sm text-gray-900">{{ $enrollment->student->phone ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Status</p>
                                <flux:badge :class="match($enrollment->student->status) {
                                    'active' => 'badge-green',
                                    'inactive' => 'badge-gray',
                                    'graduated' => 'badge-blue',
                                    'suspended' => 'badge-red',
                                    default => 'badge-gray'
                                }">
                                    {{ ucfirst($enrollment->student->status) }}
                                </flux:badge>
                            </div>
                        </div>

                        <div class="mt-4 flex gap-3">
                            <flux:button size="sm" variant="ghost" href="{{ route('students.show', $enrollment->student) }}">
                                View Full Student Profile
                            </flux:button>
                            <flux:button size="sm" variant="outline" href="{{ route('admin.students.payment-methods', $enrollment->student) }}" icon="credit-card">
                                Manage Payment Methods
                            </flux:button>
                        </div>
                    </div>
                </div>
            </flux:card>

            <!-- Course Information -->
            <flux:card>
                <flux:heading size="lg">Course Information</flux:heading>
                
                <div class="mt-6 space-y-4">
                    <div>
                        <p class="text-lg font-medium">{{ $enrollment->course->name }}</p>
                        <p class="text-gray-600">{{ $enrollment->course->description ?? 'No description available' }}</p>
                    </div>

                    <div class="pt-4 border-t">
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Course Status</p>
                                <flux:badge :class="match($enrollment->course->status) {
                                    'active' => 'badge-green',
                                    'inactive' => 'badge-gray',
                                    'archived' => 'badge-yellow',
                                    default => 'badge-gray'
                                }">
                                    {{ ucfirst($enrollment->course->status) }}
                                </flux:badge>
                            </div>
                            @if($enrollment->course->feeSettings)
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Course Fee</p>
                                    <p class="text-sm text-gray-900">{{ $enrollment->course->formatted_fee ?? 'N/A' }}</p>
                                </div>
                            @endif
                            <div>
                                <p class="text-sm font-medium text-gray-500">Enrollment Fee</p>
                                <p class="text-sm text-gray-900">{{ $enrollment->formatted_enrollment_fee }}</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <flux:button size="sm" variant="ghost" href="{{ route('courses.show', $enrollment->course) }}">
                                View Course Details
                            </flux:button>
                        </div>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Class Management -->
        <flux:card>
            <flux:heading size="lg">Class Management</flux:heading>
            <flux:text class="mt-2">Manage which classes this student is enrolled in for this course</flux:text>

            <div class="mt-6">
                @php
                    $availableClasses = $enrollment->availableClasses;
                    $enrolledClasses = $enrollment->student->classStudents()
                        ->whereHas('class', function($query) {
                            $query->where('course_id', $this->enrollment->course_id);
                        })
                        ->with('class')
                        ->get();
                @endphp

                @if($availableClasses->count() > 0)
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Available Classes -->
                        <div>
                            <flux:heading size="md" class="mb-4">Available Classes</flux:heading>
                            <div class="space-y-3">
                                @foreach($availableClasses as $class)
                                    @php
                                        $isEnrolled = $enrolledClasses->contains(function($cs) use ($class) {
                                            return $cs->class_id === $class->id && $cs->status === 'active';
                                        });
                                    @endphp
                                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                        <div class="flex-1">
                                            <flux:heading size="sm">
                                                <a href="{{ route('classes.show', $class) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                                    {{ $class->title }}
                                                </a>
                                            </flux:heading>
                                            <flux:text size="sm" class="text-gray-600 mt-1">
                                                {{ $class->date_time->format('M j, Y \a\t g:i A') }}
                                                ({{ $class->duration_minutes }} min)
                                            </flux:text>
                                            <flux:text size="sm" class="text-gray-500">
                                                {{ $class->class_type }} • Max: {{ $class->max_capacity }} students
                                            </flux:text>
                                        </div>
                                        <div class="ml-4">
                                            @if($isEnrolled)
                                                <flux:badge variant="success" size="sm">Enrolled</flux:badge>
                                                <flux:button
                                                    size="sm"
                                                    variant="ghost"
                                                    color="red"
                                                    wire:click="leaveClass({{ $class->id }})"
                                                    class="ml-2"
                                                >
                                                    Leave Class
                                                </flux:button>
                                            @else
                                                @if($enrollment->canJoinClass($class))
                                                    <flux:button
                                                        size="sm"
                                                        variant="primary"
                                                        wire:click="joinClass({{ $class->id }})"
                                                    >
                                                        Join Class
                                                    </flux:button>
                                                @else
                                                    <flux:text size="sm" class="text-gray-500">
                                                        Cannot join
                                                    </flux:text>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Enrolled Classes Details -->
                        <div>
                            <flux:heading size="md" class="mb-4">Enrollment Status</flux:heading>
                            @if($enrolledClasses->count() > 0)
                                <div class="space-y-3">
                                    @foreach($enrolledClasses as $classStudent)
                                        <div class="p-4 border border-gray-200 rounded-lg">
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <flux:heading size="sm">
                                                        <a href="{{ route('classes.show', $classStudent->class) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                                            {{ $classStudent->class->title }}
                                                        </a>
                                                    </flux:heading>
                                                    <flux:text size="sm" class="text-gray-600 mt-1">
                                                        Enrolled: {{ $classStudent->enrolled_at->format('M j, Y') }}
                                                    </flux:text>
                                                </div>
                                                <flux:badge
                                                    :variant="$classStudent->status === 'active' ? 'success' : 'warning'"
                                                    size="sm"
                                                >
                                                    {{ ucfirst($classStudent->status) }}
                                                </flux:badge>
                                            </div>
                                            @if($classStudent->status !== 'active' && $classStudent->reason)
                                                <flux:text size="sm" class="text-gray-500 mt-2">
                                                    Reason: {{ $classStudent->reason }}
                                                </flux:text>
                                            @endif
                                            @if($classStudent->left_at)
                                                <flux:text size="sm" class="text-gray-500 mt-1">
                                                    Left: {{ $classStudent->left_at->format('M j, Y') }}
                                                </flux:text>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-8">
                                    <flux:icon.academic-cap class="mx-auto h-12 w-12 text-gray-400" />
                                    <flux:heading size="sm" class="mt-2 text-gray-900">No Class Enrollments</flux:heading>
                                    <flux:text size="sm" class="mt-1 text-gray-500">
                                        This student is not enrolled in any classes for this course yet.
                                    </flux:text>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="text-center py-8">
                        <flux:icon.calendar class="mx-auto h-12 w-12 text-gray-400" />
                        <flux:heading size="sm" class="mt-2 text-gray-900">No Classes Available</flux:heading>
                        <flux:text size="sm" class="mt-1 text-gray-500">
                            There are no classes available for this course yet.
                        </flux:text>
                    </div>
                @endif
            </div>
        </flux:card>

        <!-- Subscription Management -->
        @if($enrollment->stripe_subscription_id)
            <flux:card>
                <flux:heading size="lg">Subscription Information</flux:heading>
                
                <div class="mt-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Subscription ID</p>
                            <p class="text-sm text-gray-900 font-mono">{{ $enrollment->stripe_subscription_id }}</p>
                        </div>
                        
                        <div>
                            <p class="text-sm font-medium text-gray-500">Status</p>
                            <flux:badge :class="match(true) {
                                $enrollment->isPendingCancellation() => 'badge-orange',
                                $enrollment->subscription_status === 'active' => 'badge-green',
                                $enrollment->subscription_status === 'trialing' => 'badge-blue',
                                $enrollment->subscription_status === 'past_due' => 'badge-yellow',
                                in_array($enrollment->subscription_status, ['canceled', 'unpaid', 'incomplete_expired']) => 'badge-red',
                                default => 'badge-gray'
                            }">
                                {{ $enrollment->getSubscriptionStatusLabel() }}
                            </flux:badge>
                        </div>
                        
                        <div>
                            <p class="text-sm font-medium text-gray-500">Monthly Fee</p>
                            <p class="text-sm text-gray-900">{{ $enrollment->formatted_enrollment_fee }}</p>
                        </div>
                        
                        <div>
                            <p class="text-sm font-medium text-gray-500">
                                @if($enrollment->isPendingCancellation())
                                    Cancellation Date
                                @else
                                    Next Payment
                                @endif
                            </p>
                            <p class="text-sm text-gray-900">
                                @if($enrollment->isPendingCancellation())
                                    <span class="text-orange-600 font-medium">{{ $enrollment->getFormattedCancellationDate() }}</span>
                                @elseif($enrollment->hasActiveSubscription())
                                    @php 
                                        $nextPayment = $enrollment->getFormattedNextPaymentDate();
                                    @endphp
                                    @if($nextPayment)
                                        <span class="text-green-600">{{ $nextPayment }}</span>
                                    @else
                                        <span class="text-gray-400">Not scheduled</span>
                                    @endif
                                @elseif($enrollment->isSubscriptionPastDue())
                                    <span class="text-red-600">Overdue</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    
                    <!-- Subscription Actions -->
                    @if(!$enrollment->isSubscriptionCanceled())
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="flex flex-wrap gap-3">
                                <flux:button size="sm" variant="primary" wire:click="syncSubscriptionData" wire:confirm="This will sync all subscription data from Stripe including status, collection status, next payment date, and cancellation information. Continue?" icon="arrow-path">
                                    Sync from Stripe
                                </flux:button>
                                
                                <flux:button size="sm" variant="outline" wire:click="openScheduleModal" icon="calendar-days">
                                    Manage Schedule
                                </flux:button>
                                
                                @if($enrollment->subscription_status === 'incomplete')
                                    <flux:button size="sm" variant="primary" wire:click="confirmPayment" wire:confirm="Attempt to confirm the payment for this subscription. This may not work if customer authentication is required." icon="credit-card">
                                        Confirm Payment
                                    </flux:button>
                                    
                                    <flux:button size="sm" variant="outline" wire:click="forceRecreateSubscription" wire:confirm="This will cancel the current problematic subscription and create a new one. The student will be charged immediately. Are you sure?" icon="arrow-path">
                                        Force Recreate
                                    </flux:button>
                                @endif
                                
                                @if($enrollment->isPendingCancellation())
                                    <flux:button size="sm" variant="primary" wire:click="undoCancellation" wire:confirm="Are you sure you want to undo the cancellation? The subscription will continue normally and billing will resume." icon="arrow-uturn-left">
                                        Undo Cancellation
                                    </flux:button>
                                @else
                                    <flux:button size="sm" variant="danger" wire:click="cancelSubscription" wire:confirm="Are you sure you want to cancel this subscription? It will be canceled at the end of the current billing period.">
                                        Cancel Subscription
                                    </flux:button>
                                @endif
                                
                                <flux:button size="sm" variant="ghost" href="{{ route('orders.index', ['enrollment' => $enrollment->id]) }}">
                                    View Payment History
                                </flux:button>
                            </div>
                            
                            @if($enrollment->subscription_status === 'incomplete')
                                <div class="mt-4">
                                    <p class="text-xs text-gray-500">
                                        <flux:icon.exclamation-triangle class="h-3 w-3 inline mr-1" />
                                        This subscription is waiting for payment confirmation. Click"Confirm Payment" to attempt automatic processing, or the student can complete payment setup themselves.
                                    </p>
                                </div>
                            @elseif($enrollment->isPendingCancellation())
                                <div class="mt-4">
                                    <div class="rounded-md bg-orange-50 p-3 /20">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <flux:icon.clock class="h-4 w-4 text-orange-400" />
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-xs text-orange-800">
                                                    <strong>Pending Cancellation:</strong> This subscription is scheduled to be canceled on {{ $enrollment->getFormattedCancellationDate() }}. 
                                                    The student will have access until that date, and no further charges will occur after cancellation. 
                                                    You can undo this cancellation at any time before it takes effect.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <!-- Actions for canceled/expired subscriptions -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="flex flex-wrap gap-3">
                                <flux:button size="sm" variant="outline" wire:click="syncSubscriptionData" wire:confirm="This will sync the latest subscription data from Stripe. Continue?" icon="arrow-path">
                                    Sync from Stripe
                                </flux:button>
                                
                                @if($enrollment->course->feeSettings && $enrollment->course->feeSettings->stripe_price_id)
                                    <flux:button size="sm" variant="primary" wire:click="resumeCanceledSubscription" wire:confirm="This will create a new subscription for the student. Are you sure you want to resume the subscription?">
                                        Resume Subscription
                                    </flux:button>
                                @endif
                                
                                <flux:button size="sm" variant="ghost" href="{{ route('orders.index', ['enrollment' => $enrollment->id]) }}">
                                    View Payment History
                                </flux:button>
                                
                                <flux:button size="sm" variant="ghost" href="{{ route('admin.students.payment-methods', $enrollment->student) }}" icon="credit-card">
                                    Manage Payment Methods
                                </flux:button>
                            </div>
                            
                            @if(!$enrollment->course->feeSettings || !$enrollment->course->feeSettings->stripe_price_id)
                                <div class="mt-4">
                                    <p class="text-xs text-gray-500">Course must have fee settings configured and synced with Stripe before resuming subscription.</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </flux:card>
        @else
            <flux:card>
                <flux:heading size="lg">Subscription Information</flux:heading>
                
                <div class="mt-6 text-center py-8">
                    <flux:icon.credit-card class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No Active Subscription</h3>
                    <p class="mt-1 text-sm text-gray-500">This enrollment does not have an active subscription.</p>
                    
                    @php
                        // Check subscription creation requirements
                        $hasFeeSettings = $enrollment->course->feeSettings && $enrollment->course->feeSettings->stripe_price_id;
                        $hasPaymentMethod = $enrollment->student->user->paymentMethods()->active()->default()->exists();
                        $canCreateSubscription = $hasFeeSettings && $hasPaymentMethod;
                        
                        // Get default payment method for display
                        $defaultPaymentMethod = $enrollment->student->user->paymentMethods()->active()->default()->first();
                    @endphp

                    <!-- Requirements Status -->
                    <div class="mt-6 bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Subscription Requirements</h4>
                        <div class="space-y-2 text-sm text-left">
                            <!-- Course Fee Settings -->
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Course fee settings configured</span>
                                @if($hasFeeSettings)
                                    <div class="flex items-center text-green-600">
                                        <flux:icon.check-circle class="h-4 w-4 mr-1" />
                                        <span class="font-medium">Ready</span>
                                    </div>
                                @else
                                    <div class="flex items-center text-red-600">
                                        <flux:icon.x-circle class="h-4 w-4 mr-1" />
                                        <span class="font-medium">Missing</span>
                                    </div>
                                @endif
                            </div>
                            
                            <!-- Payment Method -->
                            <div class="flex items-center justify-between">
                                <span class="text-gray-600">Student payment method</span>
                                @if($hasPaymentMethod)
                                    <div class="flex items-center text-green-600">
                                        <flux:icon.check-circle class="h-4 w-4 mr-1" />
                                        <span class="font-medium">{{ $defaultPaymentMethod->display_name ?? 'Available' }}</span>
                                    </div>
                                @else
                                    <div class="flex items-center text-red-600">
                                        <flux:icon.x-circle class="h-4 w-4 mr-1" />
                                        <span class="font-medium">None</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-6 space-y-3">
                        @if($canCreateSubscription)
                            <flux:button variant="primary" wire:click="openCreateSubscriptionModal" icon="plus">
                                Create Subscription
                            </flux:button>
                            <div class="text-xs text-green-600 flex items-center justify-center">
                                <flux:icon.check-circle class="h-3 w-3 mr-1" />
                                All requirements met - ready to create subscription
                            </div>
                        @else
                            <flux:button variant="primary" disabled>
                                Create Subscription
                            </flux:button>
                            <div class="text-xs text-red-600 flex items-center justify-center">
                                <flux:icon.exclamation-triangle class="h-3 w-3 mr-1" />
                                Requirements must be met before creating subscription
                            </div>
                        @endif
                        
                        <!-- Management Actions -->
                        <div class="flex flex-col sm:flex-row gap-2 justify-center">
                            <flux:button variant="ghost" size="sm" href="{{ route('admin.students.payment-methods', $enrollment->student) }}" icon="credit-card">
                                Manage Payment Methods
                            </flux:button>
                            
                            @if(!$hasFeeSettings)
                                <flux:button variant="ghost" size="sm" href="{{ route('courses.edit', $enrollment->course) }}" icon="cog-6-tooth">
                                    Configure Course Fee
                                </flux:button>
                            @endif
                            
                            <flux:button variant="ghost" size="sm" href="{{ route('students.show', $enrollment->student) }}" icon="user">
                                View Student Profile
                            </flux:button>
                        </div>
                    </div>

                    <!-- Detailed Help Text -->
                    @if(!$canCreateSubscription)
                        <div class="mt-4 text-left">
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm">
                                <div class="flex">
                                    <flux:icon.information-circle class="h-4 w-4 text-yellow-600 mt-0.5 mr-2 flex-shrink-0" />
                                    <div class="text-yellow-800">
                                        <p class="font-medium mb-1">To create a subscription:</p>
                                        <ul class="list-disc ml-4 space-y-1">
                                            @if(!$hasFeeSettings)
                                                <li>Set up course fee settings and sync with Stripe in the Course edit page</li>
                                            @endif
                                            @if(!$hasPaymentMethod)
                                                <li>Student needs to add a valid payment method (card)</li>
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </flux:card>
        @endif
        
        <!-- Manual Payment Management -->
        @if($enrollment->isManualPaymentType())
            <flux:card>
                <flux:heading size="lg">Manual Payment Management</flux:heading>
                <flux:text class="mt-2 text-gray-600">
                    This enrollment is set up for manual payments. Manage payment orders and approvals here.
                </flux:text>
                
                <div class="mt-6">
                    <!-- Payment Method Info -->
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg mb-6">
                        <div class="flex items-start">
                            <flux:icon icon="banknotes" class="w-5 h-5 text-amber-600 mr-3 mt-0.5" />
                            <div>
                                <flux:text class="text-amber-800 font-medium">Payment Method: {{ $enrollment->getPaymentMethodLabel() }}</flux:text>
                                <flux:text class="mt-1 text-sm text-amber-700">
                                    Student will pay manually via bank transfer, cash, or other offline methods.
                                </flux:text>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                        @if(!$enrollment->stripe_subscription_id && $enrollment->course->feeSettings && $enrollment->course->feeSettings->billing_cycle !== 'one_time')
                            <flux:button wire:click="createManualSubscription" variant="primary" icon="plus">
                                Create Manual Subscription
                            </flux:button>
                        @endif

                        @if($enrollment->stripe_subscription_id)
                            <flux:button wire:click="openCreateManualOrderModal" variant="outline" icon="document-plus">
                                Generate Payment Order
                            </flux:button>
                        @endif

                        @if($enrollment->canSwitchPaymentMethod())
                            <div class="space-y-3">
                                @php $paymentMethodsDetails = $this->paymentMethodsDetails; @endphp
                                
                                <!-- Payment Method Readiness Status -->
                                <div class="p-4 border rounded-lg {{ $paymentMethodsDetails['ready'] ? 'bg-green-50 border-green-200' : 'bg-blue-50 border-blue-200' }}">
                                    <div class="flex items-start">
                                        @if($paymentMethodsDetails['ready'])
                                            <flux:icon icon="check-circle" class="w-5 h-5 text-green-600 mr-3 mt-0.5" />
                                        @else
                                            <flux:icon icon="information-circle" class="w-5 h-5 text-blue-600 mr-3 mt-0.5" />
                                        @endif
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between">
                                                <flux:text class="{{ $paymentMethodsDetails['ready'] ? 'text-green-800' : 'text-blue-800' }} font-medium">
                                                    Automatic Payment Readiness
                                                </flux:text>
                                                @if($paymentMethodsDetails['ready'])
                                                    <flux:badge variant="success" size="sm">Ready</flux:badge>
                                                @else
                                                    <flux:badge variant="warning" size="sm">Not Ready</flux:badge>
                                                @endif
                                            </div>
                                            
                                            <div class="mt-2 space-y-1">
                                                <flux:text class="{{ $paymentMethodsDetails['ready'] ? 'text-green-700' : 'text-blue-700' }} text-sm font-medium">
                                                    Status: {{ $paymentMethodsDetails['status'] }}
                                                </flux:text>
                                                <flux:text class="{{ $paymentMethodsDetails['ready'] ? 'text-green-600' : 'text-blue-600' }} text-xs">
                                                    {{ $paymentMethodsDetails['message'] }}
                                                </flux:text>
                                            </div>

                                            @if($paymentMethodsDetails['has_customer'])
                                                <div class="mt-3 text-xs {{ $paymentMethodsDetails['ready'] ? 'text-green-600' : 'text-blue-600' }}">
                                                    <div class="flex items-center justify-between">
                                                        <span>Stripe Customer:</span>
                                                        <flux:badge variant="outline" size="xs">✓ Connected</flux:badge>
                                                    </div>
                                                    <div class="flex items-center justify-between mt-1">
                                                        <span>Payment Methods:</span>
                                                        <span class="font-medium">{{ $paymentMethodsDetails['payment_method_count'] ?? 0 }} method(s)</span>
                                                    </div>
                                                    <div class="flex items-center justify-between mt-1">
                                                        <span>Can Switch Payment:</span>
                                                        <span class="font-medium">{{ $enrollment->canSwitchPaymentMethod() ? 'Yes' : 'No' }}</span>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="mt-3 text-xs text-blue-600">
                                                    <div class="flex items-center justify-between">
                                                        <span>Stripe Customer:</span>
                                                        <flux:badge variant="danger" size="xs">✗ Not Set Up</flux:badge>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                @if($enrollment->canSwitchToAutomatic())
                                    <flux:button wire:click="switchToAutomaticPayments" variant="primary" icon="credit-card">
                                        Switch to Automatic Payment
                                    </flux:button>
                                @else
                                    <flux:button wire:click="switchToAutomaticPayments" variant="outline" icon="credit-card" 
                                                class="{{ !$paymentMethodsDetails['ready'] ? 'opacity-75' : '' }}">
                                        Switch to Automatic Payment
                                    </flux:button>
                                    @if(!$paymentMethodsDetails['ready'])
                                        <flux:text class="text-xs text-gray-500 mt-1">
                                            ⚠️ This button will show an error until the student adds a payment method.
                                        </flux:text>
                                    @endif
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Manual Orders List -->
                    @if($enrollment->orders->where('billing_reason', 'manual')->isNotEmpty())
                        <div class="space-y-4">
                            <flux:heading size="md">Manual Payment Orders</flux:heading>
                            
                            <div class="space-y-3">
                                @foreach($enrollment->orders->where('billing_reason', 'manual')->sortByDesc('created_at') as $order)
                                    <div class="border rounded-lg p-4 {{ $order->isPaid() ? 'bg-green-50 border-green-200' : ($order->isFailed() ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200') }}">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="flex items-center space-x-2">
                                                    <a href="{{ route('orders.show', $order) }}" class="font-medium text-blue-600 hover:text-blue-800 hover:underline">
                                                        Order #{{ $order->order_number }}
                                                    </a>
                                                    <flux:badge variant="{{ $order->isPaid() ? 'success' : ($order->isFailed() ? 'danger' : 'warning') }}">
                                                        {{ $order->status_label }}
                                                    </flux:badge>
                                                </div>
                                                <flux:text size="sm" class="text-gray-600 mt-1">
                                                    Amount: {{ $order->formatted_amount }} • 
                                                    Created: {{ $order->created_at->format('M d, Y H:i') }}
                                                    @if($order->period_start && $order->period_end)
                                                        • Period: {{ $order->period_start->format('M d') }} - {{ $order->period_end->format('M d, Y') }}
                                                    @endif
                                                </flux:text>
                                                @if($order->notes)
                                                    <flux:text size="sm" class="text-gray-500 mt-1">{{ $order->notes }}</flux:text>
                                                @endif
                                            </div>
                                            
                                            <div class="flex items-center space-x-2">
                                                @if($order->isPending())
                                                    <flux:button 
                                                        wire:click="openApprovalModal({{ $order->id }})" 
                                                        size="sm" 
                                                        variant="primary"
                                                        color="green"
                                                        icon="check">
                                                        Approve Payment
                                                    </flux:button>
                                                @elseif($order->isPaid())
                                                    <div class="flex items-center space-x-2">
                                                        <div class="flex items-center text-green-600">
                                                            <flux:icon icon="check-circle" class="w-4 h-4 mr-1" />
                                                            <flux:text size="sm">Paid</flux:text>
                                                        </div>
                                                        
                                                        @if($this->hasReceiptAttachment($order))
                                                            <flux:button 
                                                                wire:click="downloadReceipt({{ $order->id }})" 
                                                                size="sm" 
                                                                variant="ghost"
                                                                icon="document">
                                                                Receipt
                                                            </flux:button>
                                                            <a href="{{ $this->getReceiptUrl($order) }}" target="_blank">
                                                                <flux:button 
                                                                    size="sm" 
                                                                    variant="ghost"
                                                                    icon="magnifying-glass">
                                                                    View
                                                                </flux:button>
                                                            </a>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <flux:icon icon="document-text" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                            <flux:heading size="md" class="text-gray-600 mb-2">No Payment Orders Yet</flux:heading>
                            <flux:text class="text-gray-500 mb-4">
                                Generate a payment order to start collecting manual payments from the student.
                            </flux:text>
                        </div>
                    @endif

                    @if($enrollment->requiresManualPayment())
                        <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-start">
                                <flux:icon icon="information-circle" class="w-5 h-5 text-blue-600 mr-3 mt-0.5" />
                                <div>
                                    <flux:text class="text-blue-800 font-medium">Action Required</flux:text>
                                    <flux:text class="mt-1 text-sm text-blue-700">
                                        This enrollment requires manual payment approval before it can be activated.
                                    </flux:text>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </flux:card>
        @else
            <!-- Automatic Payment Management -->
            <flux:card>
                <flux:heading size="lg">Automatic Payment Management</flux:heading>
                <flux:text class="mt-2 text-gray-600">
                    This enrollment is set up for automatic payments. Payments are processed automatically via the student's saved payment method.
                </flux:text>
                
                <div class="mt-6">
                    <!-- Payment Method Info -->
                    <div class="p-4 bg-green-50 border border-green-200 rounded-lg mb-6">
                        <div class="flex items-start">
                            <flux:icon icon="credit-card" class="w-5 h-5 text-green-600 mr-3 mt-0.5" />
                            <div>
                                <flux:text class="text-green-800 font-medium">Payment Method: {{ $enrollment->getPaymentMethodLabel() }}</flux:text>
                                <flux:text class="mt-1 text-sm text-green-700">
                                    Payments are automatically charged to the student's saved payment method.
                                </flux:text>
                            </div>
                        </div>
                    </div>

                    <!-- Switch to Manual Payment Section -->
                    @if($enrollment->canSwitchToManual())
                        <div class="space-y-3">
                            <!-- Manual Payment Switching Status -->
                            <div class="p-4 border rounded-lg bg-amber-50 border-amber-200">
                                <div class="flex items-start">
                                    <flux:icon icon="exclamation-triangle" class="w-5 h-5 text-amber-600 mr-3 mt-0.5" />
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <flux:text class="text-amber-800 font-medium">
                                                Switch to Manual Payment
                                            </flux:text>
                                            <flux:badge variant="warning" size="sm">Available</flux:badge>
                                        </div>
                                        
                                        <div class="mt-2 space-y-1">
                                            <flux:text class="text-amber-700 text-sm">
                                                You can switch this enrollment to manual payment processing. This will:
                                            </flux:text>
                                            <ul class="text-xs text-amber-600 ml-4 space-y-1 list-disc">
                                                <li>Pause automatic collection in Stripe</li>
                                                <li>Require manual payment orders to be generated</li>
                                                <li>Require admin approval for each payment</li>
                                                <li>Allow payment via bank transfer, cash, or other offline methods</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Button -->
                            <div class="flex justify-start">
                                <flux:button wire:click="switchToManualPayments" variant="outline" icon="banknotes">
                                    Switch to Manual Payment
                                </flux:button>
                            </div>
                        </div>
                    @else
                        <div class="p-4 border rounded-lg bg-gray-50 border-gray-200">
                            <div class="flex items-start">
                                <flux:icon icon="information-circle" class="w-5 h-5 text-gray-600 mr-3 mt-0.5" />
                                <div>
                                    <flux:text class="text-gray-800 font-medium">Manual Payment Not Available</flux:text>
                                    <flux:text class="mt-1 text-sm text-gray-600">
                                        Payment method switching is not available at this time due to business rules or active billing cycles.
                                    </flux:text>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Quick Actions -->
                    <div class="mt-6 flex justify-start">
                        <flux:button size="sm" variant="ghost" href="{{ route('admin.students.payment-methods', $enrollment->student) }}" icon="credit-card">
                            Manage Payment Methods
                        </flux:button>
                    </div>
                </div>
            </flux:card>
        @endif
        
        <!-- Enrollment Details -->
        <flux:card>
            <flux:heading size="lg">Enrollment Information</flux:heading>
            
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <p class="text-sm font-medium text-gray-500">Enrollment Date</p>
                    <p class="text-sm text-gray-900">{{ $enrollment->enrollment_date->format('M d, Y') }}</p>
                </div>
                
                @if($enrollment->start_date)
                    <div>
                        <p class="text-sm font-medium text-gray-500">Start Date</p>
                        <p class="text-sm text-gray-900">{{ $enrollment->start_date->format('M d, Y') }}</p>
                    </div>
                @endif
                
                @if($enrollment->end_date)
                    <div>
                        <p class="text-sm font-medium text-gray-500">End Date</p>
                        <p class="text-sm text-gray-900">{{ $enrollment->end_date->format('M d, Y') }}</p>
                    </div>
                @endif
                
                @if($enrollment->completion_date)
                    <div>
                        <p class="text-sm font-medium text-gray-500">Completion Date</p>
                        <p class="text-sm text-gray-900">{{ $enrollment->completion_date->format('M d, Y') }}</p>
                    </div>
                @endif
                
                <div>
                    <p class="text-sm font-medium text-gray-500">Enrollment Fee</p>
                    <p class="text-sm text-gray-900">{{ $enrollment->formatted_enrollment_fee }}</p>
                </div>
                
                <div>
                    <p class="text-sm font-medium text-gray-500">Enrolled By</p>
                    <p class="text-sm text-gray-900">{{ $enrollment->enrolledBy->name }}</p>
                </div>

                @if($enrollment->duration)
                    <div>
                        <p class="text-sm font-medium text-gray-500">Duration</p>
                        <p class="text-sm text-gray-900">{{ $enrollment->duration }} days</p>
                    </div>
                @endif
            </div>
        </flux:card>

        <!-- Payment Report -->
        @if($enrollment->stripe_subscription_id)
            @php
                $paymentReport = $this->getPaymentReportData();
            @endphp

            @if($paymentReport->isNotEmpty())
                <flux:card>
                    <flux:heading size="lg">Subscription Payment Report</flux:heading>
                    <flux:text class="mt-2 text-gray-600">
                        Payment periods based on subscription billing cycle with status tracking
                    </flux:text>

                    <div class="mt-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Range</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($paymentReport as $period)
                                        <tr class="{{ $period['is_current'] ? 'bg-blue-50' : '' }}">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    @if($period['is_current'])
                                                        <flux:icon.arrow-right class="w-4 h-4 text-blue-600 mr-2" />
                                                    @endif
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ $period['period_name'] }}
                                                        @if($period['is_current'])
                                                            <span class="text-xs text-blue-600 ml-1">(Current)</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $period['period_start']->format('M d') }} - {{ $period['period_end']->format('M d, Y') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                RM {{ number_format($period['amount'], 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($period['status'] === 'paid')
                                                    <flux:badge variant="filled" color="green" size="sm">Paid</flux:badge>
                                                @elseif($period['status'] === 'failed')
                                                    <flux:badge variant="filled" color="red" size="sm">Failed</flux:badge>
                                                @elseif($period['status'] === 'pending')
                                                    <flux:badge variant="filled" color="orange" size="sm">Pending</flux:badge>
                                                @elseif($period['status'] === 'upcoming')
                                                    <flux:badge variant="filled" color="blue" size="sm">Upcoming</flux:badge>
                                                @else
                                                    <flux:badge variant="outline" size="sm">Unpaid</flux:badge>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                @if($period['order'] && $period['order']->paid_at)
                                                    {{ $period['order']->paid_at->format('M d, Y') }}
                                                @elseif($period['status'] === 'upcoming')
                                                    <span class="text-gray-400">{{ $period['period_end']->addDay()->format('M d, Y') }}</span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @php
                            $paidCount = $paymentReport->where('status', 'paid')->count();
                            $failedCount = $paymentReport->where('status', 'failed')->count();
                            $upcomingCount = $paymentReport->where('status', 'upcoming')->count();
                            $totalAmount = $paymentReport->where('status', 'paid')->sum('amount');
                        @endphp

                        <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="text-sm font-medium text-green-800">Paid Periods</div>
                                <div class="text-2xl font-bold text-green-900">{{ $paidCount }}</div>
                                <div class="text-xs text-green-600">RM {{ number_format($totalAmount, 2) }} collected</div>
                            </div>

                            @if($failedCount > 0)
                                <div class="bg-red-50 p-4 rounded-lg">
                                    <div class="text-sm font-medium text-red-800">Failed Periods</div>
                                    <div class="text-2xl font-bold text-red-900">{{ $failedCount }}</div>
                                    <div class="text-xs text-red-600">Requires attention</div>
                                </div>
                            @endif

                            @if($upcomingCount > 0)
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <div class="text-sm font-medium text-blue-800">Upcoming Periods</div>
                                    <div class="text-2xl font-bold text-blue-900">{{ $upcomingCount }}</div>
                                    <div class="text-xs text-blue-600">Future payments</div>
                                </div>
                            @endif

                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="text-sm font-medium text-gray-800">Total Periods</div>
                                <div class="text-2xl font-bold text-gray-900">{{ $paymentReport->count() }}</div>
                                <div class="text-xs text-gray-600">Billing cycles</div>
                            </div>
                        </div>
                    </div>
                </flux:card>
            @endif
        @endif

        <!-- Payment History -->
        @if($enrollment->orders->isNotEmpty())
            <flux:card>
                <flux:heading size="lg">Payment History</flux:heading>
                
                <div class="mt-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order #</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Receipt</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($enrollment->orders->take(10) as $order)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                            <a href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $order->created_at->format('M d, Y') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $order->formatted_amount }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <flux:badge :variant="match($order->status) {
                                                'paid' => 'success',
                                                'pending' => 'warning',
                                                'failed' => 'danger',
                                                'refunded' => 'outline',
                                                'void' => 'neutral',
                                                default => 'outline'
                                            }" size="sm">
                                                {{ $order->status_label }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <div class="flex items-center">
                                                @if($order->payment_method === 'stripe')
                                                    <flux:icon.credit-card class="w-4 h-4 mr-2 text-blue-500" />
                                                    <span>{{ $order->payment_method_label }}</span>
                                                @else
                                                    <flux:icon.banknotes class="w-4 h-4 mr-2 text-green-500" />
                                                    <span>{{ $order->payment_method_label }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if($order->period_start && $order->period_end)
                                                {{ $order->getPeriodDescription() }}
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($order->receipt_url)
                                                <flux:button size="xs" variant="ghost" href="{{ $order->receipt_url }}" target="_blank">
                                                    View Receipt
                                                </flux:button>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    @if($enrollment->orders->count() > 10)
                        <div class="mt-4 text-center">
                            <flux:button size="sm" variant="ghost" href="{{ route('orders.index', ['enrollment' => $enrollment->id]) }}">
                                View All {{ $enrollment->orders->count() }} Orders
                            </flux:button>
                        </div>
                    @endif
                    
                    @if($enrollment->orders->isEmpty())
                        <div class="text-center py-8">
                            <flux:icon.document-currency-dollar class="mx-auto h-12 w-12 text-gray-400" />
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No Payment History</h3>
                            <p class="mt-1 text-sm text-gray-500">No payments have been recorded for this enrollment yet.</p>
                        </div>
                    @endif
                </div>
            </flux:card>
        @endif

        <!-- Notes -->
        @if($enrollment->notes)
            <flux:card>
                <flux:heading size="lg">Notes</flux:heading>
                
                <div class="mt-6">
                    <p class="text-sm text-gray-900">{{ $enrollment->notes }}</p>
                </div>
            </flux:card>
        @endif

        <!-- Progress Data -->
        @if($enrollment->progress_data)
            <flux:card>
                <flux:heading size="lg">Progress Data</flux:heading>
                
                <div class="mt-6">
                    <pre class="text-sm text-gray-900 bg-gray-50 p-4 rounded-lg overflow-auto">{{ json_encode($enrollment->progress_data, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </flux:card>
        @endif

        <!-- Subscription Management (Temporarily Disabled) -->

        <!-- Course Billing Information -->
        @if($enrollment->course->feeSettings)
            <flux:card>
                <flux:heading size="lg">Course Billing Information</flux:heading>
                
                <div class="mt-6">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <flux:text class="text-gray-600">Amount</flux:text>
                            <flux:text class="font-medium">{{ $enrollment->course->feeSettings?->formatted_fee ?? 'N/A' }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="text-gray-600">Billing Cycle</flux:text>
                            <flux:text class="font-medium">{{ $enrollment->course->feeSettings?->billing_cycle_label ?? 'N/A' }}</flux:text>
                        </div>
                        @if($enrollment->course->feeSettings?->stripe_price_id)
                            <div>
                                <flux:text class="text-gray-600">Stripe Price ID</flux:text>
                                <flux:text size="xs" class="font-mono text-gray-500">{{ $enrollment->course->feeSettings?->stripe_price_id }}</flux:text>
                            </div>
                        @endif
                    </div>
                </div>
            </flux:card>
        @endif

        <!-- Timeline & Subscription Log -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Enrollment Timeline -->
            <flux:card>
                <flux:heading size="lg">Timeline</flux:heading>
                
                <div class="mt-6">
                    <div class="flow-root">
                        <ul role="list" class="-mb-8">
                            <li>
                                <div class="relative pb-8">
                                    <div class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"></div>
                                    <div class="relative flex space-x-3">
                                        <div>
                                            <span class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center ring-8 ring-white">
                                                <flux:icon.plus class="h-4 w-4 text-white" />
                                            </span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div>
                                                <p class="text-sm text-gray-900">Enrolled by {{ $enrollment->enrolledBy->name }}</p>
                                                <p class="text-sm text-gray-500">{{ $enrollment->enrollment_date->format('M d, Y \a\t g:i A') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            
                            @if($enrollment->start_date)
                                <li>
                                    <div class="relative pb-8">
                                        <div class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"></div>
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full bg-green-500 flex items-center justify-center ring-8 ring-white">
                                                    <flux:icon.play class="h-4 w-4 text-white" />
                                                </span>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div>
                                                    <p class="text-sm text-gray-900">Course started</p>
                                                    <p class="text-sm text-gray-500">{{ $enrollment->start_date->format('M d, Y') }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endif
                            
                            @if($enrollment->completion_date)
                                <li>
                                    <div class="relative">
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full bg-emerald-500 flex items-center justify-center ring-8 ring-white">
                                                    <flux:icon.check class="h-4 w-4 text-white" />
                                                </span>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div>
                                                    <p class="text-sm text-gray-900">Course completed</p>
                                                    <p class="text-sm text-gray-500">{{ $enrollment->completion_date->format('M d, Y') }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </flux:card>

            <!-- Subscription Log -->
            <flux:card>
                <flux:heading size="lg">Subscription Log</flux:heading>
                
                <div class="mt-6">
                    @if($subscriptionEvents && $subscriptionEvents->isNotEmpty())
                        <div class="flow-root">
                            <ul role="list" class="-mb-8">
                                @foreach($subscriptionEvents as $event)
                                    <li>
                                        <div class="relative pb-8">
                                            @if(!$loop->last)
                                                <div class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"></div>
                                            @endif
                                            <div class="relative flex space-x-3">
                                                <div>
                                                    @php
                                                        $iconClass = match(true) {
                                                            str_contains($event->type, 'subscription.created') => 'bg-blue-500',
                                                            str_contains($event->type, 'payment_succeeded') => 'bg-green-500',
                                                            str_contains($event->type, 'payment_failed') => 'bg-red-500',
                                                            str_contains($event->type, 'subscription.updated') => 'bg-yellow-500',
                                                            str_contains($event->type, 'subscription.deleted') => 'bg-red-500',
                                                            str_contains($event->type, 'paused') => 'bg-gray-500',
                                                            str_contains($event->type, 'resumed') => 'bg-green-500',
                                                            default => 'bg-gray-400',
                                                        };
                                                        $icon = match(true) {
                                                            str_contains($event->type, 'subscription.created') => 'plus',
                                                            str_contains($event->type, 'payment_succeeded') => 'check',
                                                            str_contains($event->type, 'payment_failed') => 'x-mark',
                                                            str_contains($event->type, 'subscription.updated') => 'arrow-path',
                                                            str_contains($event->type, 'subscription.deleted') => 'x-mark',
                                                            str_contains($event->type, 'paused') => 'pause',
                                                            str_contains($event->type, 'resumed') => 'play',
                                                            default => 'question-mark-circle',
                                                        };
                                                    @endphp
                                                    <span class="h-8 w-8 rounded-full {{ $iconClass }} flex items-center justify-center ring-8 ring-white">
                                                        <flux:icon :name="$icon" class="h-4 w-4 text-white" />
                                                    </span>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div>
                                                        <div class="flex items-center gap-2">
                                                            <p class="text-sm text-gray-900">{{ ucwords(str_replace(['_', '.'], ' ', $event->type)) }}</p>
                                                            @if($event->processed)
                                                                <flux:badge size="sm" class="badge-green">Processed</flux:badge>
                                                            @else
                                                                <flux:badge size="sm" class="badge-gray">Pending</flux:badge>
                                                            @endif
                                                        </div>
                                                        <p class="text-sm text-gray-500">{{ $event->created_at->format('M d, Y g:i A') }}</p>
                                                        @if($event->error_message)
                                                            <p class="text-xs text-red-600 mt-1">{{ $event->error_message }}</p>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <flux:icon.document-text class="mx-auto h-12 w-12 text-gray-400" />
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No Subscription Events</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                @if($enrollment->stripe_subscription_id)
                                    No webhook events recorded for this subscription yet.
                                @else
                                    This enrollment doesn't have an active subscription.
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
            </flux:card>
        </div>

    </div>

    <!-- Create Manual Order Modal -->
    <flux:modal wire:model="showCreateManualOrderModal" class="space-y-6">
        <div>
            <flux:heading size="lg">Generate Manual Payment Order</flux:heading>
            <flux:subheading>Create a payment order for manual processing</flux:subheading>
        </div>
        
        <div class="space-y-4">
            <flux:text>
                This will generate a new payment order for the student to pay manually. 
                The order will include payment instructions and can be tracked until payment is received.
            </flux:text>
            
            <div class="p-4 bg-gray-50 rounded-lg">
                <flux:text class="font-medium">Order Details:</flux:text>
                <ul class="mt-2 text-sm text-gray-600 space-y-1">
                    <li>• Amount: {{ $enrollment->formatted_enrollment_fee }}</li>
                    <li>• Student: {{ $enrollment->student->user->name }}</li>
                    <li>• Course: {{ $enrollment->course->name }}</li>
                    <li>• Payment Method: Manual</li>
                </ul>
            </div>
        </div>
        
        <div class="flex justify-end space-x-3">
            <flux:button wire:click="closeCreateManualOrderModal" variant="ghost">
                Cancel
            </flux:button>
            <flux:button wire:click="generateManualPaymentOrder" variant="primary">
                Generate Order
            </flux:button>
        </div>
    </flux:modal>

    <!-- Schedule Management Modal -->
    <flux:modal wire:model="showScheduleModal" class="space-y-6">
        <div>
            <flux:heading size="lg">Manage Subscription Schedule</flux:heading>
            <flux:subheading>Configure billing schedule, trial periods, and subscription timing</flux:subheading>
        </div>

        <form wire:submit="updateSubscriptionSchedule" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Payment Schedule Section -->
                <div class="space-y-4">
                    <flux:heading size="md">Payment Schedule</flux:heading>
                    
                    <flux:field>
                        <flux:label>Payment Frequency</flux:label>
                        <flux:select wire:model="scheduleForm.proration_behavior">
                            <option value="create_prorations">Monthly (with proration)</option>
                            <option value="none">Monthly (no proration)</option>
                            <option value="always_invoice">Monthly (always invoice)</option>
                        </flux:select>
                        <flux:error name="scheduleForm.proration_behavior" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Billing Cycle Start Date</flux:label>
                        <flux:input 
                            type="date" 
                            wire:model="scheduleForm.billing_cycle_anchor" 
                            placeholder="YYYY-MM-DD"
                        />
                        <flux:error name="scheduleForm.billing_cycle_anchor" />
                        <flux:description>For existing subscriptions: Today's date will reset billing cycle to now. Future dates are not supported by Stripe's API.</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Next Payment Date</flux:label>
                        <flux:input 
                            type="date" 
                            wire:model="scheduleForm.next_payment_date" 
                            placeholder="YYYY-MM-DD"
                        />
                        <flux:error name="scheduleForm.next_payment_date" />
                        <flux:description>Directly set when the next payment should occur. This overrides billing cycle calculations.</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Next Payment Time</flux:label>
                        <flux:input 
                            type="time" 
                            wire:model="scheduleForm.next_payment_time" 
                            placeholder="HH:MM"
                        />
                        <flux:error name="scheduleForm.next_payment_time" />
                        <flux:description>Time for the next payment (24-hour format).</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Subscription Fee (RM)</flux:label>
                        <flux:input 
                            type="number" 
                            step="0.01" 
                            min="0.01" 
                            wire:model="scheduleForm.subscription_fee" 
                            placeholder="0.00"
                        />
                        <flux:error name="scheduleForm.subscription_fee" />
                        <flux:description>Update the monthly subscription fee for this enrollment. Changes will be applied immediately with proration.</flux:description>
                    </flux:field>

                </div>

                <!-- Trial & End Date Section -->
                <div class="space-y-4">
                    <flux:heading size="md">Trial & End Date</flux:heading>
                    
                    <flux:field>
                        <flux:label>Trial End Date</flux:label>
                        <flux:input 
                            type="date" 
                            wire:model="scheduleForm.trial_end_at" 
                            placeholder="YYYY-MM-DD"
                        />
                        <flux:error name="scheduleForm.trial_end_at" />
                        <flux:description>When should the trial period end? Leave empty if no trial.</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Subscription End Date</flux:label>
                        <flux:input 
                            type="date" 
                            wire:model="scheduleForm.end_date" 
                            placeholder="YYYY-MM-DD"
                        />
                        <flux:error name="scheduleForm.end_date" />
                        <flux:description>When should the subscription automatically end? Leave empty for no end date.</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>End Time</flux:label>
                        <flux:input 
                            type="time" 
                            wire:model="scheduleForm.end_time" 
                            placeholder="HH:MM"
                        />
                        <flux:error name="scheduleForm.end_time" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Timezone</flux:label>
                        <flux:select wire:model="scheduleForm.subscription_timezone">
                            <option value="Asia/Kuala_Lumpur">Asia/Kuala_Lumpur (MYT)</option>
                            <option value="UTC">UTC</option>
                            <option value="America/New_York">America/New_York (EST/EDT)</option>
                            <option value="Europe/London">Europe/London (GMT/BST)</option>
                            <option value="Asia/Singapore">Asia/Singapore (SGT)</option>
                            <option value="Australia/Sydney">Australia/Sydney (AEDT/AEST)</option>
                        </flux:select>
                        <flux:error name="scheduleForm.subscription_timezone" />
                    </flux:field>
                </div>
            </div>

            <!-- Current Schedule Preview -->
            @if($enrollment->stripe_subscription_id)
                <div class="bg-gray-50 rounded-lg p-4">
                    <flux:heading size="sm" class="mb-3">Current Schedule Preview</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="font-medium">Status:</span>
                            <span class="ml-2">{{ $enrollment->getSubscriptionStatusLabel() }}</span>
                        </div>
                        <div>
                            <span class="font-medium">Next Payment:</span>
                            <span class="ml-2">{{ $enrollment->getFormattedNextPaymentDate() ?? 'Not scheduled' }}</span>
                        </div>
                        @if($enrollment->isInTrial())
                            <div>
                                <span class="font-medium">Trial Ends:</span>
                                <span class="ml-2">{{ $enrollment->getFormattedTrialEnd() ?? 'No trial' }}</span>
                            </div>
                        @endif
                        @if($enrollment->subscription_cancel_at)
                            <div>
                                <span class="font-medium">Cancellation Date:</span>
                                <span class="ml-2">{{ $enrollment->getFormattedCancellationDate() }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <flux:button variant="ghost" type="button" wire:click="$set('showScheduleModal', false)">
                    Cancel
                </flux:button>
                
                <flux:button 
                    variant="primary" 
                    type="submit"
                    wire:confirm="Are you sure you want to update the subscription schedule? This will affect billing and payments."
                >
                    Update
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Subscription Creation Modal -->
    <flux:modal wire:model="showCreateSubscriptionModal" class="space-y-6">
        <div>
            <flux:heading size="lg">Create New Subscription</flux:heading>
            <flux:subheading>Configure subscription settings before creating</flux:subheading>
        </div>

        <form wire:submit="createSubscriptionWithOptions" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Subscription Settings Section -->
                <div class="space-y-4">
                    <flux:heading size="md">Subscription Settings</flux:heading>
                    
                    <flux:field>
                        <flux:label>Payment Method</flux:label>
                        <flux:select wire:model="createForm.payment_method_id">
                            @foreach($enrollment->student->user->paymentMethods()->active()->get() as $paymentMethod)
                                <option value="{{ $paymentMethod->id }}">{{ $paymentMethod->display_name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="createForm.payment_method_id" />
                        <flux:description>Select which payment method to use for this subscription.</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Proration Behavior</flux:label>
                        <flux:select wire:model="createForm.proration_behavior">
                            <option value="create_prorations">Prorate charges (recommended)</option>
                            <option value="none">No proration</option>
                            <option value="always_invoice">Always create invoice</option>
                        </flux:select>
                        <flux:error name="createForm.proration_behavior" />
                        <flux:description>How to handle partial month billing.</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Subscription Fee (RM)</flux:label>
                        <flux:input 
                            type="number" 
                            step="0.01" 
                            min="0.01" 
                            wire:model="createForm.subscription_fee" 
                            placeholder="0.00"
                        />
                        <flux:error name="createForm.subscription_fee" />
                        <flux:description>Set a custom monthly fee for this subscription. Leave blank to use the default course fee ({{ $enrollment->course->formatted_fee ?? 'N/A' }}).</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Start Date</flux:label>
                        <flux:input 
                            type="date" 
                            wire:model="createForm.start_date" 
                            placeholder="YYYY-MM-DD"
                        />
                        <flux:error name="createForm.start_date" />
                        <flux:description>When should the subscription billing start?</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Start Time</flux:label>
                        <flux:input 
                            type="time" 
                            wire:model="createForm.start_time" 
                            placeholder="HH:MM"
                        />
                        <flux:error name="createForm.start_time" />
                        <flux:description>Time for billing start (24-hour format).</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Next Payment Date</flux:label>
                        <flux:input 
                            type="date" 
                            wire:model="createForm.next_payment_date" 
                            placeholder="YYYY-MM-DD"
                        />
                        <flux:error name="createForm.next_payment_date" />
                        <flux:description>Set when the first payment should occur. This overrides the start date for payment timing.</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Next Payment Time</flux:label>
                        <flux:input 
                            type="time" 
                            wire:model="createForm.next_payment_time" 
                            placeholder="HH:MM"
                        />
                        <flux:error name="createForm.next_payment_time" />
                        <flux:description>Time for the first payment (24-hour format).</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Timezone</flux:label>
                        <flux:select wire:model="createForm.subscription_timezone">
                            <option value="Asia/Kuala_Lumpur">Asia/Kuala_Lumpur (MYT)</option>
                            <option value="UTC">UTC</option>
                            <option value="America/New_York">America/New_York (EST/EDT)</option>
                            <option value="Europe/London">Europe/London (GMT/BST)</option>
                            <option value="Asia/Singapore">Asia/Singapore (SGT)</option>
                            <option value="Australia/Sydney">Australia/Sydney (AEDT/AEST)</option>
                        </flux:select>
                        <flux:error name="createForm.subscription_timezone" />
                    </flux:field>
                </div>

                <!-- Advanced Options Section -->
                <div class="space-y-4">
                    <flux:heading size="md">Advanced Options</flux:heading>
                    
                    <flux:field>
                        <flux:label>Trial End Date (Optional)</flux:label>
                        <flux:input 
                            type="date" 
                            wire:model="createForm.trial_end_at" 
                            placeholder="YYYY-MM-DD"
                        />
                        <flux:error name="createForm.trial_end_at" />
                        <flux:description>Set a trial period before billing begins. Leave empty if no trial needed.</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Billing Anchor Date (Optional)</flux:label>
                        <flux:input 
                            type="date" 
                            wire:model="createForm.billing_cycle_anchor" 
                            placeholder="YYYY-MM-DD"
                        />
                        <flux:error name="createForm.billing_cycle_anchor" />
                        <flux:description>Set a specific billing cycle anchor. Overrides start date if set.</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Subscription End Date (Optional)</flux:label>
                        <flux:input 
                            type="date" 
                            wire:model="createForm.end_date" 
                            placeholder="YYYY-MM-DD"
                        />
                        <flux:error name="createForm.end_date" />
                        <flux:description>Automatically end the subscription on this date. Leave empty for no end date.</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>End Time</flux:label>
                        <flux:input 
                            type="time" 
                            wire:model="createForm.end_time" 
                            placeholder="HH:MM"
                        />
                        <flux:error name="createForm.end_time" />
                        <flux:description>Time for subscription end (24-hour format).</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Notes (Optional)</flux:label>
                        <flux:textarea 
                            wire:model="createForm.notes" 
                            placeholder="Add any notes about this subscription..."
                            rows="3"
                        />
                        <flux:error name="createForm.notes" />
                        <flux:description>Internal notes about this subscription (will be saved to enrollment).</flux:description>
                    </flux:field>
                </div>
            </div>

            <!-- Subscription Preview -->
            @if($enrollment->course->feeSettings)
                <div class="bg-gray-50 rounded-lg p-4">
                    <flux:heading size="sm" class="mb-3">Subscription Preview</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="font-medium">Course:</span>
                            <span class="ml-2">{{ $enrollment->course->name }}</span>
                        </div>
                        <div>
                            <span class="font-medium">Monthly Fee:</span>
                            <span class="ml-2">{{ $enrollment->formatted_enrollment_fee }}</span>
                        </div>
                        <div>
                            <span class="font-medium">Billing Cycle:</span>
                            <span class="ml-2">{{ $enrollment->course->feeSettings->billing_cycle_label }}</span>
                        </div>
                        <div>
                            <span class="font-medium">Student:</span>
                            <span class="ml-2">{{ $enrollment->student->user->name }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <flux:button variant="ghost" type="button" wire:click="$set('showCreateSubscriptionModal', false)">
                    Cancel
                </flux:button>
                
                <flux:button 
                    variant="primary" 
                    type="submit"
                    wire:confirm="Are you sure you want to create this subscription with the configured settings?"
                >
                    Create Subscription
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Payment Approval Modal -->
    <flux:modal wire:model="showApprovalModal" class="space-y-6">
        <div>
            <flux:heading size="lg">Approve Manual Payment</flux:heading>
            <flux:subheading>Confirm payment details and attach receipt (optional)</flux:subheading>
        </div>

        <div class="space-y-4">
            <flux:input 
                type="date" 
                wire:model="paymentDate" 
                label="Payment Date" 
                placeholder="Select payment date"
                required />

            <flux:input 
                type="file" 
                wire:model="receiptFile" 
                label="Receipt" 
                accept="image/*,.pdf"
                placeholder="Attach payment receipt"
                description="Upload payment receipt (required - images or PDF, max 10MB)"
                required />

            @if($receiptFile)
                <div class="mt-2">
                    <flux:text size="sm" class="text-green-600">
                        File selected: {{ $receiptFile->getClientOriginalName() }}
                    </flux:text>
                </div>
            @endif
        </div>

        <div class="flex justify-between">
            <flux:button wire:click="closeApprovalModal" variant="ghost">
                Cancel
            </flux:button>
            <flux:button wire:click="approveManualPayment" variant="primary" color="green">
                <flux:icon icon="check" class="w-4 h-4 mr-1" />
                Approve Payment
            </flux:button>
        </div>
    </flux:modal>
</div>