<?php

use Livewire\Volt\Component;

new class extends Component {
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">WhatsApp Inbox</flux:heading>
            <flux:text class="mt-2">Urus perbualan WhatsApp dengan pelajar dan ibu bapa</flux:text>
        </div>
    </div>

    <div id="whatsapp-inbox-app"
         data-csrf-token="{{ csrf_token() }}"
         data-api-base="{{ url('/api/admin/whatsapp') }}"
         class="min-h-[calc(100vh-200px)]">
        <div class="flex items-center justify-center h-64">
            <flux:icon name="arrow-path" class="w-6 h-6 animate-spin text-zinc-400" />
        </div>
    </div>

    @viteReactRefresh
    @vite('resources/js/whatsapp-inbox/index.jsx')
</div>
