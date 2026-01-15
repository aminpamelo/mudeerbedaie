<?php

use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $showModal = false;
    public $editingId = null;
    public $platformAccountId = '';
    public $dayOfWeek = '';
    public $startTime = '';
    public $endTime = '';
    public $sortOrder = 0;
    public $isActive = true;

    // Filters
    public $filterPlatform = '';
    public $filterDay = '';
    public $filterStatus = '';

    public array $daysOfWeek = [
        '' => 'All Days (Global)',
        '6' => 'Saturday (Sabtu)',
        '0' => 'Sunday (Ahad)',
        '1' => 'Monday (Isnin)',
        '2' => 'Tuesday (Selasa)',
        '3' => 'Wednesday (Rabu)',
        '4' => 'Thursday (Khamis)',
        '5' => 'Friday (Jumaat)',
    ];

    public function getTimeSlotsProperty()
    {
        return LiveTimeSlot::query()
            ->with(['platformAccount.platform', 'createdBy'])
            ->when($this->filterPlatform, fn($q) => $q->where('platform_account_id', $this->filterPlatform))
            ->when($this->filterDay !== '', fn($q) => $this->filterDay === 'global'
                ? $q->whereNull('day_of_week')
                : $q->where('day_of_week', $this->filterDay))
            ->when($this->filterStatus, fn($q) => $q->where('is_active', $this->filterStatus === 'active'))
            ->orderBy('platform_account_id')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->paginate(20);
    }

    public function getPlatformAccountsProperty()
    {
        return PlatformAccount::query()
            ->with('platform')
            ->active()
            ->orderBy('name')
            ->get();
    }

    public function openCreateModal()
    {
        $this->reset(['editingId', 'platformAccountId', 'dayOfWeek', 'startTime', 'endTime', 'isActive']);
        $this->isActive = true;
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $slot = LiveTimeSlot::findOrFail($id);
        $this->editingId = $slot->id;
        $this->platformAccountId = $slot->platform_account_id ?? '';
        $this->dayOfWeek = $slot->day_of_week !== null ? (string) $slot->day_of_week : '';
        $this->startTime = \Carbon\Carbon::parse($slot->start_time)->format('H:i');
        $this->endTime = \Carbon\Carbon::parse($slot->end_time)->format('H:i');
        $this->isActive = $slot->is_active;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'startTime' => 'required',
            'endTime' => 'required|after:startTime',
        ], [
            'endTime.after' => 'End time must be after start time.',
        ]);

        // Check for overlapping slots
        $overlap = $this->checkOverlap();
        if ($overlap) {
            $this->addError('startTime', 'This time slot overlaps with an existing slot for the same platform and day.');
            return;
        }

        $data = [
            'platform_account_id' => $this->platformAccountId ?: null,
            'day_of_week' => $this->dayOfWeek !== '' ? (int) $this->dayOfWeek : null,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'is_active' => $this->isActive,
            'created_by' => $this->editingId ? null : auth()->id(),
        ];

        if ($this->editingId) {
            LiveTimeSlot::find($this->editingId)->update(array_filter($data, fn($v) => $v !== null || $v === null));
            session()->flash('success', 'Session slot updated successfully.');
        } else {
            $data['created_by'] = auth()->id();
            LiveTimeSlot::create($data);
            session()->flash('success', 'Session slot created successfully.');
        }

        $this->closeModal();
    }

    protected function checkOverlap(): bool
    {
        $query = LiveTimeSlot::query()
            ->where('start_time', '<', $this->endTime)
            ->where('end_time', '>', $this->startTime);

        // Check platform
        if ($this->platformAccountId) {
            $query->where(function ($q) {
                $q->where('platform_account_id', $this->platformAccountId)
                    ->orWhereNull('platform_account_id');
            });
        } else {
            // Global slot - check all platforms
            $query->whereNull('platform_account_id');
        }

        // Check day
        if ($this->dayOfWeek !== '') {
            $query->where(function ($q) {
                $q->where('day_of_week', $this->dayOfWeek)
                    ->orWhereNull('day_of_week');
            });
        } else {
            // All days - check global slots only
            $query->whereNull('day_of_week');
        }

        // Exclude current slot if editing
        if ($this->editingId) {
            $query->where('id', '!=', $this->editingId);
        }

        return $query->exists();
    }

    public function toggleActive($id)
    {
        $slot = LiveTimeSlot::find($id);
        if ($slot) {
            $slot->update(['is_active' => !$slot->is_active]);
        }
    }

    public function delete($id)
    {
        LiveTimeSlot::find($id)?->delete();
        session()->flash('success', 'Session slot deleted successfully.');
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['editingId', 'platformAccountId', 'dayOfWeek', 'startTime', 'endTime', 'isActive']);
        $this->resetValidation();
    }

    public function seedDefaultSlots()
    {
        $timeSlots = [
            ['start_time' => '06:30:00', 'end_time' => '08:30:00', 'sort_order' => 1],
            ['start_time' => '08:30:00', 'end_time' => '10:30:00', 'sort_order' => 2],
            ['start_time' => '10:30:00', 'end_time' => '12:30:00', 'sort_order' => 3],
            ['start_time' => '12:30:00', 'end_time' => '14:30:00', 'sort_order' => 4],
            ['start_time' => '14:30:00', 'end_time' => '16:30:00', 'sort_order' => 5],
            ['start_time' => '17:00:00', 'end_time' => '19:00:00', 'sort_order' => 6],
            ['start_time' => '20:00:00', 'end_time' => '22:00:00', 'sort_order' => 7],
            ['start_time' => '22:00:00', 'end_time' => '00:00:00', 'sort_order' => 8],
        ];

        foreach ($timeSlots as $slot) {
            LiveTimeSlot::updateOrCreate(
                [
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'platform_account_id' => null,
                    'day_of_week' => null,
                ],
                [
                    'is_active' => true,
                    'sort_order' => $slot['sort_order'],
                    'created_by' => auth()->id(),
                ]
            );
        }

        session()->flash('success', 'Default time slots have been created (global slots for all platforms and days).');
    }

    public function duplicateForPlatform($slotId, $platformId)
    {
        $slot = LiveTimeSlot::find($slotId);
        if (!$slot) return;

        LiveTimeSlot::create([
            'platform_account_id' => $platformId,
            'day_of_week' => $slot->day_of_week,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'is_active' => true,
            'sort_order' => $slot->sort_order,
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Session slot duplicated for the selected platform.');
    }

    public function clearFilters()
    {
        $this->reset(['filterPlatform', 'filterDay', 'filterStatus']);
    }
}
?>

<div>
    <x-slot:title>Time Slots</x-slot:title>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Time Slots</flux:heading>
            <flux:text class="mt-2">Configure available time slots for live streaming schedule</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button variant="outline" href="{{ route('admin.live-schedule-calendar') }}">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
                    Back to Schedule
                </div>
            </flux:button>
            @if($this->timeSlots->isEmpty())
                <flux:button variant="outline" wire:click="seedDefaultSlots">
                    <div class="flex items-center justify-center">
                        <flux:icon name="sparkles" class="w-4 h-4 mr-2" />
                        Seed Default Slots
                    </div>
                </flux:button>
            @endif
            <flux:button variant="primary" wire:click="openCreateModal">
                <div class="flex items-center justify-center">
                    <flux:icon name="plus" class="w-4 h-4 mr-2" />
                    Add Time Slot
                </div>
            </flux:button>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
            <div class="flex items-center gap-2 text-green-700 dark:text-green-400">
                <flux:icon name="check-circle" class="w-5 h-5" />
                {{ session('success') }}
            </div>
        </div>
    @endif

    <!-- Filters -->
    <div class="mb-6 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 p-4">
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <flux:field>
                    <flux:label>Platform</flux:label>
                    <flux:select wire:model.live="filterPlatform">
                        <option value="">All Platforms</option>
                        @foreach($this->platformAccounts as $platform)
                            <option value="{{ $platform->id }}">{{ $platform->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>
            <div class="flex-1 min-w-[200px]">
                <flux:field>
                    <flux:label>Day</flux:label>
                    <flux:select wire:model.live="filterDay">
                        <option value="">All Days</option>
                        <option value="global">Global (All Days)</option>
                        @foreach($daysOfWeek as $value => $label)
                            @if($value !== '')
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endif
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>
            <div class="flex-1 min-w-[150px]">
                <flux:field>
                    <flux:label>Status</flux:label>
                    <flux:select wire:model.live="filterStatus">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </flux:select>
                </flux:field>
            </div>
            <flux:button variant="ghost" wire:click="clearFilters">
                Clear Filters
            </flux:button>
        </div>
    </div>

    <!-- Info Box -->
    <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
        <div class="flex items-start gap-3">
            <flux:icon name="information-circle" class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
            <div class="text-sm text-blue-700 dark:text-blue-300">
                <p class="font-medium mb-1">How Time Slots Work:</p>
                <ul class="list-disc list-inside space-y-1 text-blue-600 dark:text-blue-400">
                    <li><strong>Global slots</strong> (no platform/day specified) apply to all platforms and days</li>
                    <li><strong>Platform-specific slots</strong> apply only to that platform</li>
                    <li><strong>Day-specific slots</strong> apply only to that day of the week</li>
                    <li>Overlapping slots for the same platform/day combination are not allowed</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700 overflow-hidden">
        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-zinc-900/50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-12">
                        #
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Platform
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Day
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Time Slot
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Duration
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Created By
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Status
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($this->timeSlots as $index => $slot)
                    <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700/50 transition-colors">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                {{ $this->timeSlots->firstItem() + $index }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($slot->platformAccount)
                                <div class="flex items-center gap-2">
                                    <flux:badge variant="outline" color="blue" size="sm">
                                        {{ $slot->platformAccount->name }}
                                    </flux:badge>
                                </div>
                            @else
                                <flux:badge variant="filled" color="purple" size="sm">
                                    Global (All Platforms)
                                </flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($slot->day_of_week !== null)
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $slot->day_name }} ({{ $slot->day_name_ms }})
                                </span>
                            @else
                                <flux:badge variant="filled" color="purple" size="sm">
                                    All Days
                                </flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ $slot->time_range }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $slot->duration_minutes ?? '-' }} min
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $slot->createdBy?->name ?? 'System' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <button wire:click="toggleActive({{ $slot->id }})">
                                <flux:badge :color="$slot->is_active ? 'green' : 'gray'">
                                    {{ $slot->is_active ? 'Active' : 'Inactive' }}
                                </flux:badge>
                            </button>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-right">
                            <div class="flex items-center justify-end gap-2">
                                <flux:button variant="ghost" size="sm" wire:click="openEditModal({{ $slot->id }})">
                                    Edit
                                </flux:button>
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    wire:click="delete({{ $slot->id }})"
                                    wire:confirm="Are you sure you want to delete this session slot?"
                                >
                                    Delete
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center">
                            <flux:icon name="clock" class="w-12 h-12 text-gray-300 dark:text-zinc-500 mx-auto mb-4" />
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Time Slots</h3>
                            <p class="text-gray-500 dark:text-gray-400 mb-4">
                                Create session slots to define available streaming hours.
                            </p>
                            <div class="flex items-center justify-center gap-3">
                                <flux:button variant="outline" wire:click="seedDefaultSlots">
                                    Seed Default Slots
                                </flux:button>
                                <flux:button variant="primary" wire:click="openCreateModal">
                                    Add Time Slot
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($this->timeSlots->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-zinc-700">
                {{ $this->timeSlots->links() }}
            </div>
        @endif
    </div>

    <!-- Create/Edit Modal -->
    <flux:modal wire:model="showModal" class="max-w-lg">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">
                {{ $editingId ? 'Edit Time Slot' : 'Create Time Slot' }}
            </flux:heading>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Platform Account</flux:label>
                    <flux:select wire:model="platformAccountId">
                        <option value="">Global (All Platforms)</option>
                        @foreach($this->platformAccounts as $platform)
                            <option value="{{ $platform->id }}">{{ $platform->name }} ({{ $platform->platform?->name }})</option>
                        @endforeach
                    </flux:select>
                    <flux:description>Leave empty to apply this slot to all platforms</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Day of Week</flux:label>
                    <flux:select wire:model="dayOfWeek">
                        @foreach($daysOfWeek as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>
                    <flux:description>Leave as "All Days" to apply this slot to every day</flux:description>
                </flux:field>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Start Time</flux:label>
                        <flux:input type="time" wire:model="startTime" />
                        @error('startTime') <flux:error>{{ $message }}</flux:error> @enderror
                    </flux:field>

                    <flux:field>
                        <flux:label>End Time</flux:label>
                        <flux:input type="time" wire:model="endTime" />
                        @error('endTime') <flux:error>{{ $message }}</flux:error> @enderror
                    </flux:field>
                </div>

                <flux:field>
                    <flux:checkbox wire:model="isActive" label="Active" />
                    <flux:description>Inactive slots won't appear in schedules</flux:description>
                </flux:field>

                <div class="p-3 bg-gray-50 dark:bg-zinc-700 rounded-lg">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        <strong>Note:</strong> The system will prevent creating overlapping time slots for the same platform and day combination.
                    </p>
                </div>
            </div>

            <div class="mt-6 flex gap-3">
                <flux:button variant="primary" wire:click="save" class="flex-1">
                    {{ $editingId ? 'Update Slot' : 'Create Slot' }}
                </flux:button>
                <flux:button variant="ghost" wire:click="closeModal">
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
