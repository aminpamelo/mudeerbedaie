<?php

use App\Models\Enrollment;
use App\Services\StripeService;
use Livewire\Volt\Component;

new class extends Component {
    public Enrollment $enrollment;
    public $subscriptionEvents;

    public function mount(): void
    {
        $this->enrollment->load(['student.user', 'course.feeSettings', 'enrolledBy', 'orders']);
        
        // Load subscription events
        $this->refreshSubscriptionEvents();
        
        // Refresh subscription status from Stripe if there's a subscription
        if ($this->enrollment->stripe_subscription_id) {
            $this->refreshSubscriptionStatus();
        }
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
                } else {
                    session()->flash('error', 'Failed to confirm payment: ' . $result['error']);
                }
                
                \Log::warning('Payment confirmation failed', [
                    'enrollment_id' => $this->enrollment->id,
                    'subscription_id' => $this->enrollment->stripe_subscription_id,
                    'error' => $result['error'],
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
                            <p class="text-sm text-gray-900">{{ $enrollment->course->feeSettings?->formatted_fee ?? 'N/A' }}</p>
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
                                    <span class="text-green-600">In 2 days</span>
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
                                @if($enrollment->subscription_status === 'incomplete')
                                    <flux:button size="sm" variant="primary" wire:click="confirmPayment" wire:confirm="Attempt to confirm the payment for this subscription. This may not work if customer authentication is required." icon="credit-card">
                                        Confirm Payment
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
                    
                    @if($enrollment->course->feeSettings && $enrollment->course->feeSettings->stripe_price_id)
                        <div class="mt-6 space-y-3">
                            <flux:button variant="primary" wire:click="createSubscription">
                                Create Subscription
                            </flux:button>
                            <div class="flex space-x-3">
                                <flux:button variant="ghost" size="sm" href="{{ route('admin.students.payment-methods', $enrollment->student) }}" icon="credit-card">
                                    Manage Payment Methods
                                </flux:button>
                                <flux:button variant="ghost" size="sm" href="{{ route('students.show', $enrollment->student) }}">
                                    View Student Profile
                                </flux:button>
                            </div>
                        </div>
                    @else
                        <div class="mt-4">
                            <p class="text-xs text-gray-500">Course must have fee settings configured and synced with Stripe first.</p>
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
                                            <flux:badge :class="match($order->status) {
                                                'paid' => 'badge-green',
                                                'pending' => 'badge-yellow',
                                                'failed' => 'badge-red',
                                                'refunded' => 'badge-gray',
                                                default => 'badge-gray'
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
</div>