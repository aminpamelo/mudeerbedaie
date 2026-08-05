<?php

use App\Models\Course;
use App\Models\ProductCart;
use Livewire\Volt\Component;

new class extends Component
{
    public Course $course;

    public bool $compact = false;

    public function mount(Course $course, bool $compact = false): void
    {
        $this->course = $course;
        $this->compact = $compact;
    }

    public function add(): void
    {
        $this->resolveCart()->addCourse($this->course);

        $this->dispatch('cart-updated');
        $this->dispatch('cart-notify', message: __('store.added_to_cart', ['name' => $this->course->name]));
    }

    private function resolveCart(): ProductCart
    {
        $attributes = auth()->check()
            ? ['user_id' => auth()->id()]
            : ['session_id' => session()->getId()];

        return ProductCart::firstOrCreate($attributes, [
            'currency' => 'MYR',
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'discount_amount' => 0,
        ]);
    }
}; ?>

<div>
    <button type="button" wire:click="add" wire:loading.attr="disabled" wire:target="add"
            @class([
                'store-grad store-grad-hover flex items-center justify-center gap-2 rounded-xl font-semibold text-white disabled:opacity-70',
                'w-full px-4 py-2.5 text-sm' => $compact,
                'w-full px-6 py-3 text-sm sm:w-auto' => ! $compact,
            ])>
        <flux:icon name="academic-cap" class="h-4 w-4" wire:loading.remove wire:target="add" />
        <flux:icon name="arrow-path" class="h-4 w-4 animate-spin" wire:loading wire:target="add" />
        <span wire:loading.remove wire:target="add">{{ __('store.course_enrol') }}</span>
        <span wire:loading wire:target="add">{{ __('store.adding') }}</span>
    </button>
</div>
