<?php

use App\Models\LiveSchedule;
use App\Models\PlatformAccount;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public LiveSchedule $schedule;

    public $platform_account_id;
    public $day_of_week;
    public $start_time;
    public $end_time;
    public $is_recurring;
    public $is_active;

    public function mount(LiveSchedule $schedule)
    {
        $this->schedule = $schedule;
        $this->platform_account_id = $schedule->platform_account_id;
        $this->day_of_week = $schedule->day_of_week;
        $this->start_time = $schedule->start_time;
        $this->end_time = $schedule->end_time;
        $this->is_recurring = $schedule->is_recurring;
        $this->is_active = $schedule->is_active;
    }

    public function update()
    {
        $validated = $this->validate([
            'platform_account_id' => 'required|exists:platform_accounts,id',
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'is_recurring' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $this->schedule->update($validated);

        session()->flash('success', 'Schedule updated successfully.');

        return redirect()->route('admin.live-schedules.index');
    }

    public function getPlatformAccountsProperty()
    {
        return PlatformAccount::with(['platform', 'user'])
            ->whereHas('user', fn ($q) => $q->where('role', 'live_host'))
            ->get();
    }
}
?>

<div>
    <x-slot:title>Edit Live Schedule</x-slot:title>

    <div class="mb-6">
        <flux:heading size="xl">Edit Live Schedule</flux:heading>
        <flux:text class="mt-2">Update streaming schedule details</flux:text>
    </div>

    <form wire:submit="update" class="max-w-2xl space-y-6">
        <flux:field>
            <flux:label>Platform Account</flux:label>
            <flux:select wire:model="platform_account_id" required>
                @foreach($this->platformAccounts as $account)
                    <option value="{{ $account->id }}">
                        {{ $account->platform->display_name }} - {{ $account->name }} ({{ $account->user->name }})
                    </option>
                @endforeach
            </flux:select>
            <flux:error name="platform_account_id" />
        </flux:field>

        <flux:field>
            <flux:label>Day of Week</flux:label>
            <flux:select wire:model="day_of_week" required>
                <option value="0">Sunday</option>
                <option value="1">Monday</option>
                <option value="2">Tuesday</option>
                <option value="3">Wednesday</option>
                <option value="4">Thursday</option>
                <option value="5">Friday</option>
                <option value="6">Saturday</option>
            </flux:select>
            <flux:error name="day_of_week" />
        </flux:field>

        <div class="grid grid-cols-2 gap-4">
            <flux:field>
                <flux:label>Start Time</flux:label>
                <flux:input type="time" wire:model="start_time" required />
                <flux:error name="start_time" />
            </flux:field>

            <flux:field>
                <flux:label>End Time</flux:label>
                <flux:input type="time" wire:model="end_time" required />
                <flux:error name="end_time" />
            </flux:field>
        </div>

        <flux:checkbox
            wire:model="is_recurring"
            label="Recurring Schedule"
            description="This schedule will repeat weekly"
        />

        <flux:checkbox
            wire:model="is_active"
            label="Active"
            description="Schedule is active and will generate sessions"
        />

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary">Update Schedule</flux:button>
            <flux:button href="{{ route('admin.live-schedules.index') }}" variant="ghost">Cancel</flux:button>
        </div>
    </form>
</div>
