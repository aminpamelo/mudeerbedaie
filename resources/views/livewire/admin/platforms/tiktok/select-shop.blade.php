<x-layouts.app :title="'Select TikTok Shop'">
    <div class="max-w-2xl mx-auto py-12">
        <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm border border-gray-200 dark:border-zinc-700 p-8">
            {{-- Header --}}
            <div class="text-center mb-8">
                <div class="mx-auto w-16 h-16 bg-gradient-to-br from-pink-500 to-red-500 rounded-2xl flex items-center justify-center mb-4">
                    <svg class="w-10 h-10 text-white" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43V7.56a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.19z"/>
                    </svg>
                </div>
                <flux:heading size="xl">Select Your TikTok Shop</flux:heading>
                <flux:text class="mt-2 text-zinc-600 dark:text-zinc-400">
                    Multiple shops found for your account. Please select which shop you want to connect.
                </flux:text>
            </div>

            {{-- Linking Account Notice --}}
            @if(isset($linkingAccount) && $linkingAccount)
                <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <div class="flex items-start">
                        <flux:icon name="information-circle" class="w-5 h-5 text-blue-500 mt-0.5 mr-3 flex-shrink-0" />
                        <div>
                            <flux:heading size="sm" class="text-blue-800 dark:text-blue-200">Linking to Existing Account</flux:heading>
                            <flux:text size="sm" class="text-blue-700 dark:text-blue-300 mt-1">
                                You are linking to your existing account: <strong>"{{ $linkingAccount->name }}"</strong>
                            </flux:text>
                            <flux:text size="xs" class="text-blue-600 dark:text-blue-400 mt-1">
                                Please select the TikTok Shop that corresponds to this account. The best match has been pre-selected for you.
                            </flux:text>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Shop Selection --}}
            <form action="{{ route('tiktok.confirm-shop') }}" method="POST">
                @csrf

                <div class="space-y-4 mb-8">
                    @foreach($shops as $index => $shop)
                        @php
                            $shopId = $shop['shop_id'] ?? '';
                            $isLinkedToOther = isset($linkedShops[$shopId]);
                            $linkedToAccountName = $linkedShops[$shopId] ?? null;
                            $isRecommended = isset($linkingAccount) && $linkingAccount && $index === ($bestMatchIndex ?? 0) && !$isLinkedToOther;
                            $isChecked = !$isLinkedToOther && (isset($bestMatchIndex) ? $index === $bestMatchIndex : $index === 0);
                        @endphp
                        <label class="block {{ $isLinkedToOther ? 'cursor-not-allowed' : 'cursor-pointer' }}">
                            <input
                                type="radio"
                                name="shop_index"
                                value="{{ $index }}"
                                class="sr-only peer"
                                {{ $isChecked ? 'checked' : '' }}
                                {{ $isLinkedToOther ? 'disabled' : '' }}
                            >
                            <div class="p-4 border-2 rounded-lg transition-all
                                {{ $isLinkedToOther
                                    ? 'border-gray-300 dark:border-zinc-600 bg-gray-100 dark:bg-zinc-700/50 opacity-60'
                                    : 'border-gray-200 dark:border-zinc-700 peer-checked:border-pink-500 peer-checked:bg-pink-50 dark:peer-checked:bg-pink-900/20' }}
                                {{ $isRecommended && !$isLinkedToOther ? 'ring-2 ring-blue-300 dark:ring-blue-700' : '' }}">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-zinc-700 dark:to-zinc-600 rounded-lg flex items-center justify-center">
                                            <span class="text-lg font-bold text-gray-600 dark:text-gray-300">
                                                {{ substr($shop['shop_name'] ?? 'S', 0, 1) }}
                                            </span>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <flux:heading size="sm" class="{{ $isLinkedToOther ? 'text-gray-500' : '' }}">
                                                    {{ $shop['shop_name'] ?: 'Unnamed Shop' }}
                                                </flux:heading>
                                                @if($isLinkedToOther)
                                                    <flux:badge size="sm" color="amber">Already Linked</flux:badge>
                                                @elseif($isRecommended)
                                                    <flux:badge size="sm" color="blue">Recommended</flux:badge>
                                                @endif
                                            </div>
                                            <flux:text size="sm" class="text-zinc-500">
                                                Region: {{ $shop['region'] ?? 'Unknown' }}
                                                @if(!empty($shop['shop_id']))
                                                    | ID: {{ $shop['shop_id'] }}
                                                @endif
                                            </flux:text>
                                            @if($isLinkedToOther)
                                                <flux:text size="xs" class="text-amber-600 dark:text-amber-400 mt-1">
                                                    <flux:icon name="link" class="w-3 h-3 inline mr-1" />
                                                    Linked to: "{{ $linkedToAccountName }}"
                                                </flux:text>
                                            @endif
                                        </div>
                                    </div>
                                    @if(!$isLinkedToOther)
                                        <div class="hidden peer-checked:block text-pink-500">
                                            <flux:icon name="check-circle" class="w-6 h-6" />
                                        </div>
                                    @else
                                        <div class="text-gray-400">
                                            <flux:icon name="x-circle" class="w-6 h-6" />
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>

                {{-- Warning for linking mode --}}
                @if(isset($linkingAccount) && $linkingAccount)
                    <div class="mb-6 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                        <div class="flex items-center">
                            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-amber-500 mr-2 flex-shrink-0" />
                            <flux:text size="sm" class="text-amber-700 dark:text-amber-300">
                                Make sure you select the correct TikTok Shop. The selected shop will be linked to "{{ $linkingAccount->name }}".
                            </flux:text>
                        </div>
                    </div>
                @endif

                {{-- Actions --}}
                <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-zinc-700">
                    <flux:button variant="ghost" :href="route('platforms.index')" wire:navigate>
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        @if(isset($linkingAccount) && $linkingAccount)
                            Link Selected Shop
                        @else
                            Connect Selected Shop
                        @endif
                    </flux:button>
                </div>
            </form>
        </div>

        {{-- Help Text --}}
        <div class="mt-6 text-center">
            <flux:text size="sm" class="text-zinc-500">
                @if(isset($linkingAccount) && $linkingAccount)
                    Selecting the wrong shop? <a href="{{ route('platforms.accounts.index', ['platform' => 'tiktok-shop']) }}" class="text-pink-600 hover:underline">Go back to accounts list</a> and try again.
                @else
                    You can connect additional shops later from the Platform Management page.
                @endif
            </flux:text>
        </div>
    </div>
</x-layouts.app>
