<?php

use App\Models\Enrollment;
use App\Services\StripeService;
use Livewire\Volt\Component;

new class extends Component {
    public Enrollment $enrollment;
    public $subscriptionEvents;
    
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
        'end_date' => null,
        'end_time' => null,
        'payment_method_id' => null,
        'notes' => null,
        'subscription_fee' => null,
    ];

    public function mount(): void
    {
        $this->enrollment->load(['student.user', 'course.feeSettings', 'enrolledBy', 'orders']);
        
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

        $this->createForm = [
            'trial_end_at' => null,
            'billing_cycle_anchor' => null,
            'subscription_timezone' => 'Asia/Kuala_Lumpur',
            'proration_behavior' => 'create_prorations',
            'start_date' => now()->format('Y-m-d'),
            'start_time' => '07:23',
            'end_date' => null,
            'end_time' => null,
            'payment_method_id' => $defaultPaymentMethod?->id,
            'notes' => null,
            'subscription_fee' => $this->enrollment->enrollment_fee,
        ];
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
            if (($currentCancelAt && !$cancelAt) || 
                (!$currentCancelAt && $cancelAt) || 
                ($currentCancelAt && $cancelAt && !$currentCancelAt->equalTo($cancelAt))) {
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

    public function syncSubscriptionData(): void
    {
        try {
            if (!$this->enrollment->stripe_subscription_id) {
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
                    if (!$this->enrollment->isCollectionPaused()) {
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
            if (($currentCancelAt && !$cancelAt) || 
                (!$currentCancelAt && $cancelAt) || 
                ($currentCancelAt && $cancelAt && !$currentCancelAt->equalTo($cancelAt))) {
                $this->enrollment->updateSubscriptionCancellation($cancelAt);
            }
            
            // Update trial information if available
            if (isset($details['trial_end']) && $details['trial_end']) {
                $trialEnd = \Carbon\Carbon::createFromTimestamp($details['trial_end']);
                // Update trial end if different
                if (!$this->enrollment->trial_end_at || !$this->enrollment->trial_end_at->equalTo($trialEnd)) {
                    $this->enrollment->update(['trial_end_at' => $trialEnd]);
                }
            } elseif (isset($details['trial_end']) && !$details['trial_end'] && $this->enrollment->trial_end_at) {
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
            
            session()->flash('error', 'Failed to sync subscription data: ' . $e->getMessage());
        }
    }

    public function createSubscription()
    {
        \Log::info('Create subscription button clicked', [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => auth()->user()->id
        ]);

        try {
            \Log::info('Checking course fee settings', [
                'enrollment_id' => $this->enrollment->id,
                'has_fee_settings' => (bool) $this->enrollment->course->feeSettings
            ]);

            if (!$this->enrollment->course->feeSettings) {
                \Log::warning('Course missing fee settings', ['enrollment_id' => $this->enrollment->id]);
                session()->flash('error', 'Course must have fee settings configured first.');
                return;
            }

            \Log::info('Checking Stripe price ID', [
                'enrollment_id' => $this->enrollment->id,
                'stripe_price_id' => $this->enrollment->course->feeSettings->stripe_price_id
            ]);

            if (!$this->enrollment->course->feeSettings->stripe_price_id) {
                \Log::warning('Course missing Stripe price ID', ['enrollment_id' => $this->enrollment->id]);
                session()->flash('error', 'Course must be synced with Stripe first. Go to Course Edit page and click "Sync to Stripe".');
                return;
            }

            // Get student's default payment method
            \Log::info('Looking for student payment methods', [
                'enrollment_id' => $this->enrollment->id,
                'student_id' => $this->enrollment->student->id,
                'user_id' => $this->enrollment->student->user->id
            ]);

            $paymentMethod = $this->enrollment->student->user->paymentMethods()
                ->active()
                ->default()
                ->first();
            
            \Log::info('Payment method check result', [
                'enrollment_id' => $this->enrollment->id,
                'payment_method_found' => (bool) $paymentMethod,
                'payment_method_id' => $paymentMethod?->id
            ]);

            if (!$paymentMethod) {
                \Log::warning('Student has no default payment method', ['enrollment_id' => $this->enrollment->id]);
                session()->flash('warning', 'Student must add a payment method first. You can add one for them or direct them to their Payment Methods page.');
                return;
            }

            // Create subscription using StripeService
            \Log::info('Creating Stripe subscription', [
                'enrollment_id' => $this->enrollment->id,
                'payment_method_id' => $paymentMethod->id
            ]);

            $stripeService = app(StripeService::class);
            $result = $stripeService->createSubscription($this->enrollment, $paymentMethod);
            
            \Log::info('Stripe subscription creation result', [
                'enrollment_id' => $this->enrollment->id,
                'subscription_id' => $result['subscription']->id ?? null
            ]);

            // Refresh enrollment to get updated subscription data
            $this->enrollment->refresh();
            
            \Log::info('Subscription created successfully', [
                'enrollment_id' => $this->enrollment->id,
                'stripe_subscription_id' => $this->enrollment->stripe_subscription_id
            ]);

            session()->flash('success', 'Subscription created successfully! The student will be charged according to the billing cycle.');
            
        } catch (\Exception $e) {
            \Log::error('Failed to create subscription', [
                'enrollment_id' => $this->enrollment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Failed to create subscription: ' . $e->getMessage());
        }
    }

    public function createSubscriptionWithOptions()
    {
        \Log::info('Create subscription with options submitted', [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => auth()->user()->id,
            'form_data' => $this->createForm
        ]);

        $this->validate();

        try {
            // Validate course settings
            if (!$this->enrollment->course->feeSettings || !$this->enrollment->course->feeSettings->stripe_price_id) {
                session()->flash('error', 'Course must have fee settings configured and synced with Stripe.');
                return;
            }

            // Get selected payment method
            $paymentMethod = $this->enrollment->student->user->paymentMethods()
                ->active()
                ->find($this->createForm['payment_method_id']);

            if (!$paymentMethod) {
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
            
            // For now, use the existing createSubscription method with basic options
            // This can be extended later when StripeService supports more options
            $result = $stripeService->createSubscription($this->enrollment, $paymentMethod);
            
            // Store additional metadata if notes provided
            if ($this->createForm['notes']) {
                $this->enrollment->update(['notes' => $this->createForm['notes']]);
            }
            
            // Update enrollment with timezone preference
            $this->enrollment->update([
                'subscription_timezone' => $this->createForm['subscription_timezone']
            ]);

            // Refresh enrollment to get updated subscription data
            $this->enrollment->refresh();
            
            // Close modal
            $this->showCreateSubscriptionModal = false;
            
            \Log::info('Subscription created successfully with options', [
                'enrollment_id' => $this->enrollment->id,
                'stripe_subscription_id' => $this->enrollment->stripe_subscription_id
            ]);

            session()->flash('success', 'Subscription created successfully with your configured options!');
            
        } catch (\Exception $e) {
            \Log::error('Failed to create subscription with options', [
                'enrollment_id' => $this->enrollment->id,
                'error' => $e->getMessage(),
                'form_data' => $this->createForm
            ]);
            session()->flash('error', 'Failed to create subscription: ' . $e->getMessage());
        }
    }

    public function cancelSubscription()
    {
        try {
            if (!$this->enrollment->stripe_subscription_id) {
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
                
                session()->flash('info', $result['message'] . ' The subscription will automatically end at that time and no further charges will occur.');
            }
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to cancel subscription: ' . $e->getMessage());
        }
    }

    public function undoCancellation()
    {
        \Log::info('Undo cancellation button clicked', [
            'enrollment_id' => $this->enrollment->id,
            'subscription_id' => $this->enrollment->stripe_subscription_id,
            'user_id' => auth()->user()->id
        ]);

        try {
            if (!$this->enrollment->stripe_subscription_id) {
                session()->flash('error', 'No subscription found to undo cancellation.');
                return;
            }

            if (!$this->enrollment->isPendingCancellation()) {
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
            
            session()->flash('error', 'Failed to undo cancellation: ' . $e->getMessage());
        }
    }


    public function resumeCanceledSubscription()
    {
        \Log::info('Resume canceled subscription button clicked', [
            'enrollment_id' => $this->enrollment->id,
            'user_id' => auth()->user()->id
        ]);

        try {
            // Verify that subscription is in a resumable state
            if (!in_array($this->enrollment->subscription_status, ['canceled', 'incomplete_expired', 'incomplete'])) {
                session()->flash('error', 'Subscription is not in a state that can be resumed.');
                return;
            }

            // Check course fee settings and Stripe configuration
            if (!$this->enrollment->course->feeSettings) {
                session()->flash('error', 'Course must have fee settings configured first.');
                return;
            }

            if (!$this->enrollment->course->feeSettings->stripe_price_id) {
                session()->flash('error', 'Course must be synced with Stripe first. Go to Course Edit page and click "Sync to Stripe".');
                return;
            }

            // Get student's default payment method
            $paymentMethod = $this->enrollment->student->user->paymentMethods()
                ->active()
                ->default()
                ->first();

            if (!$paymentMethod) {
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
            session()->flash('error', 'Failed to resume subscription: ' . $e->getMessage());
        }
    }

    public function forceRecreateSubscription()
    {
        \Log::info('Force recreate subscription button clicked', [
            'enrollment_id' => $this->enrollment->id,
            'subscription_id' => $this->enrollment->stripe_subscription_id,
            'user_id' => auth()->user()->id
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

            if (!$paymentMethod) {
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
            
            session()->flash('error', 'Failed to recreate subscription: ' . $e->getMessage());
        }
    }

    public function confirmPayment()
    {
        \Log::info('Confirm payment button clicked', [
            'enrollment_id' => $this->enrollment->id,
            'subscription_id' => $this->enrollment->stripe_subscription_id,
            'user_id' => auth()->user()->id
        ]);

        try {
            if (!$this->enrollment->stripe_subscription_id) {
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
                    session()->flash('warning', $result['error'] . ' The customer must complete payment setup themselves.');
                } elseif (isset($result['requires_manual_action']) && $result['requires_manual_action']) {
                    // Payment intent missing and couldn't be recovered - provide detailed guidance
                    $errorMessage = $result['error'];
                    if (isset($result['suggested_actions'])) {
                        $errorMessage .= "\n\nSuggested actions:\n• " . implode("\n• ", $result['suggested_actions']);
                    }
                    session()->flash('error', $errorMessage);
                } else {
                    session()->flash('error', 'Failed to confirm payment: ' . $result['error']);
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
            
            session()->flash('error', 'Failed to confirm payment: ' . $e->getMessage());
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
            if (!$this->enrollment->stripe_subscription_id) {
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
                $nextPaymentDateTime = $this->scheduleForm['next_payment_date'] . ' ' . ($this->scheduleForm['next_payment_time'] ?? '07:23');
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
                $endDateTime = $this->scheduleForm['end_date'] . ' ' . ($this->scheduleForm['end_time'] ?? '23:59');
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
                    session()->flash('error', 'Failed to update subscription fee: ' . $e->getMessage());
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
                    $nextPaymentDateTime = \Carbon\Carbon::parse($this->scheduleForm['next_payment_date'] . ' ' . ($this->scheduleForm['next_payment_time'] ?? '07:23'))
                        ->setTimezone($this->scheduleForm['subscription_timezone']);
                    $this->enrollment->updateNextPaymentDate($nextPaymentDateTime);
                }
                
                $this->enrollment->refresh();
                
                // Refresh subscription events
                $this->refreshSubscriptionEvents();
                
                // Combine messages if fee was updated
                $message = $result['message'];
                if ($feeUpdateResult) {
                    $message .= ' ' . $feeUpdateResult['message'];
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
            session()->flash('error', 'Failed to update subscription schedule: ' . $e->getMessage());
        }
    }

    public function rescheduleNextPayment()
    {
        try {
            if (!$this->enrollment->stripe_subscription_id) {
                session()->flash('error', 'No subscription found to reschedule payment.');
                return;
            }

            if (!$this->scheduleForm['next_payment_date']) {
                session()->flash('error', 'Next payment date is required.');
                return;
            }

            $nextPaymentDateTime = \Carbon\Carbon::parse($this->scheduleForm['next_payment_date'] . ' ' . ($this->scheduleForm['next_payment_time'] ?? '07:23'))
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
            session()->flash('error', 'Failed to reschedule next payment: ' . $e->getMessage());
        }
    }

    public function updateTrialEnd()
    {
        try {
            if (!$this->enrollment->stripe_subscription_id) {
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
            session()->flash('error', 'Failed to update trial end: ' . $e->getMessage());
        }
    }

    public function updateSubscriptionEndDate()
    {
        try {
            if (!$this->enrollment->stripe_subscription_id) {
                session()->flash('error', 'No subscription found to update end date.');
                return;
            }

            $endTimestamp = null;
            if ($this->scheduleForm['end_date']) {
                $endDateTime = $this->scheduleForm['end_date'] . ' ' . ($this->scheduleForm['end_time'] ?? '23:59');
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
            session()->flash('error', 'Failed to update subscription end date: ' . $e->getMessage());
        }
    }
}; ?>

<div>
    <!-- Flash Messages -->
    @if (session('success'))
        <div class="mb-6 rounded-md bg-green-50 p-4 dark:bg-green-900/20">
            <div class="flex">
                <div class="flex-shrink-0">
                    <flux:icon.check-circle class="h-5 w-5 text-green-400" />
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="mb-6 rounded-md bg-red-50 p-4 dark:bg-red-900/20">
            <div class="flex">
                <div class="flex-shrink-0">
                    <flux:icon.x-circle class="h-5 w-5 text-red-400" />
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800 dark:text-red-200">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session('warning'))
        <div class="mb-6 rounded-md bg-yellow-50 p-4 dark:bg-yellow-900/20">
            <div class="flex">
                <div class="flex-shrink-0">
                    <flux:icon.exclamation-triangle class="h-5 w-5 text-yellow-400" />
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">{{ session('warning') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session('info'))
        <div class="mb-6 rounded-md bg-blue-50 p-4 dark:bg-blue-900/20">
            <div class="flex">
                <div class="flex-shrink-0">
                    <flux:icon.information-circle class="h-5 w-5 text-blue-400" />
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-blue-800 dark:text-blue-200">{{ session('info') }}</p>
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
            <flux:badge size="lg" :class="$enrollment->status_badge_class">
                {{ ucfirst($enrollment->status) }}
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

                        <div class="mt-4">
                            <flux:button size="sm" variant="ghost" href="{{ route('students.show', $enrollment->student) }}">
                                View Full Student Profile
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
                                        This subscription is waiting for payment confirmation. Click "Confirm Payment" to attempt automatic processing, or the student can complete payment setup themselves.
                                    </p>
                                </div>
                            @elseif($enrollment->isPendingCancellation())
                                <div class="mt-4">
                                    <div class="rounded-md bg-orange-50 p-3 dark:bg-orange-900/20">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <flux:icon.clock class="h-4 w-4 text-orange-400" />
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-xs text-orange-800 dark:text-orange-200">
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
</div>