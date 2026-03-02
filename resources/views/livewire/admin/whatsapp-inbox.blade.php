<?php

use Livewire\Volt\Component;

new class extends Component {
}; ?>

<div>
    <div class="mb-4">
        <flux:heading size="xl">WhatsApp Inbox</flux:heading>
        <flux:text class="mt-1">Urus perbualan WhatsApp dengan pelajar dan ibu bapa</flux:text>
    </div>

    <div id="whatsapp-inbox-app"
         data-csrf-token="{{ csrf_token() }}"
         data-api-base="{{ url('/api/admin/whatsapp') }}"
         class="min-h-[calc(100vh-180px)]">
        <div class="flex items-center justify-center h-64">
            <div class="w-6 h-6 border-2 border-teal-200 border-t-teal-600 rounded-full animate-spin"></div>
        </div>
    </div>

    @viteReactRefresh
    @vite('resources/js/whatsapp-inbox/index.jsx')
</div>
