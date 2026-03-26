<?php

use Livewire\Volt\Component;

new class extends Component {
}; ?>

<div x-data="{ fullscreen: false }"
     x-on:keydown.escape.window="fullscreen = false"
     :class="fullscreen ? 'fixed inset-0 z-50 bg-white dark:bg-zinc-900 p-4 overflow-hidden' : ''"
>
    <div class="mb-4 flex items-center justify-between">
        <div>
            <flux:heading size="xl">WhatsApp Inbox</flux:heading>
            <flux:text class="mt-1" x-show="!fullscreen">Urus perbualan WhatsApp dengan pelajar dan ibu bapa</flux:text>
        </div>
        <flux:button variant="ghost" size="sm" x-on:click="fullscreen = !fullscreen">
            <div class="flex items-center gap-1.5">
                <template x-if="!fullscreen">
                    <flux:icon name="arrows-pointing-out" class="w-4 h-4" />
                </template>
                <template x-if="fullscreen">
                    <flux:icon name="arrows-pointing-in" class="w-4 h-4" />
                </template>
                <span class="text-xs" x-text="fullscreen ? 'Exit Fullscreen' : 'Fullscreen'"></span>
            </div>
        </flux:button>
    </div>

    <div id="whatsapp-inbox-app"
         data-csrf-token="{{ csrf_token() }}"
         data-api-base="{{ url('/api/admin/whatsapp') }}"
         :class="fullscreen ? 'h-[calc(100vh-80px)]' : 'h-[calc(100vh-200px)]'"
         :data-fullscreen="fullscreen">
        <div class="flex items-center justify-center h-64">
            <div class="w-6 h-6 border-2 border-teal-200 border-t-teal-600 rounded-full animate-spin"></div>
        </div>
    </div>

    @viteReactRefresh
    @vite('resources/js/whatsapp-inbox/index.jsx')
</div>
