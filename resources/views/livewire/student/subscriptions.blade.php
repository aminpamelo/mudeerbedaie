<?php
use App\Models\Enrollment;
use App\Services\StripeService;
use Livewire\Volt\Component;

new class extends Component
{
    public function cancelSubscription($enrollmentId)
    {
        try {
            $enrollment = Enrollment::where('id', $enrollmentId)
                ->where('student_id', auth()->user()->student->id)
                ->firstOrFail();

            if (! $enrollment->stripe_subscription_id) {
                session()->flash('error', 'This enrollment does not have an active subscription.');

                return;
            }

            $stripeService = app(StripeService::class);
            $stripeService->cancelSubscription($enrollment->stripe_subscription_id, false); // Cancel at period end

            session()->flash('success', 'Your subscription will be canceled at the end of the current billing period.');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to cancel subscription: '.$e->getMessage());
        }
    }

    public function updatePaymentMethod($enrollmentId)
    {
        // Redirect to payment methods page with context
        return redirect()->route('student.payment-methods', ['enrollment' => $enrollmentId]);
    }

    public function resumeCollection($enrollmentId)
    {
        try {
            $enrollment = Enrollment::where('id', $enrollmentId)
                ->where('student_id', auth()->user()->student->id)
                ->firstOrFail();

            if (! $enrollment->stripe_subscription_id) {
                session()->flash('error', 'This enrollment does not have an active subscription.');

                return;
            }

            if (! $enrollment->isCollectionPaused()) {
                session()->flash('error', 'Collection is not currently paused.');

                return;
            }

            $stripeService = app(StripeService::class);
            $result = $stripeService->resumeSubscriptionCollection($enrollment->stripe_subscription_id);

            if ($result['success']) {
                // Update local status
                $enrollment->resumeCollection();
                session()->flash('success', 'Collection has been resumed successfully. Future payments will be processed normally.');
            } else {
                session()->flash('error', 'Failed to resume collection. Please try again or contact support.');
            }

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to resume collection: '.$e->getMessage());
        }
    }

    public function resumeSubscription($enrollmentId)
    {
        try {
            $enrollment = Enrollment::where('id', $enrollmentId)
                ->where('student_id', auth()->user()->student->id)
                ->firstOrFail();

            if (! $enrollment->stripe_subscription_id) {
                session()->flash('error', 'This enrollment does not have an active subscription.');

                return;
            }

            if (! $enrollment->isPendingCancellation()) {
                session()->flash('error', 'This subscription is not scheduled for cancellation.');

                return;
            }

            $stripeService = app(StripeService::class);
            $result = $stripeService->undoCancellation($enrollment->stripe_subscription_id);

            if ($result['success']) {
                // Update local status
                $enrollment->updateSubscriptionCancellation(null);
                session()->flash('success', 'Subscription cancellation has been undone. Your subscription will continue normally.');
            } else {
                session()->flash('error', 'Failed to resume subscription. Please try again or contact support.');
            }

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to resume subscription: '.$e->getMessage());
        }
    }

    public function with(): array
    {
        $student = auth()->user()->student;

        // If user doesn't have a student record, return empty collections
        if (! $student) {
            return [
                'activeSubscriptions' => collect([]),
                'canceledSubscriptions' => collect([]),
            ];
        }

        return [
            'activeSubscriptions' => Enrollment::where('student_id', $student->id)
                ->with(['course.feeSettings', 'orders' => function ($q) {
                    $q->latest()->limit(1);
                }])
                ->where(function ($query) {
                    $query->whereIn('subscription_status', ['active', 'trialing', 'past_due'])
                        ->orWhere(function ($q) {
                            $q->whereIn('status', ['enrolled', 'active'])
                                ->whereNotNull('stripe_subscription_id');
                        });
                })
                ->get(),

            'canceledSubscriptions' => Enrollment::where('student_id', $student->id)
                ->with(['course.feeSettings'])
                ->where('subscription_status', 'canceled')
                ->get(),
        ];
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('student.subscriptions.my_subscriptions') }}</flux:heading>
            <flux:text class="mt-2">{{ __('student.subscriptions.manage_billing') }}</flux:text>
        </div>
        <flux:button href="{{ route('student.payment-methods') }}" variant="outline">
            {{ __('student.subscriptions.manage_payment_methods') }}
        </flux:button>
    </div>

    <!-- Active Subscriptions -->
    <div class="mb-8">
        <flux:heading size="lg" class="mb-4">{{ __('student.subscriptions.active_subscriptions') }}</flux:heading>
        
        @if($activeSubscriptions->count() > 0)
            <div class="space-y-4">
                @foreach($activeSubscriptions as $enrollment)
                    <flux:card>
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-start gap-4">
                                    <div class="flex-1">
                                        <flux:heading size="md">{{ $enrollment->course->name }}</flux:heading>
                                        @if($enrollment->course->description)
                                            <flux:text class="text-gray-600 mt-1">
                                                {{ Str::limit($enrollment->course->description, 100) }}
                                            </flux:text>
                                        @endif
                                        
                                        <div class="mt-3 flex items-center gap-6">
                                            <div>
                                                <flux:text class="text-gray-600">{{ __('student.subscriptions.status') }}</flux:text>
                                                <div class="flex items-center gap-2 mt-1">
                                                    @if($enrollment->isPendingCancellation())
                                                        <flux:badge variant="warning">{{ $enrollment->getSubscriptionStatusLabel() }}</flux:badge>
                                                    @elseif($enrollment->isSubscriptionActive())
                                                        @if($enrollment->isCollectionPaused())
                                                            <flux:badge variant="warning">{{ $enrollment->getFullStatusDescription() }}</flux:badge>
                                                        @else
                                                            <flux:badge variant="success">{{ $enrollment->getSubscriptionStatusLabel() }}</flux:badge>
                                                        @endif
                                                    @elseif($enrollment->isSubscriptionTrialing())
                                                        <flux:badge variant="info">{{ $enrollment->getSubscriptionStatusLabel() }}</flux:badge>
                                                    @elseif($enrollment->isSubscriptionPastDue())
                                                        <flux:badge variant="warning">{{ $enrollment->getSubscriptionStatusLabel() }}</flux:badge>
                                                    @else
                                                        <flux:badge variant="gray">{{ $enrollment->getSubscriptionStatusLabel() }}</flux:badge>
                                                    @endif
                                                </div>
                                                @if($enrollment->isPendingCancellation() && $enrollment->subscription_cancel_at)
                                                    <flux:text size="sm" class="text-orange-600 mt-1">
                                                        <flux:icon name="exclamation-triangle" class="w-4 h-4 inline mr-1" />
                                                        {{ __('student.subscriptions.cancels') }} {{ $enrollment->getFormattedCancellationDate() }}
                                                    </flux:text>
                                                @elseif($enrollment->isCollectionPaused() && $enrollment->collection_paused_at)
                                                    <flux:text size="sm" class="text-gray-500 mt-1">
                                                        {{ __('student.subscriptions.paused') }} {{ $enrollment->getFormattedCollectionPausedDate() }}
                                                    </flux:text>
                                                @endif
                                            </div>

                                            @if($enrollment->course->feeSettings)
                                                <div>
                                                    <flux:text class="text-gray-600">{{ __('student.subscriptions.amount') }}</flux:text>
                                                    <flux:text class="font-semibold mt-1">
                                                        {{ $enrollment->course->feeSettings->formatted_fee }}
                                                        <flux:text size="sm" class="text-gray-500">
                                                            / {{ $enrollment->course->feeSettings->billing_cycle_label }}
                                                        </flux:text>
                                                    </flux:text>
                                                </div>
                                            @endif
                                            
                                            @if($enrollment->enrollment_date)
                                                <div>
                                                    <flux:text class="text-gray-600">{{ __('student.subscriptions.started') }}</flux:text>
                                                    <flux:text class="mt-1">{{ $enrollment->enrollment_date->format('M j, Y') }}</flux:text>
                                                </div>
                                            @endif
                                        </div>

                                        @if($enrollment->orders->count() > 0)
                                            <div class="mt-3">
                                                <flux:text class="text-gray-600">{{ __('student.subscriptions.last_payment') }}</flux:text>
                                                <flux:text class="mt-1">
                                                    {{ $enrollment->orders->first()->created_at->format('M j, Y') }} -
                                                    {{ $enrollment->orders->first()->formatted_amount }}
                                                    @if($enrollment->orders->first()->isPaid())
                                                        <flux:badge variant="success" size="sm">{{ __('student.subscriptions.paid') }}</flux:badge>
                                                    @elseif($enrollment->orders->first()->isFailed())
                                                        <flux:badge variant="danger" size="sm">{{ __('student.subscriptions.failed') }}</flux:badge>
                                                    @endif
                                                </flux:text>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2 ml-4">
                                @if(!$enrollment->isPendingCancellation())
                                    <flux:button
                                        wire:click="updatePaymentMethod({{ $enrollment->id }})"
                                        variant="outline"
                                        size="sm"
                                    >
                                        {{ __('student.subscriptions.update_payment') }}
                                    </flux:button>
                                @endif

                                @if($enrollment->isCollectionPaused() && $enrollment->isSubscriptionActive() && !$enrollment->isPendingCancellation())
                                    <flux:button
                                        wire:click="resumeCollection({{ $enrollment->id }})"
                                        wire:confirm="Are you sure you want to resume collection? Future payments will be processed normally."
                                        variant="primary"
                                        size="sm"
                                    >
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="play" class="w-4 h-4 mr-1" />
                                            {{ __('student.subscriptions.resume_collection') }}
                                        </div>
                                    </flux:button>
                                @endif

                                @if($enrollment->isPendingCancellation())
                                    <flux:button
                                        wire:click="resumeSubscription({{ $enrollment->id }})"
                                        wire:confirm="Are you sure you want to undo the cancellation? Your subscription will continue normally."
                                        variant="primary"
                                        size="sm"
                                    >
                                        <div class="flex items-center justify-center">
                                            <flux:icon name="arrow-path" class="w-4 h-4 mr-1" />
                                            {{ __('student.subscriptions.resume_subscription') }}
                                        </div>
                                    </flux:button>
                                @else
                                    <flux:button
                                        wire:click="cancelSubscription({{ $enrollment->id }})"
                                        wire:confirm="Are you sure you want to cancel this subscription? It will remain active until the end of the current billing period."
                                        variant="outline"
                                        size="sm"
                                        class="text-red-600 border-red-300 hover:bg-red-50"
                                    >
                                        {{ __('student.subscriptions.cancel') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @else
            <flux:card>
                <div class="text-center py-8">
                    <flux:icon name="credit-card" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <flux:text class="text-gray-600">{{ __('student.subscriptions.no_active_subscriptions') }}</flux:text>
                    <flux:text size="sm" class="text-gray-500 mt-1">
                        {{ __('student.subscriptions.contact_admin') }}
                    </flux:text>
                </div>
            </flux:card>
        @endif
    </div>

    <!-- Canceled Subscriptions -->
    @if($canceledSubscriptions->count() > 0)
        <div>
            <flux:heading size="lg" class="mb-4">{{ __('student.subscriptions.canceled_subscriptions') }}</flux:heading>
            
            <div class="space-y-4">
                @foreach($canceledSubscriptions as $enrollment)
                    <flux:card class="opacity-75">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <flux:heading size="md" class="text-gray-600">{{ $enrollment->course->name }}</flux:heading>
                                
                                <div class="mt-3 flex items-center gap-6">
                                    <div>
                                        <flux:text class="text-gray-600">{{ __('student.subscriptions.status') }}</flux:text>
                                        <flux:badge variant="gray" class="mt-1">{{ __('student.status.cancelled') }}</flux:badge>
                                    </div>

                                    @if($enrollment->enrollment_date)
                                        <div>
                                            <flux:text class="text-gray-600">{{ __('student.subscriptions.was_active') }}</flux:text>
                                            <flux:text class="mt-1">{{ $enrollment->enrollment_date->format('M j, Y') }}</flux:text>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        </div>
    @endif
</div>