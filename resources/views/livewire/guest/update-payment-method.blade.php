<?php

use App\Models\PaymentMethodToken;
use App\Services\SettingsService;
use App\Services\StripeService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest')] class extends Component
{
    public string $token;

    public ?PaymentMethodToken $tokenModel = null;

    public $student = null;

    public $paymentMethods;

    public $stripePublishableKey = '';

    public bool $isProcessing = false;

    public bool $isSubmitting = false;

    public bool $isValidToken = false;

    public string $errorMessage = '';

    public string $errorMessageMy = '';

    public bool $showSuccess = false;

    // Editable user information
    public string $editName = '';

    public string $editEmail = '';

    public string $editPhone = '';

    public bool $isEditingInfo = false;

    public bool $isSavingInfo = false;

    public bool $infoUpdated = false;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->validateToken();

        if ($this->isValidToken) {
            $this->loadPaymentMethods();
            $this->initializeStripe();
            $this->loadUserInfo();
        }
    }

    protected function loadUserInfo(): void
    {
        if ($this->student && $this->student->user) {
            $this->editName = $this->student->user->name ?? '';
            $this->editEmail = $this->student->user->email ?? '';
            $this->editPhone = $this->student->phone_number ?? '';
        }
    }

    public function startEditingInfo(): void
    {
        $this->isEditingInfo = true;
        $this->infoUpdated = false;
    }

    public function cancelEditingInfo(): void
    {
        $this->isEditingInfo = false;
        $this->loadUserInfo();
    }

    public function saveUserInfo(): void
    {
        $this->validate([
            'editName' => 'required|string|min:2|max:255',
            'editEmail' => 'required|email|max:255',
            'editPhone' => 'nullable|string|max:20',
        ], [
            'editName.required' => 'Name is required / Nama diperlukan',
            'editName.min' => 'Name must be at least 2 characters / Nama mestilah sekurang-kurangnya 2 aksara',
            'editEmail.required' => 'Email is required / E-mel diperlukan',
            'editEmail.email' => 'Please enter a valid email / Sila masukkan e-mel yang sah',
        ]);

        try {
            $this->isSavingInfo = true;

            // Update user name and email
            $this->student->user->update([
                'name' => $this->editName,
                'email' => $this->editEmail,
            ]);

            // Update student phone
            $this->student->update([
                'phone' => $this->editPhone,
            ]);

            // Reload the student data
            $this->student->refresh();
            $this->student->load('user');

            $this->isEditingInfo = false;
            $this->infoUpdated = true;

            \Log::info('Guest updated user info via magic link', [
                'student_id' => $this->student->id,
                'token_id' => $this->tokenModel->id,
            ]);

        } catch (\Exception $e) {
            \Log::error('Guest failed to update user info', [
                'student_id' => $this->student?->id,
                'error' => $e->getMessage(),
            ]);

            session()->flash('error', 'Failed to update information: '.$e->getMessage());
            session()->flash('error_my', 'Gagal mengemaskini maklumat: '.$e->getMessage());
        } finally {
            $this->isSavingInfo = false;
        }
    }

    protected function validateToken(): void
    {
        $this->tokenModel = PaymentMethodToken::findValidToken($this->token);

        if (! $this->tokenModel) {
            // Check if token exists but is expired
            $expiredToken = PaymentMethodToken::where('token', $this->token)->first();

            if ($expiredToken) {
                // Log detailed info for debugging
                \Log::warning('Magic link validation failed', [
                    'token_id' => $expiredToken->id,
                    'token_prefix' => substr($this->token, 0, 10) . '...',
                    'student_id' => $expiredToken->student_id,
                    'is_active' => $expiredToken->is_active,
                    'expires_at' => $expiredToken->expires_at?->toIso8601String(),
                    'now' => now()->toIso8601String(),
                    'is_expired_check' => $expiredToken->isExpired(),
                    'expires_at_is_past' => $expiredToken->expires_at?->isPast(),
                    'created_at' => $expiredToken->created_at?->toIso8601String(),
                ]);

                if ($expiredToken->isExpired()) {
                    $this->errorMessage = 'This link has expired. Please contact the administrator for a new link.';
                    $this->errorMessageMy = 'Pautan ini telah tamat tempoh. Sila hubungi pentadbir untuk pautan baharu.';
                } else {
                    $this->errorMessage = 'This link is no longer valid (deactivated). Please contact the administrator for a new link.';
                    $this->errorMessageMy = 'Pautan ini tidak lagi sah (dinyahaktifkan). Sila hubungi pentadbir untuk pautan baharu.';
                }
            } else {
                \Log::warning('Magic link not found in database', [
                    'token_prefix' => substr($this->token, 0, 10) . '...',
                    'token_length' => strlen($this->token),
                ]);
                $this->errorMessage = 'Invalid link. Please contact the administrator for assistance.';
                $this->errorMessageMy = 'Pautan tidak sah. Sila hubungi pentadbir untuk bantuan.';
            }

            $this->isValidToken = false;

            return;
        }

        $this->isValidToken = true;
        $this->student = $this->tokenModel->student;
        $this->student->load('user');

        // Record token usage
        $this->tokenModel->recordUsage();

        \Log::info('Magic link validated successfully', [
            'token_id' => $this->tokenModel->id,
            'student_id' => $this->student->id,
            'expires_at' => $this->tokenModel->expires_at->toIso8601String(),
        ]);
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
        if (! $this->student) {
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
        if (! $this->isValidToken || ! $this->student) {
            session()->flash('error', 'Invalid session. Please refresh the page.');
            session()->flash('error_my', 'Sesi tidak sah. Sila muat semula halaman.');

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
                session()->flash('success_my', 'Kaedah pembayaran berjaya ditambah! Anda boleh menutup halaman ini.');
            } else {
                session()->flash('error', 'Failed to add payment method. Please try again.');
                session()->flash('error_my', 'Gagal menambah kaedah pembayaran. Sila cuba lagi.');
            }
        } catch (\Exception $e) {
            \Log::error('Guest failed to add payment method via magic link', [
                'student_id' => $this->student?->id,
                'token_id' => $this->tokenModel?->id,
                'error' => $e->getMessage(),
            ]);

            session()->flash('error', 'Failed to add payment method: '.$e->getMessage());
            session()->flash('error_my', 'Gagal menambah kaedah pembayaran: '.$e->getMessage());
        } finally {
            $this->isProcessing = false;
            $this->isSubmitting = false;
        }
    }

    public function with(): array
    {
        $settingsService = app(SettingsService::class);

        return [
            'hasPaymentMethods' => $this->paymentMethods?->count() > 0,
            'canAddPaymentMethods' => ! empty($this->stripePublishableKey),
            'logoUrl' => $settingsService->getLogo(),
            'siteName' => $settingsService->get('site_name', 'Pengurusan Kelas'),
        ];
    }
}; ?>

<div class="min-h-screen bg-gray-50 dark:bg-zinc-900 py-12 px-4 sm:px-6 lg:px-8" x-data="{ lang: 'my' }">
    <div class="max-w-lg mx-auto">
        <!-- Language Toggle -->
        <div class="flex justify-end mb-4">
            <div class="inline-flex rounded-lg border border-gray-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-1">
                <button
                    @click="lang = 'my'"
                    :class="lang === 'my' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-100 dark:hover:bg-zinc-700'"
                    class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors"
                >
                    BM
                </button>
                <button
                    @click="lang = 'en'"
                    :class="lang === 'en' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-zinc-400 hover:bg-gray-100 dark:hover:bg-zinc-700'"
                    class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors"
                >
                    EN
                </button>
            </div>
        </div>

        <!-- Logo/Branding -->
        <div class="text-center mb-8">
            <!-- Dual Logo Display -->
            <div class="flex items-center justify-center gap-4 mb-2">
                <!-- BeDaie Company Logo -->
                <a href="https://bedaie.com" target="_blank" rel="noopener noreferrer" class="hover:opacity-80 transition-opacity">
                    <img src="{{ asset('images/bedaie-brand.png') }}" alt="BeDaie" class="h-12 w-auto object-contain">
                </a>
                <!-- Separator -->
                <div class="h-10 w-px bg-gray-300 dark:bg-zinc-600"></div>
                <!-- Application Logo -->
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="h-12 w-auto object-contain">
                @endif
            </div>
            <!-- Company Disclosure -->
            <flux:text size="xs" class="text-gray-400 dark:text-zinc-500 mb-4">
                <span x-show="lang === 'my'">Platform ini dibangunkan oleh <span class="font-medium">BeDaie Sdn. Bhd.</span></span>
                <span x-show="lang === 'en'" x-cloak>This platform is developed by <span class="font-medium">BeDaie Sdn. Bhd.</span></span>
            </flux:text>
            <flux:heading size="xl" class="text-gray-900 dark:text-white">
                <span x-show="lang === 'my'">Kemaskini Kaedah Pembayaran</span>
                <span x-show="lang === 'en'" x-cloak>Update Payment Method</span>
            </flux:heading>
            <flux:text class="mt-2 text-gray-600 dark:text-zinc-400">
                <span x-show="lang === 'my'">Pengurusan kaedah pembayaran yang selamat</span>
                <span x-show="lang === 'en'" x-cloak>Secure payment method management</span>
            </flux:text>
        </div>

        @if(!$isValidToken)
            <!-- Invalid/Expired Token -->
            <flux:card class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700">
                <div class="text-center py-8">
                    <div class="w-16 h-16 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                        <flux:icon icon="exclamation-triangle" class="w-8 h-8 text-red-600 dark:text-red-400" />
                    </div>
                    <flux:heading size="lg" class="text-gray-900 dark:text-white mb-2">
                        <span x-show="lang === 'my'">Pautan Tidak Sah</span>
                        <span x-show="lang === 'en'" x-cloak>Link Not Valid</span>
                    </flux:heading>
                    <flux:text class="text-gray-600 dark:text-zinc-400 mb-6">
                        <span x-show="lang === 'my'">{{ $errorMessageMy }}</span>
                        <span x-show="lang === 'en'" x-cloak>{{ $errorMessage }}</span>
                    </flux:text>
                    <flux:text size="sm" class="text-gray-500 dark:text-zinc-500">
                        <span x-show="lang === 'my'">Jika anda percaya ini adalah kesilapan, sila hubungi pentadbir anda.</span>
                        <span x-show="lang === 'en'" x-cloak>If you believe this is an error, please contact your administrator.</span>
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
                    <flux:heading size="lg" class="text-gray-900 dark:text-white mb-2">
                        <span x-show="lang === 'my'">Kaedah Pembayaran Ditambah!</span>
                        <span x-show="lang === 'en'" x-cloak>Payment Method Added!</span>
                    </flux:heading>
                    <flux:text class="text-gray-600 dark:text-zinc-400 mb-6">
                        <span x-show="lang === 'my'">Kaedah pembayaran anda telah berjaya disimpan. Anda boleh menutup halaman ini sekarang.</span>
                        <span x-show="lang === 'en'" x-cloak>Your payment method has been successfully saved. You can now close this page.</span>
                    </flux:text>

                    @if($hasPaymentMethods)
                        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-zinc-700">
                            <flux:text class="font-medium text-gray-900 dark:text-white mb-4">
                                <span x-show="lang === 'my'">Kaedah Pembayaran Anda</span>
                                <span x-show="lang === 'en'" x-cloak>Your Saved Payment Methods</span>
                            </flux:text>
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
                                                    <span x-show="lang === 'my'">Tamat {{ $method->card_details['exp_month'] ?? '**' }}/{{ $method->card_details['exp_year'] ?? '**' }}</span>
                                                    <span x-show="lang === 'en'" x-cloak>Expires {{ $method->card_details['exp_month'] ?? '**' }}/{{ $method->card_details['exp_year'] ?? '**' }}</span>
                                                </flux:text>
                                            </div>
                                        </div>
                                        @if($method->is_default)
                                            <flux:badge color="blue" size="sm">
                                                <span x-show="lang === 'my'">Utama</span>
                                                <span x-show="lang === 'en'" x-cloak>Default</span>
                                            </flux:badge>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </flux:card>
        @else
            <!-- Your Information Card -->
            <flux:card class="mb-6 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700">
                <div class="space-y-4">
                    <!-- Header with Edit Button -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center">
                                <flux:icon icon="user" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <flux:heading size="md" class="text-gray-900 dark:text-white">
                                <span x-show="lang === 'my'">Maklumat Anda</span>
                                <span x-show="lang === 'en'" x-cloak>Your Information</span>
                            </flux:heading>
                        </div>
                        @if(!$isEditingInfo)
                            <flux:button variant="ghost" size="sm" wire:click="startEditingInfo">
                                <flux:icon icon="pencil" class="w-4 h-4 mr-1" />
                                <span x-show="lang === 'my'">Kemaskini</span>
                                <span x-show="lang === 'en'" x-cloak>Edit</span>
                            </flux:button>
                        @endif
                    </div>

                    <!-- Guidance Notice -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                        <div class="flex items-start">
                            <flux:icon icon="information-circle" class="w-5 h-5 text-blue-500 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                            <div class="ml-3">
                                <p class="text-sm text-blue-800 dark:text-blue-200">
                                    <span x-show="lang === 'my'">
                                        Sila pastikan maklumat anda adalah tepat. Maklumat ini akan digunakan untuk menghubungi anda mengenai pembayaran dan notifikasi penting.
                                    </span>
                                    <span x-show="lang === 'en'" x-cloak>
                                        Please ensure your information is accurate. This information will be used to contact you regarding payments and important notifications.
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Info Updated Success -->
                    @if($infoUpdated)
                        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3">
                            <div class="flex items-center">
                                <flux:icon icon="check-circle" class="w-5 h-5 text-green-500 dark:text-green-400 flex-shrink-0" />
                                <p class="ml-3 text-sm font-medium text-green-800 dark:text-green-200">
                                    <span x-show="lang === 'my'">Maklumat anda telah berjaya dikemaskini!</span>
                                    <span x-show="lang === 'en'" x-cloak>Your information has been updated successfully!</span>
                                </p>
                            </div>
                        </div>
                    @endif

                    @if($isEditingInfo)
                        <!-- Editable Form -->
                        <div class="space-y-4">
                            <!-- Name Field -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">
                                    <span x-show="lang === 'my'">Nama Penuh</span>
                                    <span x-show="lang === 'en'" x-cloak>Full Name</span>
                                    <span class="text-red-500">*</span>
                                </label>
                                <flux:input
                                    wire:model="editName"
                                    type="text"
                                    placeholder="{{ __('Enter your full name') }}"
                                />
                                @error('editName')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Email Field -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">
                                    <span x-show="lang === 'my'">Alamat E-mel</span>
                                    <span x-show="lang === 'en'" x-cloak>Email Address</span>
                                    <span class="text-red-500">*</span>
                                </label>
                                <flux:input
                                    wire:model="editEmail"
                                    type="email"
                                    placeholder="{{ __('Enter your email') }}"
                                />
                                @error('editEmail')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Phone Field -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-1">
                                    <span x-show="lang === 'my'">No. Telefon</span>
                                    <span x-show="lang === 'en'" x-cloak>Phone Number</span>
                                </label>
                                <flux:input
                                    wire:model="editPhone"
                                    type="tel"
                                    placeholder="e.g. 012-3456789"
                                />
                                @error('editPhone')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex items-center justify-end space-x-3 pt-2">
                                <flux:button variant="ghost" wire:click="cancelEditingInfo" :disabled="$isSavingInfo">
                                    <span x-show="lang === 'my'">Batal</span>
                                    <span x-show="lang === 'en'" x-cloak>Cancel</span>
                                </flux:button>
                                <flux:button variant="primary" wire:click="saveUserInfo" :disabled="$isSavingInfo">
                                    @if($isSavingInfo)
                                        <flux:icon icon="arrow-path" class="w-4 h-4 animate-spin mr-2" />
                                        <span x-show="lang === 'my'">Menyimpan...</span>
                                        <span x-show="lang === 'en'" x-cloak>Saving...</span>
                                    @else
                                        <flux:icon icon="check" class="w-4 h-4 mr-1" />
                                        <span x-show="lang === 'my'">Simpan Maklumat</span>
                                        <span x-show="lang === 'en'" x-cloak>Save Information</span>
                                    @endif
                                </flux:button>
                            </div>
                        </div>
                    @else
                        <!-- Display Mode -->
                        <div class="space-y-3">
                            <!-- Name -->
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-zinc-700">
                                <div class="flex items-center space-x-3">
                                    <flux:icon icon="user" class="w-5 h-5 text-gray-400 dark:text-zinc-500" />
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-zinc-500">
                                            <span x-show="lang === 'my'">Nama</span>
                                            <span x-show="lang === 'en'" x-cloak>Name</span>
                                        </p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $student->user->name }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-zinc-700">
                                <div class="flex items-center space-x-3">
                                    <flux:icon icon="envelope" class="w-5 h-5 text-gray-400 dark:text-zinc-500" />
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-zinc-500">
                                            <span x-show="lang === 'my'">E-mel</span>
                                            <span x-show="lang === 'en'" x-cloak>Email</span>
                                        </p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $student->user->email }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Phone -->
                            <div class="flex items-center justify-between py-2">
                                <div class="flex items-center space-x-3">
                                    <flux:icon icon="phone" class="w-5 h-5 text-gray-400 dark:text-zinc-500" />
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-zinc-500">
                                            <span x-show="lang === 'my'">No. Telefon</span>
                                            <span x-show="lang === 'en'" x-cloak>Phone Number</span>
                                        </p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                            @if($student->phone_number)
                                                {{ $student->phone_number }}
                                            @else
                                                <span class="text-gray-400 dark:text-zinc-500 italic">
                                                    <span x-show="lang === 'my'">Tidak ditetapkan</span>
                                                    <span x-show="lang === 'en'" x-cloak>Not set</span>
                                                </span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </flux:card>

            <!-- Flash Messages -->
            @if (session('success'))
                <div class="mb-6 rounded-md bg-green-50 dark:bg-green-900/30 p-4 border border-green-200 dark:border-green-800">
                    <div class="flex">
                        <flux:icon icon="check-circle" class="h-5 w-5 text-green-400" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800 dark:text-green-200">
                                <span x-show="lang === 'my'">{{ session('success_my', session('success')) }}</span>
                                <span x-show="lang === 'en'" x-cloak>{{ session('success') }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 rounded-md bg-red-50 dark:bg-red-900/30 p-4 border border-red-200 dark:border-red-800">
                    <div class="flex">
                        <flux:icon icon="x-circle" class="h-5 w-5 text-red-400" />
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800 dark:text-red-200">
                                <span x-show="lang === 'my'">{{ session('error_my', session('error')) }}</span>
                                <span x-show="lang === 'en'" x-cloak>{{ session('error') }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Current Payment Methods -->
            @if($hasPaymentMethods)
                <flux:card class="mb-6 bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700">
                    <flux:heading size="md" class="mb-4 text-gray-900 dark:text-white">
                        <span x-show="lang === 'my'">Kaedah Pembayaran Semasa</span>
                        <span x-show="lang === 'en'" x-cloak>Current Payment Methods</span>
                    </flux:heading>
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
                                            <span x-show="lang === 'my'">Tamat {{ $method->card_details['exp_month'] ?? '**' }}/{{ $method->card_details['exp_year'] ?? '**' }}</span>
                                            <span x-show="lang === 'en'" x-cloak>Expires {{ $method->card_details['exp_month'] ?? '**' }}/{{ $method->card_details['exp_year'] ?? '**' }}</span>
                                        </flux:text>
                                    </div>
                                </div>
                                @if($method->is_default)
                                    <flux:badge color="blue" size="sm">
                                        <span x-show="lang === 'my'">Utama</span>
                                        <span x-show="lang === 'en'" x-cloak>Default</span>
                                    </flux:badge>
                                @endif
                                @if($method->is_expired)
                                    <flux:badge color="red" size="sm">
                                        <span x-show="lang === 'my'">Tamat Tempoh</span>
                                        <span x-show="lang === 'en'" x-cloak>Expired</span>
                                    </flux:badge>
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
                        @if($hasPaymentMethods)
                            <span x-show="lang === 'my'">Tambah Kaedah Pembayaran Baharu</span>
                            <span x-show="lang === 'en'" x-cloak>Add New Payment Method</span>
                        @else
                            <span x-show="lang === 'my'">Tambah Kaedah Pembayaran</span>
                            <span x-show="lang === 'en'" x-cloak>Add Payment Method</span>
                        @endif
                    </flux:heading>

                    <div class="space-y-6">
                        <!-- Stripe Elements Card Input -->
                        <div>
                            <flux:text class="font-medium mb-3 text-gray-900 dark:text-white">
                                <span x-show="lang === 'my'">Maklumat Kad</span>
                                <span x-show="lang === 'en'" x-cloak>Card Information</span>
                            </flux:text>
                            <div id="stripe-card-element" class="p-4 border border-gray-300 dark:border-zinc-600 rounded-lg bg-white dark:bg-zinc-900 min-h-[50px]">
                                <!-- Stripe Elements will be mounted here -->
                                <div class="text-center text-gray-500 dark:text-zinc-400 py-4">
                                    <flux:icon icon="credit-card" class="w-6 h-6 mx-auto mb-2" />
                                    <div class="text-sm">
                                        <span x-show="lang === 'my'">Memuatkan input kad...</span>
                                        <span x-show="lang === 'en'" x-cloak>Loading card input...</span>
                                    </div>
                                </div>
                            </div>
                            <div id="stripe-card-errors" class="mt-2 text-red-600 dark:text-red-400 text-sm"></div>
                        </div>

                        <!-- Set as Default Option -->
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" id="set-as-default" class="rounded border-gray-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500 dark:bg-zinc-700 dark:checked:bg-blue-600" checked>
                                <span class="ml-2 text-sm text-gray-700 dark:text-zinc-300">
                                    <span x-show="lang === 'my'">Tetapkan sebagai kaedah pembayaran utama</span>
                                    <span x-show="lang === 'en'" x-cloak>Set as default payment method</span>
                                </span>
                            </label>
                        </div>

                        <!-- Security Notice -->
                        <div class="bg-gray-50 dark:bg-zinc-700/50 p-4 rounded-lg">
                            <div class="flex items-start text-sm">
                                <flux:icon icon="shield-check" class="w-5 h-5 mr-2 text-green-500 dark:text-green-400 flex-shrink-0 mt-0.5" />
                                <div class="text-gray-600 dark:text-zinc-300">
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        <span x-show="lang === 'my'">Pemprosesan Pembayaran Selamat</span>
                                        <span x-show="lang === 'en'" x-cloak>Secure Payment Processing</span>
                                    </span><br>
                                    <span x-show="lang === 'my'">Maklumat kad anda diproses dengan selamat oleh Stripe dan tidak pernah disimpan di pelayan kami.</span>
                                    <span x-show="lang === 'en'" x-cloak>Your card information is securely processed by Stripe and never stored on our servers.</span>
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
                                        <span x-show="lang === 'my'">Memproses Kad...</span>
                                        <span x-show="lang === 'en'" x-cloak>Processing Card...</span>
                                    @else
                                        <span x-show="lang === 'my'">Menambah Kaedah Pembayaran...</span>
                                        <span x-show="lang === 'en'" x-cloak>Adding Payment Method...</span>
                                    @endif
                                </div>
                            @else
                                <div class="flex items-center justify-center">
                                    <flux:icon icon="credit-card" class="w-4 h-4 mr-2" />
                                    <span x-show="lang === 'my'">Tambah Kaedah Pembayaran</span>
                                    <span x-show="lang === 'en'" x-cloak>Add Payment Method</span>
                                </div>
                            @endif
                        </flux:button>
                    </div>
                </flux:card>
            @else
                <flux:card class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700">
                    <div class="text-center py-8">
                        <flux:icon icon="exclamation-triangle" class="w-12 h-12 text-amber-500 dark:text-amber-400 mx-auto mb-4" />
                        <flux:heading size="md" class="text-gray-700 dark:text-zinc-300 mb-2">
                            <span x-show="lang === 'my'">Sistem Pembayaran Tidak Tersedia</span>
                            <span x-show="lang === 'en'" x-cloak>Payment System Unavailable</span>
                        </flux:heading>
                        <flux:text class="text-gray-600 dark:text-zinc-400">
                            <span x-show="lang === 'my'">Sistem pembayaran tidak tersedia buat masa ini. Sila cuba lagi kemudian atau hubungi sokongan.</span>
                            <span x-show="lang === 'en'" x-cloak>The payment system is currently unavailable. Please try again later or contact support.</span>
                        </flux:text>
                    </div>
                </flux:card>
            @endif

            <!-- Link Expiry Notice -->
            @if($tokenModel)
                <div class="mt-6 text-center">
                    <flux:text size="sm" class="text-gray-500 dark:text-zinc-500">
                        <span x-show="lang === 'my'">Pautan ini tamat tempoh {{ $tokenModel->expires_in }}</span>
                        <span x-show="lang === 'en'" x-cloak>This link expires {{ $tokenModel->expires_in }}</span>
                    </flux:text>
                </div>
            @endif
        @endif

        <!-- Powered by BeDaie Branding -->
        <div class="mt-10 pt-6 border-t border-gray-200 dark:border-zinc-700">
            <div class="flex flex-col items-center justify-center space-y-3">
                <a href="https://bedaie.com" target="_blank" rel="noopener noreferrer" class="flex items-center hover:opacity-80 transition-opacity">
                    <img src="{{ asset('images/bedaie-brand.png') }}" alt="BeDaie" class="h-10 w-auto">
                </a>
                <div class="text-center">
                    <flux:text size="sm" class="text-gray-400 dark:text-zinc-500">
                        <span x-show="lang === 'my'">Platform ini dibangunkan oleh</span>
                        <span x-show="lang === 'en'" x-cloak>This platform is developed by</span>
                    </flux:text>
                    <flux:text size="sm" class="font-medium text-gray-500 dark:text-zinc-400">
                        BeDaie Sdn. Bhd.
                    </flux:text>
                </div>
            </div>
        </div>
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
