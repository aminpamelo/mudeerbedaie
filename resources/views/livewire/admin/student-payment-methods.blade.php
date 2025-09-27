<?php

use App\Models\Student;
use App\Models\PaymentMethod;
use App\Services\StripeService;
use Livewire\Volt\Component;

new class extends Component {
    public Student $student;
    public $paymentMethods;
    public $showAddCardModal = false;
    public $stripePublishableKey = '';
    public bool $isProcessing = false;
    public bool $isSubmitting = false;

    public function mount()
    {
        // Load student with user relationship
        $this->student->load(['user']);
        
        $this->loadPaymentMethods();
        
        // Get Stripe configuration
        try {
            $stripeService = app(StripeService::class);
            if ($stripeService->isConfigured()) {
                $this->stripePublishableKey = $stripeService->getPublishableKey();
            }
        } catch (\Exception $e) {
            session()->flash('warning', 'Payment method management is not available. Stripe is not configured.');
        }
    }

    public function loadPaymentMethods()
    {
        $this->paymentMethods = $this->student->user
            ->paymentMethods()
            ->active()
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function openAddCardModal()
    {
        if (empty($this->stripePublishableKey)) {
            session()->flash('error', 'Payment methods are not available. Please check Stripe configuration.');
            return;
        }

        $this->showAddCardModal = true;
        
        // Dispatch event for JavaScript to initialize Stripe Elements
        $this->dispatch('show-add-card-modal');
    }

    public function closeAddCardModal()
    {
        $this->showAddCardModal = false;
        $this->isSubmitting = false;
        $this->isProcessing = false;

        // Dispatch event for JavaScript to clean up Stripe Elements
        $this->dispatch('hide-add-card-modal');
    }

    public function deletePaymentMethod($paymentMethodId)
    {
        $paymentMethod = PaymentMethod::where('id', $paymentMethodId)
            ->where('user_id', $this->student->user_id)
            ->first();

        if (!$paymentMethod) {
            session()->flash('error', 'Payment method not found.');
            return;
        }

        try {
            // Delete via API call
            $response = $this->callAdminApi('DELETE', route('admin.students.payment-methods.delete', [
                'student' => $this->student,
                'paymentMethod' => $paymentMethod
            ]));
            
            if ($response['success'] ?? false) {
                session()->flash('success', 'Payment method deleted successfully.');
                $this->loadPaymentMethods();

                // Log admin action
                \Log::info('Admin deleted student payment method', [
                    'admin_id' => auth()->user()->id,
                    'admin_name' => auth()->user()->name,
                    'student_id' => $this->student->id,
                    'student_name' => $this->student->user->name,
                    'payment_method_id' => $paymentMethod->id,
                    'payment_method_display' => $paymentMethod->display_name
                ]);
            } else {
                session()->flash('error', $response['error'] ?? 'Failed to delete payment method.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete payment method: ' . $e->getMessage());
        }
    }

    public function setAsDefault($paymentMethodId)
    {
        $paymentMethod = PaymentMethod::where('id', $paymentMethodId)
            ->where('user_id', $this->student->user_id)
            ->first();

        if (!$paymentMethod) {
            session()->flash('error', 'Payment method not found.');
            return;
        }

        try {
            // Set as default via API call
            $response = $this->callAdminApi('PATCH', route('admin.students.payment-methods.default', [
                'student' => $this->student,
                'paymentMethod' => $paymentMethod
            ]));
            
            if ($response['success'] ?? false) {
                session()->flash('success', 'Default payment method updated.');
                $this->loadPaymentMethods();

                // Log admin action
                \Log::info('Admin set default payment method for student', [
                    'admin_id' => auth()->user()->id,
                    'admin_name' => auth()->user()->name,
                    'student_id' => $this->student->id,
                    'student_name' => $this->student->user->name,
                    'payment_method_id' => $paymentMethod->id,
                    'payment_method_display' => $paymentMethod->display_name
                ]);
            } else {
                session()->flash('error', $response['error'] ?? 'Failed to update default payment method.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update payment method: ' . $e->getMessage());
        }
    }

    private function callAdminApi(string $method, string $url, array $data = []): array
    {
        try {
            $paymentController = app(\App\Http\Controllers\PaymentController::class);
            
            if (str_contains($url, 'payment-methods/store') || str_contains($url, 'payment-methods') && $method === 'POST') {
                // Create payment method
                $request = request()->merge($data);
                $response = $paymentController->adminStorePaymentMethod($request, $this->student);
            } elseif (str_contains($url, '/delete') || $method === 'DELETE') {
                // Delete payment method 
                $paymentMethodId = basename(parse_url($url, PHP_URL_PATH));
                $paymentMethod = \App\Models\PaymentMethod::findOrFail($paymentMethodId);
                $response = $paymentController->adminDeletePaymentMethod($this->student, $paymentMethod);
            } elseif (str_contains($url, '/default') || $method === 'PATCH') {
                // Set default payment method
                $paymentMethodId = basename(dirname(parse_url($url, PHP_URL_PATH)));
                $paymentMethod = \App\Models\PaymentMethod::findOrFail($paymentMethodId);
                $response = $paymentController->adminSetDefaultPaymentMethod($this->student, $paymentMethod);
            } else {
                return ['success' => false, 'error' => 'Unknown operation: ' . $method . ' ' . $url];
            }
            
            return $response->getData(true);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function setSubmitting($isSubmitting)
    {
        $this->isSubmitting = $isSubmitting;
    }

    public function addPaymentMethod($paymentMethodData)
    {
        try {
            $this->isProcessing = true;

            // This would be called from JavaScript after Stripe processing
            $response = $this->callAdminApi('POST', route('admin.students.payment-methods.store', $this->student), $paymentMethodData);

            if ($response['success'] ?? false) {
                session()->flash('success', 'Payment method added successfully for ' . $this->student->user->name . '.');
                $this->loadPaymentMethods();
                $this->closeAddCardModal();

                // Log admin action
                \Log::info('Admin added payment method for student', [
                    'admin_id' => auth()->user()->id,
                    'admin_name' => auth()->user()->name,
                    'student_id' => $this->student->id,
                    'student_name' => $this->student->user->name,
                    'payment_method_type' => $paymentMethodData['type'] ?? 'stripe_card'
                ]);
            } else {
                session()->flash('error', $response['error'] ?? 'Failed to add payment method.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to add payment method: ' . $e->getMessage());
        } finally {
            $this->isProcessing = false;
            $this->isSubmitting = false;
        }
    }

    public function with(): array
    {
        return [
            'hasPaymentMethods' => $this->paymentMethods->count() > 0,
            'canAddPaymentMethods' => !empty($this->stripePublishableKey),
        ];
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Payment Methods for {{ $student->user->name }}</flux:heading>
            <flux:text class="mt-2">Student ID: {{ $student->student_id }} • Email: {{ $student->user->email }}</flux:text>
        </div>
        <div class="flex space-x-3">
            <flux:button variant="ghost" href="{{ route('students.show', $student) }}">
                Back to Student Profile
            </flux:button>
            @if($canAddPaymentMethods)
                <flux:button variant="primary" icon="plus" wire:click="openAddCardModal">
                    Add Payment Method
                </flux:button>
            @endif
        </div>
    </div>

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

    <!-- Payment Methods List -->
    @if($hasPaymentMethods)
        <flux:card>
            <flux:header>
                <flux:heading size="lg">Active Payment Methods ({{ $paymentMethods->count() }})</flux:heading>
                <flux:text class="text-gray-600">Manage saved payment methods for subscriptions and payments</flux:text>
            </flux:header>

            <div class="space-y-4">
                @foreach($paymentMethods as $method)
                    <div class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 :bg-gray-800/50 {{ $method->is_default ? 'border-blue-500 bg-blue-50 /20' : 'border-gray-200 ' }}">
                        <div class="flex items-center space-x-4">
                            <!-- Card Icon -->
                            <div class="w-12 h-8 bg-gray-200  rounded flex items-center justify-center">
                                <span class="text-xs font-bold text-gray-600">
                                    {{ strtoupper($method->card_details['brand'] ?? 'CARD') }}
                                </span>
                            </div>

                            <!-- Card Details -->
                            <div>
                                <div class="flex items-center space-x-2">
                                    <flux:text class="font-medium">
                                        {{ ucfirst($method->card_details['brand'] ?? 'Card') }} ending in {{ $method->card_details['last4'] ?? '****' }}
                                    </flux:text>
                                    @if($method->is_default)
                                        <flux:badge color="blue" size="sm">Default</flux:badge>
                                    @endif
                                    @if($method->is_expired)
                                        <flux:badge color="red" size="sm">Expired</flux:badge>
                                    @endif
                                </div>
                                <flux:text size="sm" class="text-gray-600">
                                    Expires {{ $method->card_details['exp_month'] ?? '**' }}/{{ $method->card_details['exp_year'] ?? '**' }}
                                </flux:text>
                                <flux:text size="sm" class="text-gray-500">
                                    Added {{ $method->created_at->format('M d, Y') }}
                                </flux:text>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center space-x-2">
                            @if(!$method->is_default)
                                <flux:button 
                                    variant="ghost" 
                                    size="sm" 
                                    wire:click="setAsDefault({{ $method->id }})"
                                    wire:confirm="Set this as the default payment method for {{ $student->user->name }}?"
                                >
                                    Set as Default
                                </flux:button>
                            @endif
                            
                            <flux:button 
                                variant="ghost" 
                                size="sm" 
                                color="red"
                                icon="trash"
                                wire:click="deletePaymentMethod({{ $method->id }})"
                                wire:confirm="Are you sure you want to delete this payment method for {{ $student->user->name }}? This action cannot be undone."
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @else
        <!-- Empty State -->
        <flux:card>
            <div class="text-center py-12">
                <flux:icon icon="credit-card" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
                <flux:heading size="md" class="text-gray-600  mb-2">No Payment Methods</flux:heading>
                <flux:text class="text-gray-600  mb-6">
                    {{ $student->user->name }} hasn't added any payment methods yet. You can add one for them to enable subscription creation.
                </flux:text>
                @if($canAddPaymentMethods)
                    <flux:button variant="primary" icon="plus" wire:click="openAddCardModal">
                        Add Payment Method for Student
                    </flux:button>
                @else
                    <flux:text class="text-gray-500">
                        Payment method management is currently unavailable. Please check Stripe configuration.
                    </flux:text>
                @endif
            </div>
        </flux:card>
    @endif

    <!-- Admin Notice -->
    <flux:card class="mt-6 bg-amber-50 /20 border-amber-200">
        <div class="flex items-start space-x-3">
            <flux:icon.information-circle class="w-5 h-5 text-amber-600 mt-1" />
            <div>
                <flux:heading size="sm" class="text-amber-800">Admin Notice</flux:heading>
                <flux:text size="sm" class="text-amber-700  mt-1">
                    You are managing payment methods for {{ $student->user->name }}. All actions will be logged for audit purposes. 
                    The student will receive email notifications about changes to their payment methods.
                </flux:text>
            </div>
        </div>
    </flux:card>

    <!-- Add Payment Method Modal -->
    <flux:modal wire:model="showAddCardModal" class="max-w-lg">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:heading size="lg">Add Payment Method</flux:heading>
                    <flux:text class="text-gray-600">For: {{ $student->user->name }}</flux:text>
                </div>
                <flux:button variant="ghost" size="sm" wire:click="closeAddCardModal">
                    <flux:icon icon="x-mark" class="w-5 h-5" />
                </flux:button>
            </div>

            <div class="space-y-6">
                <!-- Stripe Elements Card Input -->
                <div>
                    <flux:text class="font-medium mb-3">Card Information</flux:text>
                    <div id="stripe-card-element" class="p-4 border rounded-lg bg-white">
                        <!-- Stripe Elements will be mounted here -->
                        <div class="text-center text-gray-500 py-8">
                            <flux:icon icon="credit-card" class="w-8 h-8 mx-auto mb-2" />
                            <div>Stripe Elements will be loaded here</div>
                            <div class="text-sm">Card input form</div>
                        </div>
                    </div>
                    <div id="stripe-card-errors" class="mt-2 text-red-600 text-sm"></div>
                </div>

                <!-- Save Options -->
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" id="set-as-default" class="rounded" checked>
                        <span class="ml-2 text-sm">Set as default payment method</span>
                    </label>
                </div>

                <!-- Security Notice -->
                <div class="bg-gray-50  p-4 rounded-lg">
                    <div class="flex items-center text-sm">
                        <flux:icon icon="shield-check" class="w-4 h-4 mr-2 text-green-500" />
                        <span>Card information is securely processed by Stripe and never stored on our servers.</span>
                    </div>
                </div>

                <!-- Admin Notice -->
                <div class="bg-amber-50 /20 p-4 rounded-lg">
                    <div class="flex items-center text-sm">
                        <flux:icon icon="information-circle" class="w-4 h-4 mr-2 text-amber-500" />
                        <span>This action will be logged and {{ $student->user->name }} will be notified.</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3">
                    <flux:button variant="outline" wire:click="closeAddCardModal" :disabled="$isSubmitting || $isProcessing">
                        Cancel
                    </flux:button>
                    <flux:button
                        id="submit-payment-method"
                        variant="primary"
                        :disabled="$isSubmitting || $isProcessing"
                    >
                        @if($isSubmitting || $isProcessing)
                            <div class="flex items-center">
                                <flux:icon icon="arrow-path" class="w-4 h-4 animate-spin mr-2" />
                                @if($isSubmitting && !$isProcessing)
                                    Processing Card...
                                @else
                                    Adding...
                                @endif
                            </div>
                        @else
                            Add Payment Method
                        @endif
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>

@push('scripts')
@if($canAddPaymentMethods)
<script src="https://js.stripe.com/v3/"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const publishableKey = @json($stripePublishableKey);
    if (!publishableKey) {
        console.error('Stripe publishable key not found');
        return;
    }

    console.log('Initializing Stripe with key:', publishableKey.substring(0, 20) + '...');
    
    const stripe = Stripe(publishableKey);
    const elements = stripe.elements();
    
    let cardElement = null;
    let isInitialized = false;
    
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
            
            cardElement = elements.create('card', {
                style: {
                    base: {
                        fontSize: '16px',
                        color: '#424770',
                        fontFamily: 'system-ui, -apple-system, sans-serif',
                        '::placeholder': {
                            color: '#aab7c4',
                        },
                    },
                    invalid: {
                        color: '#fa755a',
                        iconColor: '#fa755a'
                    }
                },
            });

            cardElement.mount('#stripe-card-element');
            console.log('Stripe card element mounted successfully');

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
                    // Send token to server - the addPaymentMethod will handle resetting isSubmitting
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
    
    function cleanupStripeCard() {
        if (cardElement) {
            try {
                cardElement.unmount();
                console.log('Stripe card element unmounted');
            } catch (error) {
                console.error('Error unmounting card element:', error);
            }
            cardElement = null;
        }
        isInitialized = false;
    }
    
    // Wait for Livewire to be ready
    document.addEventListener('livewire:initialized', function() {
        console.log('Livewire initialized, setting up event listeners');
        
        Livewire.on('show-add-card-modal', function() {
            console.log('Show add card modal event received');
            
            // Use a more reliable timing approach
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
                }, 150 + (attempts * 100)); // Increase delay with each attempt
            };
            
            if (!isInitialized) {
                initWithRetry();
            }
        });
        
        Livewire.on('hide-add-card-modal', function() {
            console.log('Hide add card modal event received');
            cleanupStripeCard();
        });
    });
});
</script>
@endif
@endpush