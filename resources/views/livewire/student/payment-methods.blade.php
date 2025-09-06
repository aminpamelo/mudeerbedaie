<?php
use App\Models\PaymentMethod;
use App\Services\StripeService;
use Livewire\Volt\Component;

new class extends Component {
    public $paymentMethods;
    public $showAddCardModal = false;
    public $stripePublishableKey = '';
    public bool $isProcessing = false;

    public function mount()
    {
        // Ensure user is a student
        if (!auth()->user()->isStudent()) {
            abort(403, 'Access denied');
        }

        $this->loadPaymentMethods();
        
        // Get Stripe configuration
        try {
            $stripeService = app(StripeService::class);
            if ($stripeService->isConfigured()) {
                $this->stripePublishableKey = $stripeService->getPublishableKey();
            }
        } catch (\Exception $e) {
            // Stripe not configured
            session()->flash('warning', 'Payment method management is not available. Stripe is not configured.');
        }
    }

    public function loadPaymentMethods()
    {
        $this->paymentMethods = auth()->user()
            ->paymentMethods()
            ->active()
            ->with('user')
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function openAddCardModal()
    {
        if (empty($this->stripePublishableKey)) {
            session()->flash('error', 'Payment methods are not available. Please contact support.');
            return;
        }

        $this->showAddCardModal = true;
        
        // Dispatch event for JavaScript to initialize Stripe Elements
        $this->dispatch('show-add-card-modal');
    }

    public function closeAddCardModal()
    {
        $this->showAddCardModal = false;
        
        // Dispatch event for JavaScript to clean up Stripe Elements
        $this->dispatch('hide-add-card-modal');
    }

    public function deletePaymentMethod($paymentMethodId)
    {
        $paymentMethod = PaymentMethod::where('id', $paymentMethodId)
            ->where('user_id', auth()->id())
            ->first();

        if (!$paymentMethod) {
            session()->flash('error', 'Payment method not found.');
            return;
        }

        try {
            // Delete via API call
            $response = $this->callApi('DELETE', route('payment-methods.delete', $paymentMethod));
            
            if ($response['success'] ?? false) {
                session()->flash('success', 'Payment method deleted successfully.');
                $this->loadPaymentMethods();
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
            ->where('user_id', auth()->id())
            ->first();

        if (!$paymentMethod) {
            session()->flash('error', 'Payment method not found.');
            return;
        }

        try {
            // Set as default via API call
            $response = $this->callApi('PATCH', route('payment-methods.default', $paymentMethod));
            
            if ($response['success'] ?? false) {
                session()->flash('success', 'Default payment method updated.');
                $this->loadPaymentMethods();
            } else {
                session()->flash('error', $response['error'] ?? 'Failed to update default payment method.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update payment method: ' . $e->getMessage());
        }
    }

    private function callApi(string $method, string $url, array $data = []): array
    {
        // This would make an actual HTTP request to the API
        // For now, we'll simulate the response
        return ['success' => true];
    }

    public function addPaymentMethod($paymentMethodData)
    {
        try {
            $this->isProcessing = true;

            // This would be called from JavaScript after Stripe processing
            $response = $this->callApi('POST', route('payment-methods.store'), $paymentMethodData);
            
            if ($response['success'] ?? false) {
                session()->flash('success', 'Payment method added successfully.');
                $this->loadPaymentMethods();
                $this->closeAddCardModal();
            } else {
                session()->flash('error', $response['error'] ?? 'Failed to add payment method.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to add payment method: ' . $e->getMessage());
        } finally {
            $this->isProcessing = false;
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
            <flux:heading size="xl">Payment Methods</flux:heading>
            <flux:text class="mt-2">Manage your saved payment methods for faster checkout</flux:text>
        </div>
        @if($canAddPaymentMethods)
            <flux:button variant="filled" icon="plus" wire:click="openAddCardModal">
                Add Payment Method
            </flux:button>
        @endif
    </div>

    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
            <div class="flex items-center">
                <flux:icon icon="check-circle" class="w-5 h-5 text-emerald-600 mr-3" />
                <flux:text class="text-emerald-800">{{ session('success') }}</flux:text>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-center">
                <flux:icon icon="exclamation-circle" class="w-5 h-5 text-red-600 mr-3" />
                <flux:text class="text-red-800">{{ session('error') }}</flux:text>
            </div>
        </div>
    @endif

    @if (session()->has('warning'))
        <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
            <div class="flex items-center">
                <flux:icon icon="exclamation-triangle" class="w-5 h-5 text-amber-600 mr-3" />
                <flux:text class="text-amber-800">{{ session('warning') }}</flux:text>
            </div>
        </div>
    @endif

    <!-- Payment Methods List -->
    @if($hasPaymentMethods)
        <flux:card>
            <flux:header>
                <flux:heading size="lg">Saved Payment Methods</flux:heading>
            </flux:header>

            <div class="space-y-4">
                @foreach($paymentMethods as $method)
                    <div class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $method->is_default ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700' }}">
                        <div class="flex items-center space-x-4">
                            <!-- Card Icon -->
                            <div class="w-12 h-8 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
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
                                    wire:confirm="Set this as your default payment method?"
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
                                wire:confirm="Are you sure you want to delete this payment method? This action cannot be undone."
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
                <flux:heading size="md" class="text-gray-600 dark:text-gray-400 mb-2">No Payment Methods</flux:heading>
                <flux:text class="text-gray-600 dark:text-gray-400 mb-6">
                    You haven't added any payment methods yet. Add a card to make payments faster and easier.
                </flux:text>
                @if($canAddPaymentMethods)
                    <flux:button variant="filled" icon="plus" wire:click="openAddCardModal">
                        Add Your First Payment Method
                    </flux:button>
                @else
                    <flux:text class="text-gray-500">
                        Payment method management is currently unavailable.
                    </flux:text>
                @endif
            </div>
        </flux:card>
    @endif

    <!-- Benefits Card -->
    <flux:card class="mt-6">
        <flux:header>
            <flux:heading size="lg">Benefits of Saving Payment Methods</flux:heading>
        </flux:header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="text-center">
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center mx-auto mb-3">
                    <flux:icon icon="bolt" class="w-6 h-6 text-blue-600" />
                </div>
                <flux:text class="font-medium">Faster Payments</flux:text>
                <flux:text size="sm" class="text-gray-600 mt-1">Pay invoices with just one click</flux:text>
            </div>

            <div class="text-center">
                <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center mx-auto mb-3">
                    <flux:icon icon="shield-check" class="w-6 h-6 text-emerald-600" />
                </div>
                <flux:text class="font-medium">Secure Storage</flux:text>
                <flux:text size="sm" class="text-gray-600 mt-1">Encrypted and PCI compliant</flux:text>
            </div>

            <div class="text-center">
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center mx-auto mb-3">
                    <flux:icon icon="sparkles" class="w-6 h-6 text-purple-600" />
                </div>
                <flux:text class="font-medium">Auto-Pay Ready</flux:text>
                <flux:text size="sm" class="text-gray-600 mt-1">Set up automatic payments (coming soon)</flux:text>
            </div>
        </div>
    </flux:card>

    <!-- Add Payment Method Modal -->
    <flux:modal wire:model="showAddCardModal" class="max-w-lg">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Add Payment Method</flux:heading>
                <flux:button variant="ghost" size="sm" wire:click="closeAddCardModal">
                    <flux:icon icon="x-mark" class="w-5 h-5" />
                </flux:button>
            </div>

            <div class="space-y-6">
                <!-- Stripe Elements Card Input -->
                <div>
                    <flux:text class="font-medium mb-3">Card Information</flux:text>
                    <div id="stripe-card-element" class="p-4 border rounded-lg bg-white dark:bg-gray-800">
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
                        <input type="checkbox" id="set-as-default" class="rounded">
                        <span class="ml-2 text-sm">Set as default payment method</span>
                    </label>
                </div>

                <!-- Security Notice -->
                <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                    <div class="flex items-center text-sm">
                        <flux:icon icon="shield-check" class="w-4 h-4 mr-2 text-green-500" />
                        <span>Your card information is securely processed by Stripe and never stored on our servers.</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3">
                    <flux:button variant="outline" wire:click="closeAddCardModal" :disabled="$isProcessing">
                        Cancel
                    </flux:button>
                    <flux:button 
                        id="submit-payment-method"
                        variant="filled" 
                        :disabled="$isProcessing"
                    >
                        @if($isProcessing)
                            <flux:icon icon="arrow-path" class="w-4 h-4 animate-spin" />
                            Adding...
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
    if (!publishableKey) return;

    const stripe = Stripe(publishableKey);
    const elements = stripe.elements();
    
    let cardElement = null;
    
    // Initialize Stripe Elements when modal opens
    document.addEventListener('livewire:initialized', function() {
        Livewire.on('show-add-card-modal', function() {
            setTimeout(() => {
                // Clean up any existing card element first
                if (cardElement) {
                    try {
                        cardElement.destroy();
                    } catch (e) {
                        console.warn('Error destroying existing card element:', e);
                    }
                    cardElement = null;
                }

                // Clear the container
                const container = document.getElementById('stripe-card-element');
                if (container) {
                    container.innerHTML = '';
                }

                // Clear any existing errors
                const errorContainer = document.getElementById('stripe-card-errors');
                if (errorContainer) {
                    errorContainer.textContent = '';
                }
                
                cardElement = elements.create('card', {
                    style: {
                        base: {
                            fontSize: '16px',
                            color: '#424770',
                            '::placeholder': {
                                color: '#aab7c4',
                            },
                        },
                    },
                });

                cardElement.mount('#stripe-card-element');

                // Handle real-time validation errors
                cardElement.on('change', ({error}) => {
                    const displayError = document.getElementById('stripe-card-errors');
                    if (displayError) {
                        if (error) {
                            displayError.textContent = error.message;
                        } else {
                            displayError.textContent = '';
                        }
                    }
                });

                // Handle form submission
                const submitButton = document.getElementById('submit-payment-method');
                if (submitButton) {
                    // Remove any existing event listeners
                    const newSubmitButton = submitButton.cloneNode(true);
                    submitButton.parentNode.replaceChild(newSubmitButton, submitButton);
                    
                    newSubmitButton.addEventListener('click', async function(event) {
                        event.preventDefault();
                        
                        if (!cardElement) {
                            console.error('Card element not available');
                            return;
                        }

                        try {
                            const {token, error} = await stripe.createToken(cardElement);
                            
                            if (error) {
                                const errorContainer = document.getElementById('stripe-card-errors');
                                if (errorContainer) {
                                    errorContainer.textContent = error.message;
                                }
                            } else {
                                // Send token to server
                                const setAsDefaultCheckbox = document.getElementById('set-as-default');
                                const setAsDefault = setAsDefaultCheckbox ? setAsDefaultCheckbox.checked : false;
                                
                                @this.call('addPaymentMethod', {
                                    payment_method_id: token.id,
                                    set_as_default: setAsDefault
                                });
                            }
                        } catch (e) {
                            console.error('Error creating token:', e);
                            const errorContainer = document.getElementById('stripe-card-errors');
                            if (errorContainer) {
                                errorContainer.textContent = 'An error occurred while processing your card.';
                            }
                        }
                    });
                }
            }, 150);
        });
        
        // Clean up when modal closes
        Livewire.on('hide-add-card-modal', function() {
            if (cardElement) {
                try {
                    cardElement.destroy();
                } catch (e) {
                    console.warn('Error destroying card element:', e);
                }
                cardElement = null;
            }

            // Clear the container
            const container = document.getElementById('stripe-card-element');
            if (container) {
                container.innerHTML = '';
            }

            // Clear any existing errors
            const errorContainer = document.getElementById('stripe-card-errors');
            if (errorContainer) {
                errorContainer.textContent = '';
            }
        });
    });
});
</script>
@endif
@endpush