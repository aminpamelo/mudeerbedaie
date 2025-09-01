<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component {
    //
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Enrollments</flux:heading>
            <flux:text class="mt-2">Manage student enrollments in your courses</flux:text>
        </div>
    </div>

    <flux:card class="text-center py-12">
        <flux:icon icon="clipboard" class="w-16 h-16 text-purple-500 mx-auto mb-4" />
        <flux:heading size="lg" class="mb-4">Enrollment Management</flux:heading>
        <flux:text class="text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
            Track and manage student enrollments, view enrollment status, and handle enrollment changes.
        </flux:text>
        <div class="flex items-center justify-center space-x-4">
            <flux:badge color="blue">Coming Soon</flux:badge>
        </div>
    </flux:card>
</div>