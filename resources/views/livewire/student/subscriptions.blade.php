<?php
use App\Models\Enrollment;
use App\Services\StripeService;
use Livewire\Volt\Component;

new class extends Component {
    public function cancelSubscription($enrollmentId)
    {
        try {
            $enrollment = Enrollment::where('id', $enrollmentId)
                ->where('student_id', auth()->user()->student->id)
                ->firstOrFail();

            if (!$enrollment->stripe_subscription_id) {
                session()->flash('error', 'This enrollment does not have an active subscription.');
                return;
            }

            $stripeService = app(StripeService::class);
            $stripeService->cancelSubscription($enrollment->stripe_subscription_id, false); // Cancel at period end
            
            session()->flash('success', 'Your subscription will be canceled at the end of the current billing period.');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to cancel subscription: ' . $e->getMessage());
        }
    }

    public function updatePaymentMethod($enrollmentId)
    {
        // Redirect to payment methods page with context
        return redirect()->route('student.payment-methods', ['enrollment' => $enrollmentId]);
    }

    public function with(): array
    {
        $student = auth()->user()->student;
        
        return [
            'activeSubscriptions' => Enrollment::where('student_id', $student->id)
                ->with(['course.feeSettings', 'orders' => function($q) {
                    $q->latest()->limit(1);
                }])
                ->where(function($query) {
                    $query->whereIn('subscription_status', ['active', 'trialing', 'past_due'])
                          ->orWhere(function($q) {
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
            <flux:heading size="xl">My Subscriptions</flux:heading>
            <flux:text class="mt-2">Manage your course subscriptions and billing</flux:text>
        </div>
        <flux:button href="{{ route('student.payment-methods') }}" variant="outline">
            Manage Payment Methods
        </flux:button>
    </div>

    <!-- Active Subscriptions -->
    <div class="mb-8">
        <flux:heading size="lg" class="mb-4">Active Subscriptions</flux:heading>
        
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
                                                <flux:text class="text-gray-600">Status</flux:text>
                                                <div class="flex items-center gap-2 mt-1">
                                                    @if($enrollment->isSubscriptionActive())
                                                        <flux:badge variant="success">{{ $enrollment->getSubscriptionStatusLabel() }}</flux:badge>
                                                    @elseif($enrollment->isSubscriptionTrialing())
                                                        <flux:badge variant="info">{{ $enrollment->getSubscriptionStatusLabel() }}</flux:badge>
                                                    @elseif($enrollment->isSubscriptionPastDue())
                                                        <flux:badge variant="warning">{{ $enrollment->getSubscriptionStatusLabel() }}</flux:badge>
                                                    @else
                                                        <flux:badge variant="gray">{{ $enrollment->getSubscriptionStatusLabel() }}</flux:badge>
                                                    @endif
                                                </div>
                                            </div>
                                            
                                            @if($enrollment->course->feeSettings)
                                                <div>
                                                    <flux:text class="text-gray-600">Amount</flux:text>
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
                                                    <flux:text class="text-gray-600">Started</flux:text>
                                                    <flux:text class="mt-1">{{ $enrollment->enrollment_date->format('M j, Y') }}</flux:text>
                                                </div>
                                            @endif
                                        </div>

                                        @if($enrollment->orders->count() > 0)
                                            <div class="mt-3">
                                                <flux:text class="text-gray-600">Last Payment</flux:text>
                                                <flux:text class="mt-1">
                                                    {{ $enrollment->orders->first()->created_at->format('M j, Y') }} - 
                                                    {{ $enrollment->orders->first()->formatted_amount }}
                                                    @if($enrollment->orders->first()->isPaid())
                                                        <flux:badge variant="success" size="sm">Paid</flux:badge>
                                                    @elseif($enrollment->orders->first()->isFailed())
                                                        <flux:badge variant="danger" size="sm">Failed</flux:badge>
                                                    @endif
                                                </flux:text>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2 ml-4">
                                <flux:button 
                                    wire:click="updatePaymentMethod({{ $enrollment->id }})" 
                                    variant="outline" 
                                    size="sm"
                                >
                                    Update Payment
                                </flux:button>
                                
                                <flux:button 
                                    wire:click="cancelSubscription({{ $enrollment->id }})"
                                    wire:confirm="Are you sure you want to cancel this subscription? It will remain active until the end of the current billing period."
                                    variant="outline" 
                                    size="sm"
                                    class="text-red-600 border-red-300 hover:bg-red-50"
                                >
                                    Cancel
                                </flux:button>
                            </div>
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @else
            <flux:card>
                <div class="text-center py-8">
                    <flux:icon name="credit-card" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <flux:text class="text-gray-600">You don't have any active subscriptions.</flux:text>
                    <flux:text size="sm" class="text-gray-500 mt-1">
                        Contact your administrator to enroll in courses.
                    </flux:text>
                </div>
            </flux:card>
        @endif
    </div>

    <!-- Canceled Subscriptions -->
    @if($canceledSubscriptions->count() > 0)
        <div>
            <flux:heading size="lg" class="mb-4">Canceled Subscriptions</flux:heading>
            
            <div class="space-y-4">
                @foreach($canceledSubscriptions as $enrollment)
                    <flux:card class="opacity-75">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <flux:heading size="md" class="text-gray-600">{{ $enrollment->course->name }}</flux:heading>
                                
                                <div class="mt-3 flex items-center gap-6">
                                    <div>
                                        <flux:text class="text-gray-600">Status</flux:text>
                                        <flux:badge variant="gray" class="mt-1">Canceled</flux:badge>
                                    </div>
                                    
                                    @if($enrollment->enrollment_date)
                                        <div>
                                            <flux:text class="text-gray-600">Was Active</flux:text>
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