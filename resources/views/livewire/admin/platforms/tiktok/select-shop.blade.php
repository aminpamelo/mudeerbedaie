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

            {{-- Shop Selection --}}
            <form action="{{ route('tiktok.confirm-shop') }}" method="POST">
                @csrf

                <div class="space-y-4 mb-8">
                    @foreach($shops as $index => $shop)
                        <label class="block cursor-pointer">
                            <input type="radio" name="shop_index" value="{{ $index }}" class="sr-only peer" {{ $index === 0 ? 'checked' : '' }}>
                            <div class="p-4 border-2 border-gray-200 dark:border-zinc-700 rounded-lg peer-checked:border-pink-500 peer-checked:bg-pink-50 dark:peer-checked:bg-pink-900/20 transition-all">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-zinc-700 dark:to-zinc-600 rounded-lg flex items-center justify-center">
                                            <span class="text-lg font-bold text-gray-600 dark:text-gray-300">
                                                {{ substr($shop['shop_name'] ?? 'S', 0, 1) }}
                                            </span>
                                        </div>
                                        <div>
                                            <flux:heading size="sm">{{ $shop['shop_name'] ?: 'Unnamed Shop' }}</flux:heading>
                                            <flux:text size="sm" class="text-zinc-500">
                                                Region: {{ $shop['region'] ?? 'Unknown' }}
                                                @if(!empty($shop['shop_id']))
                                                    | ID: {{ $shop['shop_id'] }}
                                                @endif
                                            </flux:text>
                                        </div>
                                    </div>
                                    <div class="hidden peer-checked:block text-pink-500">
                                        <flux:icon name="check-circle" class="w-6 h-6" />
                                    </div>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-zinc-700">
                    <flux:button variant="ghost" :href="route('platforms.index')" wire:navigate>
                        Cancel
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        Connect Selected Shop
                    </flux:button>
                </div>
            </form>
        </div>

        {{-- Help Text --}}
        <div class="mt-6 text-center">
            <flux:text size="sm" class="text-zinc-500">
                You can connect additional shops later from the Platform Management page.
            </flux:text>
        </div>
    </div>
</x-layouts.app>
