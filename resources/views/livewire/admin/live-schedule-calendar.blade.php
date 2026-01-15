<?php

use App\Models\LiveSchedule;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;
use App\Notifications\ScheduleAssignmentNotification;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Url(as: 'platform', keep: true)]
    public $selectedPlatformId = '';

    public $editingScheduleId = null;
    public $editingPlatformId = null;
    public $editingDayOfWeek = null;
    public $editingTimeSlotId = null;
    public $selectedHostId = null;
    public $remarks = '';

    public $showAssignmentModal = false;
    public $conflicts = [];
    public $showConflictWarning = false;

    // Import properties
    public $showImportModal = false;
    public $importFile = null;
    public $importPreview = [];
    public $importErrors = [];
    public $importSuccess = false;

    // Days of week in Malay (starting from Saturday as per the image)
    public array $daysOfWeek = [
        6 => 'SABTU',
        0 => 'AHAD',
        1 => 'ISNIN',
        2 => 'SELASA',
        3 => 'RABU',
        4 => 'KHAMIS',
        5 => 'JUMAAT',
    ];

    public function getTimeSlotsProperty()
    {
        return LiveTimeSlot::query()
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();
    }

    public function getPlatformAccountsProperty()
    {
        $query = PlatformAccount::query()
            ->with(['platform'])
            ->active();

        if ($this->selectedPlatformId) {
            $query->where('id', $this->selectedPlatformId);
        }

        return $query->orderBy('name')->get();
    }

    public function getLiveHostsProperty()
    {
        return User::query()
            ->where('role', 'live_host')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function getSchedulesMapProperty()
    {
        $platformIds = $this->platformAccounts->pluck('id');

        $schedules = LiveSchedule::query()
            ->with(['liveHost'])
            ->whereIn('platform_account_id', $platformIds)
            ->where('is_active', true)
            ->get();

        // Create a map: platform_id-day-start_time => schedule
        $map = [];
        foreach ($schedules as $schedule) {
            $key = $schedule->platform_account_id . '-' . $schedule->day_of_week . '-' . $schedule->start_time;
            $map[$key] = $schedule;
        }

        return $map;
    }

    public function getScheduleFor($platformId, $dayOfWeek, $timeSlot)
    {
        $key = $platformId . '-' . $dayOfWeek . '-' . $timeSlot->start_time;
        return $this->schedulesMap[$key] ?? null;
    }

    public function openAssignmentModal($platformId, $dayOfWeek, $timeSlotId)
    {
        $timeSlot = LiveTimeSlot::find($timeSlotId);
        if (!$timeSlot) return;

        $this->editingPlatformId = $platformId;
        $this->editingDayOfWeek = $dayOfWeek;
        $this->editingTimeSlotId = $timeSlotId;

        // Find existing schedule
        $schedule = LiveSchedule::where('platform_account_id', $platformId)
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', $timeSlot->start_time)
            ->first();

        $this->editingScheduleId = $schedule?->id;
        $this->selectedHostId = $schedule?->live_host_id;
        $this->remarks = $schedule?->remarks ?? '';

        $this->showAssignmentModal = true;
    }

    public function updatedSelectedHostId($value)
    {
        $this->checkConflicts();
    }

    public function checkConflicts()
    {
        $this->conflicts = [];
        $this->showConflictWarning = false;

        if (!$this->selectedHostId || !$this->editingTimeSlotId || $this->editingDayOfWeek === null) {
            return;
        }

        $timeSlot = LiveTimeSlot::find($this->editingTimeSlotId);
        if (!$timeSlot) return;

        // Find other schedules where this host is assigned at the same day/time but different platform
        $conflictingSchedules = LiveSchedule::where('live_host_id', $this->selectedHostId)
            ->where('day_of_week', $this->editingDayOfWeek)
            ->where('start_time', $timeSlot->start_time)
            ->where('platform_account_id', '!=', $this->editingPlatformId)
            ->where('is_active', true)
            ->with('platformAccount')
            ->get();

        if ($conflictingSchedules->isNotEmpty()) {
            $this->conflicts = $conflictingSchedules->map(function ($schedule) {
                return [
                    'platform' => $schedule->platformAccount?->name,
                    'time' => $schedule->time_range,
                    'day' => $schedule->day_name,
                ];
            })->toArray();
            $this->showConflictWarning = true;
        }
    }

    public function saveAssignment()
    {
        $timeSlot = LiveTimeSlot::find($this->editingTimeSlotId);
        if (!$timeSlot) return;

        $oldHostId = null;
        $newHostId = $this->selectedHostId ?: null;

        if ($this->editingScheduleId) {
            // Update existing schedule
            $schedule = LiveSchedule::find($this->editingScheduleId);
            if ($schedule) {
                $oldHostId = $schedule->live_host_id;
                $schedule->update([
                    'live_host_id' => $newHostId,
                    'remarks' => $this->remarks,
                ]);

                // Send notifications
                $this->sendNotifications($schedule, $oldHostId, $newHostId);
            }
        } else {
            // Create new schedule
            $schedule = LiveSchedule::create([
                'platform_account_id' => $this->editingPlatformId,
                'day_of_week' => $this->editingDayOfWeek,
                'start_time' => $timeSlot->start_time,
                'end_time' => $timeSlot->end_time,
                'is_recurring' => true,
                'is_active' => true,
                'live_host_id' => $newHostId,
                'remarks' => $this->remarks,
            ]);

            // Send notification to new host
            if ($newHostId) {
                $newHost = User::find($newHostId);
                if ($newHost) {
                    $newHost->notify(new ScheduleAssignmentNotification($schedule, 'assigned'));
                }
            }
        }

        $this->closeModal();
    }

    protected function sendNotifications($schedule, $oldHostId, $newHostId)
    {
        // Notify removed host
        if ($oldHostId && $oldHostId !== $newHostId) {
            $oldHost = User::find($oldHostId);
            if ($oldHost) {
                $oldHost->notify(new ScheduleAssignmentNotification($schedule, 'removed'));
            }
        }

        // Notify new host
        if ($newHostId && $newHostId !== $oldHostId) {
            $newHost = User::find($newHostId);
            if ($newHost) {
                $newHost->notify(new ScheduleAssignmentNotification($schedule, 'assigned'));
            }
        }

        // If same host but schedule was updated (e.g., remarks changed)
        if ($newHostId && $newHostId === $oldHostId) {
            $host = User::find($newHostId);
            if ($host) {
                $host->notify(new ScheduleAssignmentNotification($schedule, 'updated'));
            }
        }
    }

    public function clearAssignment()
    {
        if ($this->editingScheduleId) {
            $schedule = LiveSchedule::find($this->editingScheduleId);
            if ($schedule) {
                $oldHostId = $schedule->live_host_id;

                $schedule->update([
                    'live_host_id' => null,
                    'remarks' => null,
                ]);

                // Notify removed host
                if ($oldHostId) {
                    $oldHost = User::find($oldHostId);
                    if ($oldHost) {
                        $oldHost->notify(new ScheduleAssignmentNotification($schedule, 'removed'));
                    }
                }
            }
        }

        $this->closeModal();
    }

    public function closeModal()
    {
        $this->showAssignmentModal = false;
        $this->editingScheduleId = null;
        $this->editingPlatformId = null;
        $this->editingDayOfWeek = null;
        $this->editingTimeSlotId = null;
        $this->selectedHostId = null;
        $this->remarks = '';
        $this->conflicts = [];
        $this->showConflictWarning = false;
    }

    public function exportToExcel()
    {
        $platforms = $this->platformAccounts;
        $timeSlots = $this->timeSlots;

        $rows = [];

        // Header row
        $headerRow = ['Platform', 'Day', 'Time Slot', 'Host Name', 'Host Email', 'Remarks', 'Status'];
        $rows[] = $headerRow;

        foreach ($platforms as $platform) {
            foreach ($this->daysOfWeek as $dayIndex => $dayName) {
                foreach ($timeSlots as $timeSlot) {
                    $schedule = $this->getScheduleFor($platform->id, $dayIndex, $timeSlot);
                    $host = $schedule?->liveHost;

                    $rows[] = [
                        $platform->name,
                        $dayName,
                        $timeSlot->time_range,
                        $host?->name ?? '',
                        $host?->email ?? '',
                        $schedule?->remarks ?? '',
                        $host ? 'Assigned' : 'Unassigned',
                    ];
                }
            }
        }

        // Generate CSV content
        $filename = 'jadual-hostlive-' . now()->format('Y-m-d') . '.csv';
        $handle = fopen('php://temp', 'r+');

        // Add BOM for UTF-8 Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function updatedImportFile()
    {
        $this->importErrors = [];
        $this->importPreview = [];
        $this->importSuccess = false;

        if (!$this->importFile) return;

        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        try {
            $path = $this->importFile->getRealPath();
            $rows = array_map('str_getcsv', file($path));

            // Remove BOM if present
            if (!empty($rows[0][0])) {
                $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]);
            }

            // Get header row
            $header = array_shift($rows);

            // Map day names to indexes
            $dayMap = [
                'SABTU' => 6, 'AHAD' => 0, 'ISNIN' => 1, 'SELASA' => 2,
                'RABU' => 3, 'KHAMIS' => 4, 'JUMAAT' => 5,
                'Saturday' => 6, 'Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2,
                'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5,
            ];

            $preview = [];
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNum = $index + 2; // Account for header

                if (count($row) < 4) {
                    $errors[] = "Row {$rowNum}: Insufficient columns";
                    continue;
                }

                $platformName = trim($row[0] ?? '');
                $dayName = strtoupper(trim($row[1] ?? ''));
                $timeRange = trim($row[2] ?? '');
                $hostName = trim($row[3] ?? '');
                $hostEmail = trim($row[4] ?? '');
                $remarks = trim($row[5] ?? '');

                // Find platform
                $platform = PlatformAccount::where('name', 'like', "%{$platformName}%")->first();
                if (!$platform && $platformName) {
                    $errors[] = "Row {$rowNum}: Platform '{$platformName}' not found";
                    continue;
                }

                // Parse day
                $dayOfWeek = $dayMap[$dayName] ?? null;
                if ($dayOfWeek === null && $dayName) {
                    $errors[] = "Row {$rowNum}: Invalid day '{$dayName}'";
                    continue;
                }

                // Parse time - try to find matching time slot
                $timeSlot = null;
                if ($timeRange) {
                    $timeSlot = LiveTimeSlot::all()->first(function ($slot) use ($timeRange) {
                        return str_contains(strtolower($slot->time_range), strtolower(str_replace(' ', '', $timeRange)));
                    });
                }

                // Find host by email or name
                $host = null;
                if ($hostEmail) {
                    $host = User::where('email', $hostEmail)->where('role', 'live_host')->first();
                }
                if (!$host && $hostName) {
                    $host = User::where('name', 'like', "%{$hostName}%")->where('role', 'live_host')->first();
                }

                if ($platform && $dayOfWeek !== null && $timeSlot) {
                    $preview[] = [
                        'platform_id' => $platform->id,
                        'platform_name' => $platform->name,
                        'day_of_week' => $dayOfWeek,
                        'day_name' => $dayName,
                        'time_slot_id' => $timeSlot->id,
                        'time_range' => $timeSlot->time_range,
                        'start_time' => $timeSlot->start_time,
                        'end_time' => $timeSlot->end_time,
                        'host_id' => $host?->id,
                        'host_name' => $host?->name ?? $hostName,
                        'remarks' => $remarks,
                        'valid' => true,
                    ];
                }
            }

            $this->importPreview = $preview;
            $this->importErrors = $errors;

        } catch (\Exception $e) {
            $this->importErrors = ['Error reading file: ' . $e->getMessage()];
        }
    }

    public function processImport()
    {
        $imported = 0;
        $skipped = 0;

        foreach ($this->importPreview as $row) {
            if (!$row['valid']) {
                $skipped++;
                continue;
            }

            // Find or create schedule
            $schedule = LiveSchedule::updateOrCreate(
                [
                    'platform_account_id' => $row['platform_id'],
                    'day_of_week' => $row['day_of_week'],
                    'start_time' => $row['start_time'],
                ],
                [
                    'end_time' => $row['end_time'],
                    'is_recurring' => true,
                    'is_active' => true,
                    'live_host_id' => $row['host_id'],
                    'remarks' => $row['remarks'] ?: null,
                ]
            );

            // Send notification if host assigned
            if ($row['host_id']) {
                $host = User::find($row['host_id']);
                if ($host) {
                    $host->notify(new ScheduleAssignmentNotification($schedule, 'assigned'));
                }
            }

            $imported++;
        }

        $this->importSuccess = true;
        session()->flash('success', "Imported {$imported} schedules successfully. {$skipped} skipped.");
        $this->closeImportModal();
    }

    public function closeImportModal()
    {
        $this->showImportModal = false;
        $this->importFile = null;
        $this->importPreview = [];
        $this->importErrors = [];
        $this->importSuccess = false;
    }

    public function downloadTemplate()
    {
        $rows = [
            ['Platform', 'Day', 'Time Slot', 'Host Name', 'Host Email', 'Remarks'],
            ['AMARMIRZABEDAIE', 'SABTU', '6:30am - 8:30am', 'John Doe', 'john@example.com', 'Morning shift'],
            ['BEDAIEHQ', 'AHAD', '8:30am - 10:30am', 'Jane Smith', 'jane@example.com', ''],
        ];

        $filename = 'jadual-hostlive-template.csv';
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
?>

<div>
    <x-slot:title>Schedule Live Host</x-slot:title>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Schedule Live Host</flux:heading>
            <flux:text class="mt-2">Weekly schedule template for live streaming hosts</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button variant="outline" wire:click="exportToExcel">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-2" />
                    Export
                </div>
            </flux:button>
            <flux:button variant="outline" wire:click="$set('showImportModal', true)">
                <div class="flex items-center justify-center">
                    <flux:icon name="arrow-up-tray" class="w-4 h-4 mr-2" />
                    Import
                </div>
            </flux:button>
            <flux:button variant="outline" href="{{ route('admin.live-schedule-reports') }}">
                <div class="flex items-center justify-center">
                    <flux:icon name="chart-bar" class="w-4 h-4 mr-2" />
                    Reports
                </div>
            </flux:button>
            <flux:button variant="outline" href="{{ route('admin.live-time-slots') }}">
                <div class="flex items-center justify-center">
                    <flux:icon name="clock" class="w-4 h-4 mr-2" />
                    Time Slots
                </div>
            </flux:button>
            <flux:button variant="outline" href="{{ route('admin.live-schedules.index') }}">
                <div class="flex items-center justify-center">
                    <flux:icon name="calendar" class="w-4 h-4 mr-2" />
                    Legacy View
                </div>
            </flux:button>
            <flux:button variant="primary" href="{{ route('admin.live-hosts') }}">
                <div class="flex items-center justify-center">
                    <flux:icon name="users" class="w-4 h-4 mr-2" />
                    Manage Hosts
                </div>
            </flux:button>
        </div>
    </div>

    <!-- Platform Filter -->
    <div class="mb-6 flex gap-4 items-center">
        <flux:select wire:model.live="selectedPlatformId" class="w-64">
            <option value="">All Platform Accounts</option>
            @foreach(PlatformAccount::active()->with('platform')->orderBy('name')->get() as $account)
                <option value="{{ $account->id }}">{{ $account->name }}</option>
            @endforeach
        </flux:select>

        <!-- Legend -->
        <div class="flex-1 flex items-center justify-end gap-4">
            <div class="text-sm text-gray-500 dark:text-gray-400">Host Colors:</div>
            @foreach($this->liveHosts->take(6) as $host)
                <div class="flex items-center gap-1">
                    <span
                        class="inline-block w-4 h-4 rounded"
                        style="background-color: {{ $host->host_color }};"
                    ></span>
                    <span class="text-xs text-gray-600 dark:text-gray-300">{{ Str::before($host->name, ' ') }}</span>
                </div>
            @endforeach
        </div>
    </div>

    @if($this->timeSlots->isEmpty())
        <!-- No Time Slots Warning -->
        <div class="text-center py-12 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
            <flux:icon name="exclamation-triangle" class="w-12 h-12 text-yellow-500 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Time Slots Configured</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-4">Please configure time slots first to set up the schedule grid.</p>
            <flux:button variant="primary" href="{{ route('admin.live-time-slots') }}">
                Configure Time Slots
            </flux:button>
        </div>
    @elseif($this->platformAccounts->isEmpty())
        <!-- No Platform Accounts -->
        <div class="text-center py-12 bg-white dark:bg-zinc-800 rounded-lg border border-gray-200 dark:border-zinc-700">
            <flux:icon name="calendar-days" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Platform Accounts</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-4">Create platform accounts first to set up the schedule.</p>
            <flux:button variant="primary" href="{{ route('admin.platforms.index') }}">
                Manage Platforms
            </flux:button>
        </div>
    @else
        <!-- Schedule Grid -->
        <div class="overflow-x-auto">
            <div class="flex gap-4 min-w-max pb-4">
                @foreach($this->platformAccounts as $platformAccount)
                    @php
                        $headerColors = [
                            0 => 'bg-green-500',
                            1 => 'bg-orange-500',
                            2 => 'bg-blue-500',
                            3 => 'bg-purple-500',
                            4 => 'bg-pink-500',
                        ];
                        $headerColor = $headerColors[$loop->index % 5];
                    @endphp

                    <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-lg overflow-hidden min-w-[420px] border border-gray-200 dark:border-zinc-700">
                        <!-- Platform Header -->
                        <div class="{{ $headerColor }} px-4 py-3">
                            <h2 class="text-white font-bold text-center text-lg uppercase tracking-wide">
                                {{ $platformAccount->name }}
                            </h2>
                        </div>

                        <!-- Column Headers -->
                        <div class="grid grid-cols-4 bg-gray-100 dark:bg-zinc-700 border-b border-gray-200 dark:border-zinc-600">
                            <div class="px-3 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase text-center">
                                Hari
                            </div>
                            <div class="px-3 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase text-center">
                                Nama Asatizah
                            </div>
                            <div class="px-3 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase text-center">
                                Masa
                            </div>
                            <div class="px-3 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase text-center">
                                Admin Remark
                            </div>
                        </div>

                        <!-- Schedule Rows -->
                        <div class="divide-y divide-gray-100 dark:divide-zinc-700 max-h-[700px] overflow-y-auto">
                            @foreach($this->daysOfWeek as $dayIndex => $dayName)
                                @foreach($this->timeSlots as $slotIndex => $timeSlot)
                                    @php
                                        $schedule = $this->getScheduleFor($platformAccount->id, $dayIndex, $timeSlot);
                                        $host = $schedule?->liveHost;
                                        $isFirstSlotOfDay = $slotIndex === 0;
                                    @endphp

                                    <div
                                        class="grid grid-cols-4 hover:bg-gray-50 dark:hover:bg-zinc-700/50 transition-colors cursor-pointer"
                                        wire:click="openAssignmentModal({{ $platformAccount->id }}, {{ $dayIndex }}, {{ $timeSlot->id }})"
                                    >
                                        <!-- Day Column -->
                                        <div class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center justify-center border-r border-gray-100 dark:border-zinc-700">
                                            @if($isFirstSlotOfDay)
                                                {{ $dayName }}
                                            @endif
                                        </div>

                                        <!-- Host Name Column -->
                                        <div class="px-2 py-1.5 flex items-center justify-center border-r border-gray-100 dark:border-zinc-700">
                                            @if($host)
                                                <span
                                                    class="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium truncate max-w-full"
                                                    style="background-color: {{ $host->host_color }}; color: {{ $host->host_text_color }};"
                                                >
                                                    {{ $host->name }}
                                                </span>
                                            @else
                                                <span class="text-gray-300 dark:text-zinc-500 text-xs">-</span>
                                            @endif
                                        </div>

                                        <!-- Time Column -->
                                        <div class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400 flex items-center justify-center border-r border-gray-100 dark:border-zinc-700">
                                            {{ $timeSlot->time_range }}
                                        </div>

                                        <!-- Admin Remarks Column -->
                                        <div class="px-2 py-2 text-xs text-gray-500 dark:text-gray-400 flex items-center">
                                            @if($schedule?->remarks)
                                                <div class="flex items-center gap-1" title="{{ $schedule->remarks }}">
                                                    <flux:icon name="chat-bubble-left-ellipsis" class="w-3 h-3 text-blue-500 flex-shrink-0" />
                                                    <span class="truncate">{{ Str::limit($schedule->remarks, 12) }}</span>
                                                </div>
                                            @else
                                                <flux:icon name="chevron-down" class="w-3 h-3 text-gray-300 mx-auto" />
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Assignment Modal -->
    <flux:modal wire:model="showAssignmentModal" class="max-w-md">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Assign Host</flux:heading>

            @if($editingTimeSlotId && $editingDayOfWeek !== null)
                @php
                    $timeSlot = \App\Models\LiveTimeSlot::find($editingTimeSlotId);
                    $platform = \App\Models\PlatformAccount::find($editingPlatformId);
                    $dayName = $daysOfWeek[$editingDayOfWeek] ?? '';
                @endphp

                <div class="mb-4 p-3 bg-gray-50 dark:bg-zinc-700 rounded-lg">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <strong>Platform:</strong> {{ $platform?->name }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <strong>Day:</strong> {{ $dayName }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <strong>Time:</strong> {{ $timeSlot?->time_range }}
                    </div>
                </div>
            @endif

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Select Host</flux:label>
                    <flux:select wire:model.live="selectedHostId">
                        <option value="">-- No Host --</option>
                        @foreach($this->liveHosts as $host)
                            <option value="{{ $host->id }}">{{ $host->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>

                {{-- Conflict Warning --}}
                @if($showConflictWarning && count($conflicts) > 0)
                    <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                        <div class="flex items-start gap-2">
                            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" />
                            <div>
                                <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Conflict Detected!</p>
                                <p class="text-xs text-yellow-700 dark:text-yellow-300 mt-1">This host is already assigned to:</p>
                                <ul class="text-xs text-yellow-600 dark:text-yellow-400 mt-1 list-disc list-inside">
                                    @foreach($conflicts as $conflict)
                                        <li>{{ $conflict['platform'] }} on {{ $conflict['day'] }} at {{ $conflict['time'] }}</li>
                                    @endforeach
                                </ul>
                                <p class="text-xs text-yellow-600 dark:text-yellow-400 mt-2 italic">You can still save, but the host will be double-booked.</p>
                            </div>
                        </div>
                    </div>
                @endif

                <flux:field>
                    <flux:label>Remarks (Admin)</flux:label>
                    <flux:textarea wire:model="remarks" rows="2" placeholder="Optional notes..." />
                </flux:field>
            </div>

            <div class="mt-6 flex gap-3">
                <flux:button variant="primary" wire:click="saveAssignment" class="flex-1">
                    Save Assignment
                </flux:button>
                @if($editingScheduleId)
                    <flux:button variant="ghost" wire:click="clearAssignment">
                        Clear
                    </flux:button>
                @endif
                <flux:button variant="ghost" wire:click="closeModal">
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Import Modal -->
    <flux:modal wire:model="showImportModal" class="max-w-2xl">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Import Schedule</flux:heading>

            <div class="space-y-4">
                <!-- Download Template -->
                <div class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <flux:icon name="document-text" class="w-5 h-5 text-blue-600" />
                            <span class="text-sm text-blue-700 dark:text-blue-300">Download the CSV template for correct format</span>
                        </div>
                        <flux:button variant="outline" size="sm" wire:click="downloadTemplate">
                            <div class="flex items-center justify-center">
                                <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-1" />
                                Template
                            </div>
                        </flux:button>
                    </div>
                </div>

                <!-- File Upload -->
                <flux:field>
                    <flux:label>Upload CSV File</flux:label>
                    <input
                        type="file"
                        wire:model="importFile"
                        accept=".csv,.txt"
                        class="block w-full text-sm text-gray-500 dark:text-gray-400
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-lg file:border-0
                            file:text-sm file:font-semibold
                            file:bg-blue-50 file:text-blue-700
                            dark:file:bg-blue-900/20 dark:file:text-blue-300
                            hover:file:bg-blue-100 dark:hover:file:bg-blue-900/40
                            cursor-pointer"
                    />
                    @error('importFile') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>

                <!-- Loading indicator -->
                <div wire:loading wire:target="importFile" class="text-sm text-gray-500">
                    Processing file...
                </div>

                <!-- Import Errors -->
                @if(count($importErrors) > 0)
                    <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg max-h-40 overflow-y-auto">
                        <div class="flex items-start gap-2">
                            <flux:icon name="exclamation-circle" class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
                            <div>
                                <p class="text-sm font-medium text-red-800 dark:text-red-200">Import Errors</p>
                                <ul class="text-xs text-red-600 dark:text-red-400 mt-1 list-disc list-inside">
                                    @foreach($importErrors as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Import Preview -->
                @if(count($importPreview) > 0)
                    <div class="border border-gray-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                        <div class="bg-gray-50 dark:bg-zinc-700 px-4 py-2 border-b border-gray-200 dark:border-zinc-600">
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Preview ({{ count($importPreview) }} rows ready to import)
                            </h4>
                        </div>
                        <div class="max-h-60 overflow-y-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-gray-100 dark:bg-zinc-800">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-gray-600 dark:text-gray-400">Platform</th>
                                        <th class="px-3 py-2 text-left text-gray-600 dark:text-gray-400">Day</th>
                                        <th class="px-3 py-2 text-left text-gray-600 dark:text-gray-400">Time</th>
                                        <th class="px-3 py-2 text-left text-gray-600 dark:text-gray-400">Host</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach($importPreview as $row)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $row['platform_name'] }}</td>
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $row['day_name'] }}</td>
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $row['time_range'] }}</td>
                                            <td class="px-3 py-2">
                                                @if($row['host_id'])
                                                    <span class="text-green-600 dark:text-green-400">{{ $row['host_name'] }}</span>
                                                @else
                                                    <span class="text-gray-400">{{ $row['host_name'] ?: 'Unassigned' }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-6 flex gap-3">
                @if(count($importPreview) > 0)
                    <flux:button variant="primary" wire:click="processImport" class="flex-1">
                        <div class="flex items-center justify-center">
                            <flux:icon name="arrow-up-tray" class="w-4 h-4 mr-2" />
                            Import {{ count($importPreview) }} Schedules
                        </div>
                    </flux:button>
                @endif
                <flux:button variant="ghost" wire:click="closeImportModal">
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
