<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.teacher')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Appearance')" :subheading=" __('Update the appearance settings for your account')">
        <flux:radio.group x-data variant="segmented" x-model="lightMode" x-init="lightMode = 'light'; $flux.appearance = 'light'">
            <flux:radio value="light" icon="sun" checked>{{ __('Light') }}</flux:radio>
        </flux:radio.group>
    </x-settings.layout>
</section>
