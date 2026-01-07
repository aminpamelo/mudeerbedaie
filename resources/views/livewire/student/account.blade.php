<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component {
    public string $currentLocale = 'ms';

    public function mount(): void
    {
        $this->currentLocale = auth()->user()->locale ?? 'ms';
    }

    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }

    public function updateLocale(string $locale): void
    {
        if (! in_array($locale, ['en', 'ms'])) {
            return;
        }

        auth()->user()->update(['locale' => $locale]);
        $this->currentLocale = $locale;

        $this->redirect(route('student.account'), navigate: true);
    }

    public function with(): array
    {
        $user = auth()->user();
        $student = $user->student;

        $pendingOrdersCount = 0;
        $activeSubscriptionsCount = 0;

        if ($student) {
            $pendingOrdersCount = $student->orders()->where('status', 'pending')->count();
            $activeSubscriptionsCount = $student->classStudents()->where('status', 'active')->count();
        }

        return [
            'user' => $user,
            'student' => $student,
            'pendingOrdersCount' => $pendingOrdersCount,
            'activeSubscriptionsCount' => $activeSubscriptionsCount,
        ];
    }
}; ?>

<div>
    {{-- Profile Header --}}
    <div class="mb-6">
        <flux:card class="hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center gap-4">
                {{-- Avatar with gradient --}}
                <div class="flex-shrink-0 w-16 h-16 bg-gradient-to-br from-blue-100 to-blue-200 dark:from-blue-900 dark:to-blue-800 rounded-full flex items-center justify-center shadow-inner">
                    <span class="text-2xl font-bold text-blue-600 dark:text-blue-300">{{ $user->initials() }}</span>
                </div>

                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <flux:heading size="lg" class="truncate">{{ $user->name }}</flux:heading>
                    <flux:text size="sm" class="text-gray-500 dark:text-gray-400 truncate">{{ $user->email }}</flux:text>
                    @if($student && $student->phone)
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ $student->phone }}</flux:text>
                    @endif
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Quick Stats (Alert Cards) --}}
    @if($pendingOrdersCount > 0)
        <div class="mb-6">
            <a href="{{ route('student.orders') }}" wire:navigate class="group block">
                <flux:card class="bg-orange-50 dark:bg-orange-900/30 border-orange-200 dark:border-orange-800 group-hover:bg-orange-100 dark:group-hover:bg-orange-900/50 group-active:bg-orange-150 transition-all duration-150 group-hover:shadow-md">
                    <div class="text-center">
                        <flux:heading size="lg" class="text-orange-600 dark:text-orange-400 group-hover:scale-110 transition-transform duration-150">{{ $pendingOrdersCount }}</flux:heading>
                        <flux:text size="xs" class="text-orange-700 dark:text-orange-300">{{ __('student.account.pending_orders') }}</flux:text>
                    </div>
                </flux:card>
            </a>
        </div>
    @endif

    {{-- Account Menu --}}
    <div class="space-y-3">
        {{-- Orders --}}
        <a href="{{ route('student.orders') }}" wire:navigate class="block group">
            <flux:card class="group-hover:bg-gray-50 dark:group-hover:bg-zinc-700 group-active:bg-gray-100 dark:group-active:bg-zinc-600 transition-all duration-150">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-50 dark:bg-blue-900/30 rounded-lg flex items-center justify-center group-hover:bg-blue-100 dark:group-hover:bg-blue-900/50 transition-colors duration-150">
                        <flux:icon name="shopping-bag" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <flux:text class="font-medium">{{ __('student.account.orders') }}</flux:text>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.account.view_order_history') }}</flux:text>
                    </div>
                    @if($pendingOrdersCount > 0)
                        <flux:badge variant="warning" size="sm">{{ $pendingOrdersCount }}</flux:badge>
                    @endif
                    <flux:icon name="chevron-right" class="w-5 h-5 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 group-hover:translate-x-0.5 transition-all duration-150" />
                </div>
            </flux:card>
        </a>

        {{-- Invoices --}}
        <a href="{{ route('student.invoices') }}" wire:navigate class="block group">
            <flux:card class="group-hover:bg-gray-50 dark:group-hover:bg-zinc-700 group-active:bg-gray-100 dark:group-active:bg-zinc-600 transition-all duration-150">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-10 h-10 bg-purple-50 dark:bg-purple-900/30 rounded-lg flex items-center justify-center group-hover:bg-purple-100 dark:group-hover:bg-purple-900/50 transition-colors duration-150">
                        <flux:icon name="document-text" class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <flux:text class="font-medium">{{ __('student.account.invoices') }}</flux:text>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.account.view_pay_invoices') }}</flux:text>
                    </div>
                    <flux:icon name="chevron-right" class="w-5 h-5 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 group-hover:translate-x-0.5 transition-all duration-150" />
                </div>
            </flux:card>
        </a>

        {{-- Subscriptions --}}
        <a href="{{ route('student.subscriptions') }}" wire:navigate class="block group">
            <flux:card class="group-hover:bg-gray-50 dark:group-hover:bg-zinc-700 group-active:bg-gray-100 dark:group-active:bg-zinc-600 transition-all duration-150">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-10 h-10 bg-green-50 dark:bg-green-900/30 rounded-lg flex items-center justify-center group-hover:bg-green-100 dark:group-hover:bg-green-900/50 transition-colors duration-150">
                        <flux:icon name="arrow-path" class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <flux:text class="font-medium">{{ __('student.account.subscriptions') }}</flux:text>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.account.manage_subscriptions') }}</flux:text>
                    </div>
                    @if($activeSubscriptionsCount > 0)
                        <flux:badge variant="success" size="sm">{{ $activeSubscriptionsCount }}</flux:badge>
                    @endif
                    <flux:icon name="chevron-right" class="w-5 h-5 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 group-hover:translate-x-0.5 transition-all duration-150" />
                </div>
            </flux:card>
        </a>

        {{-- Payment Methods --}}
        <a href="{{ route('student.payment-methods') }}" wire:navigate class="block group">
            <flux:card class="group-hover:bg-gray-50 dark:group-hover:bg-zinc-700 group-active:bg-gray-100 dark:group-active:bg-zinc-600 transition-all duration-150">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-10 h-10 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg flex items-center justify-center group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/50 transition-colors duration-150">
                        <flux:icon name="credit-card" class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <flux:text class="font-medium">{{ __('student.account.payment_methods') }}</flux:text>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.account.manage_cards') }}</flux:text>
                    </div>
                    <flux:icon name="chevron-right" class="w-5 h-5 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 group-hover:translate-x-0.5 transition-all duration-150" />
                </div>
            </flux:card>
        </a>

        {{-- Payment History --}}
        <a href="{{ route('student.payments') }}" wire:navigate class="block group">
            <flux:card class="group-hover:bg-gray-50 dark:group-hover:bg-zinc-700 group-active:bg-gray-100 dark:group-active:bg-zinc-600 transition-all duration-150">
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-10 h-10 bg-teal-50 dark:bg-teal-900/30 rounded-lg flex items-center justify-center group-hover:bg-teal-100 dark:group-hover:bg-teal-900/50 transition-colors duration-150">
                        <flux:icon name="banknotes" class="w-5 h-5 text-teal-600 dark:text-teal-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <flux:text class="font-medium">{{ __('student.account.payment_history') }}</flux:text>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.account.view_transactions') }}</flux:text>
                    </div>
                    <flux:icon name="chevron-right" class="w-5 h-5 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 group-hover:translate-x-0.5 transition-all duration-150" />
                </div>
            </flux:card>
        </a>
    </div>

    {{-- Settings Section --}}
    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-zinc-700">
        <flux:text size="sm" class="text-gray-500 dark:text-gray-400 mb-3 px-1 uppercase tracking-wider font-medium">{{ __('student.account.settings') }}</flux:text>

        <div class="space-y-3">
            {{-- Profile Settings --}}
            <a href="{{ route('settings.profile') }}" wire:navigate class="block group">
                <flux:card class="group-hover:bg-gray-50 dark:group-hover:bg-zinc-700 group-active:bg-gray-100 dark:group-active:bg-zinc-600 transition-all duration-150">
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0 w-10 h-10 bg-gray-100 dark:bg-zinc-700 rounded-lg flex items-center justify-center group-hover:bg-gray-200 dark:group-hover:bg-zinc-600 transition-colors duration-150">
                            <flux:icon name="user-circle" class="w-5 h-5 text-gray-600 dark:text-gray-300" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <flux:text class="font-medium">{{ __('student.account.profile_settings') }}</flux:text>
                            <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.account.update_info') }}</flux:text>
                        </div>
                        <flux:icon name="chevron-right" class="w-5 h-5 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 group-hover:translate-x-0.5 transition-all duration-150" />
                    </div>
                </flux:card>
            </a>

            {{-- Password --}}
            <a href="{{ route('settings.password') }}" wire:navigate class="block group">
                <flux:card class="group-hover:bg-gray-50 dark:group-hover:bg-zinc-700 group-active:bg-gray-100 dark:group-active:bg-zinc-600 transition-all duration-150">
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0 w-10 h-10 bg-gray-100 dark:bg-zinc-700 rounded-lg flex items-center justify-center group-hover:bg-gray-200 dark:group-hover:bg-zinc-600 transition-colors duration-150">
                            <flux:icon name="lock-closed" class="w-5 h-5 text-gray-600 dark:text-gray-300" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <flux:text class="font-medium">{{ __('student.account.password') }}</flux:text>
                            <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.account.change_password') }}</flux:text>
                        </div>
                        <flux:icon name="chevron-right" class="w-5 h-5 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 group-hover:translate-x-0.5 transition-all duration-150" />
                    </div>
                </flux:card>
            </a>

            {{-- Appearance --}}
            <flux:card x-data>
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-10 h-10 bg-amber-50 dark:bg-amber-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon name="sun" class="w-5 h-5 text-amber-600 dark:text-amber-400" x-show="$flux.appearance === 'light'" />
                        <flux:icon name="moon" class="w-5 h-5 text-amber-600 dark:text-amber-400" x-show="$flux.appearance === 'dark'" />
                        <flux:icon name="computer-desktop" class="w-5 h-5 text-amber-600 dark:text-amber-400" x-show="$flux.appearance === 'system'" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <flux:text class="font-medium">{{ __('student.account.appearance') }}</flux:text>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.account.switch_theme') }}</flux:text>
                    </div>
                </div>
                <div class="mt-4">
                    <flux:radio.group variant="segmented" x-model="$flux.appearance" class="w-full">
                        <flux:radio value="light" icon="sun">{{ __('student.account.light') }}</flux:radio>
                        <flux:radio value="dark" icon="moon">{{ __('student.account.dark') }}</flux:radio>
                        <flux:radio value="system" icon="computer-desktop">{{ __('student.account.system') }}</flux:radio>
                    </flux:radio.group>
                </div>
            </flux:card>

            {{-- Language --}}
            <flux:card>
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 w-10 h-10 bg-emerald-50 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center">
                        <flux:icon name="language" class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <flux:text class="font-medium">{{ __('student.account.language') }}</flux:text>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.account.select_language') }}</flux:text>
                    </div>
                </div>
                <div class="mt-4">
                    <flux:radio.group variant="segmented" wire:model="currentLocale" class="w-full">
                        <flux:radio value="ms" wire:click="updateLocale('ms')">Bahasa Malaysia</flux:radio>
                        <flux:radio value="en" wire:click="updateLocale('en')">English</flux:radio>
                    </flux:radio.group>
                </div>
            </flux:card>
        </div>
    </div>

    {{-- Logout Button --}}
    <div class="mt-6">
        <flux:button
            wire:click="logout"
            wire:loading.attr="disabled"
            variant="ghost"
            class="w-full text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 active:bg-red-100 dark:active:bg-red-900/50 transition-colors duration-150"
        >
            <div class="flex items-center justify-center gap-2">
                <flux:icon wire:loading.remove name="arrow-right-start-on-rectangle" class="w-5 h-5" />
                <flux:icon wire:loading name="arrow-path" class="w-5 h-5 animate-spin" />
                <span wire:loading.remove>{{ __('student.account.logout') }}</span>
                <span wire:loading>{{ __('student.account.logging_out') }}</span>
            </div>
        </flux:button>
    </div>
</div>
