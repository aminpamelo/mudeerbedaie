<?php

use App\Models\Funnel;
use App\Models\FunnelSession;
use App\Models\FunnelStep;
use App\Models\ProductOrder;
use Livewire\Volt\Component;

new class extends Component
{
    public Funnel $funnel;
    public FunnelStep $step;
    public ?FunnelSession $funnelSession = null;
    public ?ProductOrder $order = null;
    public ?string $orderNumber = null;

    public function mount(Funnel $funnel, FunnelStep $step, ?FunnelSession $session = null): void
    {
        $this->funnel = $funnel;
        $this->step = $step;
        $this->funnelSession = $session;

        // Get order number from query string or session
        $this->orderNumber = request('order') ?: session('order_number');

        if ($this->orderNumber) {
            $this->order = ProductOrder::where('order_number', $this->orderNumber)->first();
        }

        // Track thank you page view
        $this->funnelSession?->trackEvent('thank_you_view', [
            'order_number' => $this->orderNumber,
        ], $this->step);
    }
}; ?>

<div class="funnel-thank-you text-center py-12">
    <div class="max-w-2xl mx-auto px-4">
        {{-- Success Icon --}}
        <div class="mx-auto w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mb-6">
            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        {{-- Thank You Message --}}
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Thank You for Your Order!</h1>

        @if($order)
            <p class="text-lg text-gray-600 mb-2">Your order has been confirmed.</p>
            <p class="text-xl font-semibold text-gray-800 mb-6">Order #{{ $order->order_number }}</p>

            {{-- Order Summary --}}
            <div class="bg-white rounded-lg shadow-sm border p-6 text-left mb-8">
                <h3 class="font-semibold text-lg mb-4 border-b pb-2">Order Details</h3>

                <div class="space-y-3">
                    @foreach($order->items as $item)
                        <div class="flex justify-between">
                            <span class="text-gray-700">{{ $item->name }}</span>
                            <span class="font-medium">RM {{ number_format($item->total_price, 2) }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="border-t mt-4 pt-4">
                    <div class="flex justify-between text-lg font-bold">
                        <span>Total Paid</span>
                        <span>RM {{ number_format($order->total_amount, 2) }}</span>
                    </div>
                </div>

                {{-- Customer Info --}}
                <div class="border-t mt-4 pt-4 text-sm text-gray-600">
                    <p><strong>Email:</strong> {{ $order->email }}</p>
                    @if($order->billing_address)
                        <p class="mt-1">
                            <strong>Billing:</strong>
                            {{ $order->billing_address['first_name'] ?? '' }} {{ $order->billing_address['last_name'] ?? '' }},
                            {{ $order->billing_address['address_line_1'] ?? '' }},
                            {{ $order->billing_address['city'] ?? '' }}
                        </p>
                    @endif
                </div>
            </div>
        @else
            <p class="text-lg text-gray-600 mb-6">
                We've received your order and you'll receive a confirmation email shortly.
            </p>
        @endif

        {{-- What's Next Section --}}
        <div class="bg-blue-50 rounded-lg p-6 mb-8 text-left">
            <h3 class="font-semibold text-lg text-blue-900 mb-3">What's Next?</h3>
            <ul class="space-y-2 text-blue-800">
                <li class="flex items-start">
                    <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>Check your email for the order confirmation</span>
                </li>
                <li class="flex items-start">
                    <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>Access your purchases in your account dashboard</span>
                </li>
                <li class="flex items-start">
                    <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>If you have any questions, contact our support team</span>
                </li>
            </ul>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            @auth
                <a
                    href="{{ route('student.orders') }}"
                    class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    View My Orders
                </a>
            @else
                <a
                    href="{{ route('login') }}"
                    class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    Create Account
                </a>
            @endauth

            <a
                href="/"
                class="inline-flex items-center px-6 py-3 border border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold rounded-lg transition-colors"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Back to Home
            </a>
        </div>

        {{-- Support Info --}}
        <div class="mt-12 text-sm text-gray-500">
            <p>Questions about your order?</p>
            <p>Contact us at <a href="mailto:support@example.com" class="text-blue-600 hover:underline">support@example.com</a></p>
        </div>
    </div>
</div>
