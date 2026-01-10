<?php

use App\Models\PaymentMethod;
use App\Models\PaymentMethodToken;
use App\Services\StripeService;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.guest')] class extends Component {
    public string $token;
    public ?PaymentMethodToken $tokenModel = null;
    public $student = null;
    public $paymentMethods;
    public $stripePublishableKey = '';
    public bool $isProcessing = false;
    public bool $isSubmitting = false;
    public bool $isValidToken = false;
    public string $errorMessage = '';
    public bool $showSuccess = false;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->validateToken();

        if ($this->isValidToken) {
            $this->loadPaymentMethods();
            $this->initializeStripe();
        }
    }

    protected function validateToken(): void
    {
        $this->tokenModel = PaymentMethodToken::findValidToken($this->token);

        if (!$this->tokenModel) {
            // Check if token exists but is expired
            $expiredToken = PaymentMethodToken::where('token', $this->token)->first();

            if ($expiredToken) {
                if ($expiredToken->isExpired()) {
                    $this->errorMessage = 'This link has expired. Please contact the administrator for a new link.';
                } else {
                    $this->errorMessage = 'This link is no longer valid. Please contact the administrator for a new link.';
                }
            } else {
                $this->errorMessage = 'Invalid link. Please contact the administrator for assistance.';
            }

            $this->isValidToken = false;
            return;
        }

        $this->isValidToken = true;
        $this->student = $this->tokenModel->student;
        $this->student->load('user');

        // Record token usage
        $this->tokenModel->recordUsage();
    }

    protected function initializeStripe(): void
    {
        try {
            $stripeService = app(StripeService::class);
            if ($stripeService->isConfigured()) {
                $this->stripePublishableKey = $stripeService->getPublishableKey();
            }
        } catch (\Exception $e) {
            \Log::error('Stripe configuration error in guest payment update', [
                'error' => $e->getMessage(),
                'token_id' => $this->tokenModel?->id,
            ]);
        }
    }

    public function loadPaymentMethods(): void
    {
        if (!$this->student) {
            $this->paymentMethods = collect();
            return;
        }

        $this->paymentMethods = $this->student->user
            ->paymentMethods()
            ->active()
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function setSubmitting(bool $isSubmitting): void
    {
        $this->isSubmitting = $isSubmitting;
    }

    public function addPaymentMethod(array $paymentMethodData): void
    {
        if (!$this->isValidToken || !$this->student) {
            session()->flash('error', 'Invalid session. Please refresh the page.');
            return;
        }

        try {
            $this->isProcessing = true;

            $stripeService = app(StripeService::class);

            // Create payment method from token (method handles customer creation internally)
            $paymentMethod = $stripeService->createPaymentMethodFromToken(
                $this->student->user,
                $paymentMethodData['payment_method_id']
            );

            // Set as default if requested
            if ($paymentMethod && ($paymentMethodData['set_as_default'] ?? true)) {
                $paymentMethod->setAsDefault();
            }

            if ($paymentMethod) {
                // Log the action
                \Log::info('Guest added payment method via magic link', [
                    'student_id' => $this->student->id,
                    'student_name' => $this->student->user->name,
                    'token_id' => $this->tokenModel->id,
                    'payment_method_id' => $paymentMethod->id,
                ]);

                $this->showSuccess = true;
                $this->loadPaymentMethods();

                session()->flash('success', 'Payment method added successfully! You can close this page.');
            } else {
                session()->flash('error', 'Failed to add payment method. Please try again.');
            }
        } catch (\Exception $e) {
            \Log::error('Guest failed to add payment method via magic link', [
                'student_id' => $this->student?->id,
                'token_id' => $this->tokenModel?->id,
                'error' => $e->getMessage(),
            ]);

            session()->flash('error', 'Failed to add payment method: ' . $e->getMessage());
        } finally {
            $this->isProcessing = false;
            $this->isSubmitting = false;
        }
    }

    public function with(): array
    {
        return [
            'hasPaymentMethods' => $this->paymentMethods?->count() > 0,
            'canAddPaymentMethods' => !empty($this->stripePublishableKey),
        ];
    }
}; ?>

<div class="min-h-screen bg-gray-50 dark:bg-zinc-900 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-lg mx-auto">
        <!-- Logo/Branding -->
        <div class="text-center mb-8">
            <flux:heading size="xl" class="text-gray-900 dark:text-white">Update Payment Method</flux:heading>
            <flux:text class="mt-2 text-gray-600 dark:text-zinc-400">Secure payment method management</flux:text>
        </div>

        @if(!$isValidToken)
            <!-- Invalid/Expired Token -->
            <flux:card class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700">
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                        <flux:icon icon="exclamation-triangle" class="w-8 h-8 text-red-600 dark:text-red-400" />
                    </div>
                    <flux:heading size="lg" class="text-gray-900 dark:text-white mb-2">Link Not Valid</flux:heading>
                    <flux:text class="text-gray-600 dark:text-zinc-400 mb-6">{{ $errorMessage }}</flux:text>
                    <flux:text size="sm" class="text-gray-500 dark:text-zinc-500">
                        If you believe this is an error, please contact your administrator.
                    </flux:text>
                </div>
            </flux:card>
        @elseif($showSuccess)
            <!-- Success State -->
            <flux:card class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700">
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                        <flux:icon icon="check-circle" class="w-8 h-8 text-green-600 dark:text-green-400" />
                    </div>
                    <flux:heading size="lg" class="text-gray-900 dark:text-white mb-2">Payment Method Added!</flux:heading>
                    <flux:text class="text-gray-600 dark:text-zinc-400 mb-6">
                        Your payment method has been successfully saved. You can now close this page.
                    </flux:text>

                    @if($hasPaymentMethods)
                        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-zinc-700">
                            <flux:text class="font-medium text-gray-900 dark:text-white mb-4">Your Saved Payment Methods</flux:text>
                            <div class="space-y-3">
                                @foreach($paymentMethods as $method)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-zinc-700/50 rounded-lg {{ $method->is_default ? 'ring-2 ring-blue-500' : '' }}">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-6 bg-gray-200 dark:bg-zinc-600 rounded flex items-center justify-center">
                                                <span class="text-xs font-bold text-gray-600 dark:text-zinc-300">
                                                    {{ strtoupper(substr($method->card_details['brand'] ?? 'CARD', 0, 4)) }}
                                                </span>
                                            </div>
                                            <div>
                                                <flux:text size="sm" class="font-medium text-gray-900 dark:text-white">
                                                    •••• {{ $method->card_details['last4'] ?? '****' }}
                                                </flux:text>
                                                <flux:text size="xs" class="text-gray-500 dark:text-zinc-400">
                                                    Expires {{ $method->card_details['exp_month'] ?? '**' }}/{{ $method->card_details['exp_year'] ?? '**' }}
                                                </flux:text>
                                            </div>
                                        </div>
                                        @if($method->is_default)
                                            <flux:badge color="blue" size="sm">Default</flux:badge>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </flux:card>
        @else
            <!-- Student Info Card -->
            <flux:card class="mb-6 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center">
                        <span class="text-blue-600 dark:text-blue-400 font-semibold text-lg">
                            {{ strtoupper(substr($student->user->name ?? 'U', 0, 2)) }}
                        </span>
                    </div>
                    <div>
                        <flux:text class="font-medium text-gray-900 dark:text-white">{{ $student->user->name }}</flux:text>
                        <flux:text size="sm" class="text-gray-500 dark:text-zinc-400">{{ $student->user->email }}</flux:text>
                        <flux:text size="xs" class="text-gray-400 dark:text-zinc-500">Student ID: {{ $student->student_id }}</flux:text>
                    </div>
                </div>
            </flux:card>

            <!-- Flash Messages -->
            @if (session('success'))
                <div class="mb-6 rounded-md bg-green-50 dark:bg-green-900/30 p-4 border border-green-200 dark:border-green-800">
                    <div class="flex">
                        <flux:icon icon="check-circle" class="h-5 w-5 text-green-400" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 rounded-md bg-red-50 dark:bg-red-900/30 p-4 border border-red-200 dark:border-red-800">
                    <div class="flex">
                        <flux:icon icon="x-circle" class="h-5 w-5 text-red-400" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800 dark:text-red-200">{{ session('error') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Current Payment Methods -->
            @if($hasPaymentMethods)
                <flux:card class="mb-6 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700">
                    <flux:heading size="md" class="mb-4 text-gray-900 dark:text-white">Current Payment Methods</flux:heading>
                    <div class="space-y-3">
                        @foreach($paymentMethods as $method)
                            <div class="flex items-center justify-between p-3 border rounded-lg {{ $method->is_default ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-zinc-600' }}">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-6 bg-gray-200 dark:bg-zinc-600 rounded flex items-center justify-center">
                                        <span class="text-xs font-bold text-gray-600 dark:text-zinc-300">
                                            {{ strtoupper(substr($method->card_details['brand'] ?? 'CARD', 0, 4)) }}
                                        </span>
                                    </div>
                                    <div>
                                        <flux:text size="sm" class="font-medium text-gray-900 dark:text-white">
                                            {{ ucfirst($method->card_details['brand'] ?? 'Card') }} •••• {{ $method->card_details['last4'] ?? '****' }}
                                        </flux:text>
                                        <flux:text size="xs" class="text-gray-500 dark:text-zinc-400">
                                            Expires {{ $method->card_details['exp_month'] ?? '**' }}/{{ $method->card_details['exp_year'] ?? '**' }}
                                        </flux:text>
                                    </div>
                                </div>
                                @if($method->is_default)
                                    <flux:badge color="blue" size="sm">Default</flux:badge>
                                @endif
                                @if($method->is_expired)
                                    <flux:badge color="red" size="sm">Expired</flux:badge>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            @endif

            <!-- Add New Payment Method -->
            @if($canAddPaymentMethods)
                <flux:card class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700">
                    <flux:heading size="md" class="mb-4 text-gray-900 dark:text-white">
                        {{ $hasPaymentMethods ? 'Add New Payment Method' : 'Add Payment Method' }}
                    </flux:heading>

                    <div class="space-y-6">
                        <!-- Stripe Elements Card Input -->
                        <div>
                            <flux:text class="font-medium mb-3 text-gray-900 dark:text-white">Card Information</flux:text>
                            <div id="stripe-card-element" class="p-4 border border-gray-300 dark:border-zinc-600 rounded-lg bg-white dark:bg-zinc-900 min-h-[50px]">
                                <!-- Stripe Elements will be mounted here -->
                                <div class="text-center text-gray-500 dark:text-zinc-400 py-4">
                                    <flux:icon icon="credit-card" class="w-6 h-6 mx-auto mb-2" />
                                    <div class="text-sm">Loading card input...</div>
                                </div>
                            </div>
                            <div id="stripe-card-errors" class="mt-2 text-red-600 dark:text-red-400 text-sm"></div>
                        </div>

                        <!-- Set as Default Option -->
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" id="set-as-default" class="rounded border-gray-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500 dark:bg-zinc-700 dark:checked:bg-blue-600" checked>
                                <span class="ml-2 text-sm text-gray-700 dark:text-zinc-300">Set as default payment method</span>
                            </label>
                        </div>

                        <!-- Security Notice -->
                        <div class="bg-gray-50 dark:bg-zinc-700/50 p-4 rounded-lg">
                            <div class="flex items-start text-sm">
                                <flux:icon icon="shield-check" class="w-5 h-5 mr-2 text-green-500 dark:text-green-400 flex-shrink-0 mt-0.5" />
                                <div class="text-gray-600 dark:text-zinc-300">
                                    <span class="font-medium text-gray-900 dark:text-white">Secure Payment Processing</span><br>
                                    Your card information is securely processed by Stripe and never stored on our servers.
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <flux:button
                            id="submit-payment-method"
                            variant="primary"
                            class="w-full"
                            :disabled="$isSubmitting || $isProcessing"
                        >
                            @if($isSubmitting || $isProcessing)
                                <div class="flex items-center justify-center">
                                    <flux:icon icon="arrow-path" class="w-4 h-4 animate-spin mr-2" />
                                    @if($isSubmitting && !$isProcessing)
                                        Processing Card...
                                    @else
                                        Adding Payment Method...
                                    @endif
                                </div>
                            @else
                                <div class="flex items-center justify-center">
                                    <flux:icon icon="credit-card" class="w-4 h-4 mr-2" />
                                    Add Payment Method
                                </div>
                            @endif
                        </flux:button>
                    </div>
                </flux:card>
            @else
                <flux:card class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700">
                    <div class="text-center py-8">
                        <flux:icon icon="exclamation-triangle" class="w-12 h-12 text-amber-500 dark:text-amber-400 mx-auto mb-4" />
                        <flux:heading size="md" class="text-gray-700 dark:text-zinc-300 mb-2">Payment System Unavailable</flux:heading>
                        <flux:text class="text-gray-600 dark:text-zinc-400">
                            The payment system is currently unavailable. Please try again later or contact support.
                        </flux:text>
                    </div>
                </flux:card>
            @endif

            <!-- Link Expiry Notice -->
            @if($tokenModel)
                <div class="mt-6 text-center">
                    <flux:text size="sm" class="text-gray-500 dark:text-zinc-500">
                        This link expires {{ $tokenModel->expires_in }}
                    </flux:text>
                </div>
            @endif
        @endif
    </div>
</div>

@if($isValidToken && $canAddPaymentMethods && !$showSuccess)
@push('scripts')
<script src="https://js.stripe.com/v3/"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const publishableKey = @json($stripePublishableKey);
    if (!publishableKey) {
        console.error('Stripe publishable key not found');
        return;
    }

    console.log('Initializing Stripe for guest payment update');

    const stripe = Stripe(publishableKey);
    const elements = stripe.elements();

    let cardElement = null;
    let isInitialized = false;

    // Detect dark mode
    function isDarkMode() {
        return document.documentElement.classList.contains('dark');
    }

    function initializeStripeCard() {
        const cardContainer = document.getElementById('stripe-card-element');
        if (!cardContainer) {
            console.error('Stripe card container not found');
            return false;
        }

        if (cardElement) {
            console.log('Card element already exists, skipping initialization');
            return true;
        }

        try {
            // Clear any existing placeholder content
            cardContainer.innerHTML = '';

            // Get dark mode styling
            const darkMode = isDarkMode();
            const cardStyle = {
                base: {
                    fontSize: '16px',
                    color: darkMode ? '#f4f4f5' : '#424770',
                    fontFamily: 'system-ui, -apple-system, sans-serif',
                    iconColor: darkMode ? '#a1a1aa' : '#6b7280',
                    '::placeholder': {
                        color: darkMode ? '#71717a' : '#aab7c4',
                    },
                },
                invalid: {
                    color: darkMode ? '#f87171' : '#fa755a',
                    iconColor: darkMode ? '#f87171' : '#fa755a'
                }
            };

            cardElement = elements.create('card', {
                style: cardStyle,
            });

            cardElement.mount('#stripe-card-element');
            console.log('Stripe card element mounted successfully', { darkMode });

            // Handle real-time validation errors
            cardElement.on('change', ({error}) => {
                const displayError = document.getElementById('stripe-card-errors');
                if (displayError) {
                    displayError.textContent = error ? error.message : '';
                }
            });

            return true;
        } catch (error) {
            console.error('Error initializing Stripe card element:', error);
            return false;
        }
    }

    function setupSubmitHandler() {
        const submitButton = document.getElementById('submit-payment-method');
        if (!submitButton) {
            console.error('Submit button not found');
            return;
        }

        if (submitButton.hasAttribute('data-stripe-listener')) {
            console.log('Submit listener already attached');
            return;
        }

        submitButton.setAttribute('data-stripe-listener', 'true');
        submitButton.addEventListener('click', async function(event) {
            event.preventDefault();

            if (!cardElement) {
                console.error('Card element not initialized');
                return;
            }

            // Immediately set loading state to provide user feedback
            @this.call('setSubmitting', true);

            // Clear any existing errors
            const errorElement = document.getElementById('stripe-card-errors');
            if (errorElement) {
                errorElement.textContent = '';
            }

            try {
                const {token, error} = await stripe.createToken(cardElement);

                if (error) {
                    console.error('Stripe token creation error:', error);
                    if (errorElement) {
                        errorElement.textContent = error.message;
                    }
                    // Reset loading state on error
                    @this.call('setSubmitting', false);
                } else {
                    console.log('Stripe token created successfully');
                    // Send token to server
                    const setAsDefault = document.getElementById('set-as-default')?.checked || false;

                    @this.call('addPaymentMethod', {
                        payment_method_id: token.id,
                        set_as_default: setAsDefault
                    });
                }
            } catch (error) {
                console.error('Error during payment method submission:', error);
                // Reset loading state on error
                @this.call('setSubmitting', false);
                if (errorElement) {
                    errorElement.textContent = 'An unexpected error occurred. Please try again.';
                }
            }
        });
    }

    // Initialize immediately since we're on a standalone page
    const initWithRetry = (attempts = 0) => {
        if (attempts > 10) {
            console.error('Failed to initialize Stripe after multiple attempts');
            return;
        }

        setTimeout(() => {
            const success = initializeStripeCard();
            if (success) {
                setupSubmitHandler();
                isInitialized = true;
            } else {
                console.log(`Initialization attempt ${attempts + 1} failed, retrying...`);
                initWithRetry(attempts + 1);
            }
        }, 150 + (attempts * 100));
    };

    initWithRetry();
});
</script>
@endpush
@endif
