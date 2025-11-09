<?php

use App\Models\LiveSchedule;
use App\Models\PlatformAccount;
use Livewire\Volt\Component;

new class extends Component {
    public $platform_account_id = '';
    public $day_of_week = '';
    public $start_time = '';
    public $end_time = '';
    public $is_recurring = true;
    public $is_active = true;

    public function save()
    {
        $validated = $this->validate([
            'platform_account_id' => 'required|exists:platform_accounts,id',
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
            'is_recurring' => 'boolean',
            'is_active' => 'boolean',
        ]);

        LiveSchedule::create($validated);

        session()->flash('success', 'Schedule created successfully.');

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
    <x-slot:title>Create Live Schedule</x-slot:title>

    <div class="mb-6">
        <flux:heading size="xl">Create Live Schedule</flux:heading>
        <flux:text class="mt-2">Add a new streaming schedule for a live host</flux:text>
    </div>

    <form wire:submit="save" class="max-w-2xl space-y-6">
        <flux:field>
            <flux:label>Platform Account</flux:label>
            <flux:select wire:model="platform_account_id" required>
                <option value="">Select a platform account</option>
                @foreach($this->platformAccounts as $account)
                    <option value="{{ $account->id }}">
                        {{ $account->platform->display_name }} - {{ $account->name }} ({{ $account->user->name }})
                    </option>
                @endforeach
            </flux:select>
            <flux:error name="platform_account_id" />
            @if($this->platformAccounts->isEmpty())
                <flux:description>
                    No platform accounts found. Please create a platform account for a live host first.
                </flux:description>
            @endif
        </flux:field>

        <flux:field>
            <flux:label>Day of Week</flux:label>
            <flux:select wire:model="day_of_week" required>
                <option value="">Select a day</option>
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
            <flux:button type="submit" variant="primary">Create Schedule</flux:button>
            <flux:button href="{{ route('admin.live-schedules.index') }}" variant="ghost">Cancel</flux:button>
        </div>
    </form>
</div>
