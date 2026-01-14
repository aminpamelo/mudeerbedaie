<?php

use App\Models\LiveTimeSlot;
use Livewire\Volt\Component;

new class extends Component
{
    public $showModal = false;
    public $editingId = null;
    public $startTime = '';
    public $endTime = '';
    public $sortOrder = 0;
    public $isActive = true;

    public function getTimeSlotsProperty()
    {
        return LiveTimeSlot::orderBy('start_time')->get();
    }

    public function openCreateModal()
    {
        $this->reset(['editingId', 'startTime', 'endTime', 'isActive']);
        $this->isActive = true;
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $slot = LiveTimeSlot::findOrFail($id);
        $this->editingId = $slot->id;
        $this->startTime = \Carbon\Carbon::parse($slot->start_time)->format('H:i');
        $this->endTime = \Carbon\Carbon::parse($slot->end_time)->format('H:i');
        $this->isActive = $slot->is_active;
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'startTime' => 'required',
            'endTime' => 'required',
        ]);

        $data = [
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'is_active' => $this->isActive,
        ];

        if ($this->editingId) {
            LiveTimeSlot::find($this->editingId)->update($data);
            session()->flash('success', 'Time slot updated successfully.');
        } else {
            LiveTimeSlot::create($data);
            session()->flash('success', 'Time slot created successfully.');
        }

        $this->closeModal();
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
        $this->reset(['editingId', 'startTime', 'endTime', 'isActive']);
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
                ],
                [
                    'is_active' => true,
                    'sort_order' => $slot['sort_order'],
                ]
            );
        }

        session()->flash('success', 'Default session slots have been created.');
    }
}
?>

<div>
    <x-slot:title>Session Slots</x-slot:title>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Session Slots</flux:heading>
            <flux:text class="mt-2">Configure available session slots for live streaming schedule</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button variant="outline" href="{{ route('admin.live-schedule-calendar') }}">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
                    Back to Schedule Live Host
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
                    Add Session Slot
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

    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-12">
                        #
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Start Time
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        End Time
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Duration
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Display
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Status
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($this->timeSlots as $index => $slot)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                {{ $index + 1 }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ \Carbon\Carbon::parse($slot->start_time)->format('g:i A') }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ \Carbon\Carbon::parse($slot->end_time)->format('g:i A') }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $slot->duration_minutes ?? '-' }} minutes
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $slot->time_range }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button wire:click="toggleActive({{ $slot->id }})">
                                <flux:badge :color="$slot->is_active ? 'green' : 'gray'">
                                    {{ $slot->is_active ? 'Active' : 'Inactive' }}
                                </flux:badge>
                            </button>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
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
                        <td colspan="7" class="px-6 py-12 text-center">
                            <flux:icon name="clock" class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Session Slots</h3>
                            <p class="text-gray-500 dark:text-gray-400 mb-4">
                                Create session slots to define available streaming hours.
                            </p>
                            <div class="flex items-center justify-center gap-3">
                                <flux:button variant="outline" wire:click="seedDefaultSlots">
                                    Seed Default Slots
                                </flux:button>
                                <flux:button variant="primary" wire:click="openCreateModal">
                                    Add Session Slot
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Create/Edit Modal -->
    <flux:modal wire:model="showModal" class="max-w-md">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">
                {{ $editingId ? 'Edit Session Slot' : 'Create Session Slot' }}
            </flux:heading>

            <div class="space-y-4">
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

                <flux:field>
                    <flux:checkbox wire:model="isActive" label="Active" />
                </flux:field>

                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Session slots are automatically sorted by start time.
                </p>
            </div>

            <div class="mt-6 flex gap-3">
                <flux:button variant="primary" wire:click="save" class="flex-1">
                    {{ $editingId ? 'Update' : 'Create' }}
                </flux:button>
                <flux:button variant="ghost" wire:click="closeModal">
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
