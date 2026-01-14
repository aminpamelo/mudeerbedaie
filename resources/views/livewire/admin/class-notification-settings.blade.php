<?php

use App\Models\ClassModel;
use App\Models\ClassNotificationSetting;
use App\Models\NotificationTemplate;
use App\Models\ScheduledNotification;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public ClassModel $class;

    public bool $showEditModal = false;
    public ?int $editingSettingId = null;
    public ?int $selectedTemplateId = null;
    public ?string $customSubject = '';
    public ?string $customContent = '';
    public bool $sendToStudents = true;
    public bool $sendToTeacher = true;
    public ?int $customMinutesBefore = null;

    public function mount(ClassModel $class): void
    {
        $this->class = $class;
        $this->initializeSettings();
    }

    public function initializeSettings(): void
    {
        $types = [
            'session_reminder_24h',
            'session_reminder_3h',
            'session_reminder_1h',
            'session_reminder_30m',
            'session_followup_immediate',
            'session_followup_24h',
            'enrollment_welcome',
        ];

        foreach ($types as $type) {
            $existingSetting = $this->class->notificationSettings()
                ->where('notification_type', $type)
                ->first();

            if (!$existingSetting) {
                $templateType = str_starts_with($type, 'session_reminder_')
                    ? 'session_reminder'
                    : (str_starts_with($type, 'session_followup_')
                        ? 'session_followup'
                        : $type);

                $template = NotificationTemplate::active()
                    ->where('type', $templateType)
                    ->where('language', 'ms')
                    ->first();

                $this->class->notificationSettings()->create([
                    'notification_type' => $type,
                    'is_enabled' => false,
                    'template_id' => $template?->id,
                    'send_to_students' => true,
                    'send_to_teacher' => true,
                ]);
            }
        }
    }

    public function getSettingsProperty()
    {
        return $this->class->notificationSettings()
            ->with('template')
            ->orderByRaw("CASE notification_type
                WHEN 'session_reminder_24h' THEN 1
                WHEN 'session_reminder_3h' THEN 2
                WHEN 'session_reminder_1h' THEN 3
                WHEN 'session_reminder_30m' THEN 4
                WHEN 'session_followup_immediate' THEN 5
                WHEN 'session_followup_24h' THEN 6
                WHEN 'enrollment_welcome' THEN 7
                ELSE 8
            END")
            ->get();
    }

    public function getNotificationHistoryProperty()
    {
        return $this->class->scheduledNotifications()
            ->with(['session', 'setting', 'logs'])
            ->orderByDesc('scheduled_at')
            ->paginate(20);
    }

    public function getGroupedNotificationHistoryProperty(): array
    {
        $notifications = $this->class->scheduledNotifications()
            ->with(['session', 'setting', 'logs'])
            ->orderByDesc('scheduled_session_date')
            ->orderByDesc('scheduled_session_time')
            ->orderByDesc('scheduled_at')
            ->get();

        $grouped = [];

        foreach ($notifications as $notification) {
            // Create a unique key for each session slot
            if ($notification->scheduled_session_date) {
                $slotKey = $notification->scheduled_session_date->format('Y-m-d') . '_' . $notification->scheduled_session_time;
                $slotLabel = $notification->scheduled_session_date->format('d M Y') . ' - ' . \Carbon\Carbon::parse($notification->scheduled_session_time)->format('g:i A');
                $slotDate = $notification->scheduled_session_date;
            } elseif ($notification->session) {
                $slotKey = $notification->session->session_date->format('Y-m-d') . '_' . $notification->session->session_time->format('H:i:s');
                $slotLabel = $notification->session->session_date->format('d M Y') . ' - ' . $notification->session->session_time->format('g:i A');
                $slotDate = $notification->session->session_date;
            } else {
                $slotKey = 'unknown';
                $slotLabel = 'Tidak Diketahui';
                $slotDate = null;
            }

            if (!isset($grouped[$slotKey])) {
                $grouped[$slotKey] = [
                    'label' => $slotLabel,
                    'date' => $slotDate,
                    'notifications' => [],
                ];
            }

            $grouped[$slotKey]['notifications'][] = $notification;
        }

        return $grouped;
    }

    public function getTemplatesProperty()
    {
        return NotificationTemplate::active()
            ->orderBy('type')
            ->orderBy('language')
            ->get()
            ->groupBy('type');
    }

    public function getSelectedTemplateProperty(): ?NotificationTemplate
    {
        if (!$this->selectedTemplateId) {
            return null;
        }

        return NotificationTemplate::find($this->selectedTemplateId);
    }

    public function getAvailablePlaceholdersProperty(): array
    {
        return [
            '{{student_name}}' => 'Nama pelajar',
            '{{teacher_name}}' => 'Nama guru',
            '{{class_name}}' => 'Nama kelas',
            '{{course_name}}' => 'Nama kursus',
            '{{session_date}}' => 'Tarikh sesi',
            '{{session_time}}' => 'Masa sesi',
            '{{session_datetime}}' => 'Tarikh & masa',
            '{{location}}' => 'Lokasi',
            '{{meeting_url}}' => 'URL mesyuarat',
            '{{whatsapp_link}}' => 'Pautan WhatsApp',
            '{{duration}}' => 'Tempoh',
            '{{remaining_sessions}}' => 'Sesi berbaki',
            '{{total_sessions}}' => 'Jumlah sesi',
            '{{attendance_rate}}' => 'Kadar kehadiran',
        ];
    }

    public function toggleSetting(int $settingId): void
    {
        $setting = ClassNotificationSetting::find($settingId);
        if ($setting && $setting->class_id === $this->class->id) {
            $setting->update(['is_enabled' => !$setting->is_enabled]);

            $this->dispatch('notify',
                type: 'success',
                message: $setting->is_enabled
                    ? 'Notifikasi telah diaktifkan'
                    : 'Notifikasi telah dinyahaktifkan',
            );
        }
    }

    public function editSetting(int $settingId): void
    {
        $setting = ClassNotificationSetting::find($settingId);
        if ($setting && $setting->class_id === $this->class->id) {
            $this->editingSettingId = $settingId;
            $this->selectedTemplateId = $setting->template_id;
            $this->customSubject = $setting->custom_subject;
            $this->customContent = $setting->custom_content;
            $this->sendToStudents = $setting->send_to_students;
            $this->sendToTeacher = $setting->send_to_teacher;
            $this->customMinutesBefore = $setting->custom_minutes_before;
            $this->showEditModal = true;
        }
    }

    public function saveSetting(): void
    {
        $setting = ClassNotificationSetting::find($this->editingSettingId);
        if ($setting && $setting->class_id === $this->class->id) {
            $setting->update([
                'template_id' => $this->selectedTemplateId,
                'custom_subject' => $this->customSubject ?: null,
                'custom_content' => $this->customContent ?: null,
                'send_to_students' => $this->sendToStudents,
                'send_to_teacher' => $this->sendToTeacher,
                'custom_minutes_before' => $this->customMinutesBefore,
            ]);

            $this->showEditModal = false;
            $this->resetEditForm();

            $this->dispatch('notify',
                type: 'success',
                message: 'Tetapan notifikasi telah dikemaskini',
            );
        }
    }

    public function resetEditForm(): void
    {
        $this->editingSettingId = null;
        $this->selectedTemplateId = null;
        $this->customSubject = '';
        $this->customContent = '';
        $this->sendToStudents = true;
        $this->sendToTeacher = true;
        $this->customMinutesBefore = null;
    }

    public function cancelNotification(int $notificationId): void
    {
        $notification = ScheduledNotification::find($notificationId);
        if ($notification && $notification->class_id === $this->class->id && $notification->isPending()) {
            $notification->cancel();

            $this->dispatch('notify',
                type: 'success',
                message: 'Notifikasi telah dibatalkan',
            );
        }
    }

    public function getTypeLabels(): array
    {
        return ClassNotificationSetting::getNotificationTypeLabels();
    }

    public function getUpcomingTimetableSlotsProperty(): array
    {
        $service = app(\App\Services\NotificationService::class);
        $timetable = $this->class->timetable;

        if (!$timetable || !$timetable->is_active) {
            return [];
        }

        return $service->generateUpcomingSessionSlots($timetable, 7);
    }

    public function getTimetableProperty()
    {
        return $this->class->timetable;
    }

    public function getScheduledSlotsProperty(): array
    {
        // Get all scheduled notifications for this class grouped by date/time
        $notifications = $this->class->scheduledNotifications()
            ->whereIn('status', ['pending', 'processing', 'sent'])
            ->whereNotNull('scheduled_session_date')
            ->get();

        $scheduled = [];

        foreach ($notifications as $notification) {
            $slotKey = $notification->scheduled_session_date->format('Y-m-d') . '_' . $notification->scheduled_session_time;

            if (!isset($scheduled[$slotKey])) {
                $scheduled[$slotKey] = [
                    'total' => 0,
                    'pending' => 0,
                    'sent' => 0,
                ];
            }

            $scheduled[$slotKey]['total']++;
            if ($notification->status === 'pending' || $notification->status === 'processing') {
                $scheduled[$slotKey]['pending']++;
            } elseif ($notification->status === 'sent') {
                $scheduled[$slotKey]['sent']++;
            }
        }

        return $scheduled;
    }

    public function isSlotScheduled(string $date, string $time): bool
    {
        $slotKey = $date . '_' . $time;
        return isset($this->scheduledSlots[$slotKey]) && $this->scheduledSlots[$slotKey]['total'] > 0;
    }

    public function getSlotScheduledCount(string $date, string $time): int
    {
        $slotKey = $date . '_' . $time;
        return $this->scheduledSlots[$slotKey]['pending'] ?? 0;
    }

    public function scheduleNotificationsForSlot(string $date, string $time): void
    {
        $service = app(\App\Services\NotificationService::class);
        $settings = $this->class->enabledNotificationSettings()
            ->where('notification_type', 'like', 'session_reminder_%')
            ->get();

        if ($settings->isEmpty()) {
            $this->dispatch('notify',
                type: 'info',
                message: 'Tiada tetapan notifikasi aktif. Sila aktifkan sekurang-kurangnya satu tetapan.',
            );
            return;
        }

        $sessionDate = \Carbon\Carbon::parse($date);
        $sessionDateTime = \Carbon\Carbon::parse($date . ' ' . $time);
        $totalScheduled = 0;

        foreach ($settings as $setting) {
            $scheduledAt = $sessionDateTime->copy()->subMinutes($setting->getMinutesBefore());

            if ($scheduledAt->isFuture()) {
                $exists = \App\Models\ScheduledNotification::where('class_id', $this->class->id)
                    ->where('scheduled_session_date', $sessionDate->toDateString())
                    ->where('scheduled_session_time', $time)
                    ->where('class_notification_setting_id', $setting->id)
                    ->whereIn('status', ['pending', 'processing'])
                    ->exists();

                if (!$exists) {
                    \App\Models\ScheduledNotification::create([
                        'class_id' => $this->class->id,
                        'session_id' => null,
                        'scheduled_session_date' => $sessionDate->toDateString(),
                        'scheduled_session_time' => $time,
                        'class_notification_setting_id' => $setting->id,
                        'status' => 'pending',
                        'scheduled_at' => $scheduledAt,
                        'total_recipients' => $service->getRecipients($setting)->count(),
                    ]);
                    $totalScheduled++;
                }
            }
        }

        // Clear cached computed property to force refresh on re-render
        unset($this->scheduledSlots);

        if ($totalScheduled > 0) {
            $this->dispatch('notify',
                type: 'success',
                message: $totalScheduled . ' notifikasi telah dijadualkan untuk slot ini',
            );
        } else {
            $this->dispatch('notify',
                type: 'info',
                message: 'Tiada notifikasi baharu untuk dijadualkan (mungkin sudah dijadualkan atau masa telah berlalu)',
            );
        }
    }

    public function scheduleAllUpcomingNotifications(): void
    {
        $service = app(\App\Services\NotificationService::class);

        // Check if timetable exists
        if (!$this->class->timetable || !$this->class->timetable->is_active) {
            $this->dispatch('notify',
                type: 'error',
                message: 'Kelas ini tidak mempunyai jadual waktu yang aktif.',
            );
            return;
        }

        // Check if any notification settings are enabled
        $enabledSettings = $this->class->enabledNotificationSettings()
            ->where('notification_type', 'like', 'session_reminder_%')
            ->count();

        if ($enabledSettings === 0) {
            $this->dispatch('notify',
                type: 'info',
                message: 'Tiada tetapan notifikasi aktif. Sila aktifkan sekurang-kurangnya satu tetapan peringatan.',
            );
            return;
        }

        // Schedule notifications based on timetable for the next 7 days
        $scheduled = $service->scheduleNotificationsFromTimetable($this->class, 7);

        // Clear cached computed property to force refresh on re-render
        unset($this->scheduledSlots);

        if (count($scheduled) > 0) {
            $this->dispatch('notify',
                type: 'success',
                message: count($scheduled) . ' notifikasi telah dijadualkan berdasarkan jadual waktu',
            );
        } else {
            $this->dispatch('notify',
                type: 'info',
                message: 'Tiada notifikasi baharu untuk dijadualkan (mungkin sudah dijadualkan atau tiada slot akan datang)',
            );
        }
    }

    public function sendTestNotification(int $settingId): void
    {
        $setting = ClassNotificationSetting::with('template')->find($settingId);

        if (!$setting || $setting->class_id !== $this->class->id) {
            $this->dispatch('notify',
                type: 'error',
                message: 'Tetapan tidak dijumpai',
            );
            return;
        }

        // Get the current user's email for test
        $user = auth()->user();

        // Check if template or custom content exists
        if (!$setting->template && !$setting->custom_subject && !$setting->custom_content) {
            $this->dispatch('notify',
                type: 'error',
                message: 'Sila pilih templat atau tetapkan kandungan tersuai terlebih dahulu',
            );
            return;
        }

        $subject = $setting->getEffectiveSubject();
        $content = $setting->getEffectiveContent();

        if (!$subject || !$content) {
            $this->dispatch('notify',
                type: 'error',
                message: 'Templat subjek atau kandungan tidak lengkap. Sila edit tetapan ini.',
            );
            return;
        }

        // Replace placeholders with sample data
        $placeholders = [
            '{{student_name}}' => 'Ahmad bin Ali (Contoh)',
            '{{teacher_name}}' => $this->class->teacher?->user?->name ?? 'Guru',
            '{{class_name}}' => $this->class->title,
            '{{course_name}}' => $this->class->course?->name ?? '',
            '{{session_date}}' => now()->addDay()->format('d M Y'),
            '{{session_time}}' => '8:00 PM',
            '{{session_datetime}}' => now()->addDay()->format('d M Y') . ' - 8:00 PM',
            '{{location}}' => $this->class->location ?? 'TBA',
            '{{meeting_url}}' => $this->class->meeting_url ?? 'https://meet.example.com',
            '{{whatsapp_link}}' => $this->class->whatsapp_group_link ?? '',
            '{{duration}}' => '1 jam 30 minit',
            '{{remaining_sessions}}' => '8',
            '{{total_sessions}}' => '12',
            '{{attendance_rate}}' => '95%',
        ];

        $personalizedSubject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
        $personalizedContent = str_replace(array_keys($placeholders), array_values($placeholders), $content);

        try {
            \Illuminate\Support\Facades\Mail::html(
                '<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333;">' .
                nl2br(e($personalizedContent)) .
                '<br><br><em style="color: #999;">— Ini adalah e-mel ujian —</em></div>',
                function ($message) use ($user, $personalizedSubject) {
                    $message->to($user->email, $user->name)
                        ->subject('[UJIAN] ' . $personalizedSubject);
                }
            );

            $this->dispatch('notify',
                type: 'success',
                message: 'E-mel ujian telah dihantar ke ' . $user->email,
            );
        } catch (\Exception $e) {
            $this->dispatch('notify',
                type: 'error',
                message: 'Gagal menghantar e-mel ujian: ' . $e->getMessage(),
            );
        }
    }
}; ?>

<div class="space-y-6">
    <!-- Quick Actions Section -->
    <flux:card>
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:heading size="lg">Jadualkan Notifikasi</flux:heading>
                    <flux:text class="text-gray-500 mt-1">
                        Jadualkan notifikasi berdasarkan jadual waktu kelas (7 hari akan datang).
                    </flux:text>
                </div>
                <flux:button
                    variant="primary"
                    wire:click="scheduleAllUpcomingNotifications"
                    wire:loading.attr="disabled"
                    icon="bell-alert"
                >
                    <span wire:loading.remove wire:target="scheduleAllUpcomingNotifications">Jadualkan Semua</span>
                    <span wire:loading wire:target="scheduleAllUpcomingNotifications">Menjadualkan...</span>
                </flux:button>
            </div>

            @if($this->timetable && $this->timetable->is_active)
                @php
                    $upcomingSlots = $this->upcomingTimetableSlots;
                    $scheduledSlots = $this->scheduledSlots;
                @endphp
                @if(count($upcomingSlots) > 0)
                    <div class="border border-gray-200 rounded-lg divide-y divide-gray-200">
                        @foreach(array_slice($upcomingSlots, 0, 5) as $slot)
                            @php
                                $slotDate = \Carbon\Carbon::parse($slot['session_date']);
                                $slotTime = \Carbon\Carbon::parse($slot['session_time']);
                                $slotKey = $slot['session_date'] . '_' . $slot['session_time'];
                                $isScheduled = isset($scheduledSlots[$slotKey]) && $scheduledSlots[$slotKey]['total'] > 0;
                                $pendingCount = $scheduledSlots[$slotKey]['pending'] ?? 0;
                                $sentCount = $scheduledSlots[$slotKey]['sent'] ?? 0;
                            @endphp
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 {{ $isScheduled ? 'bg-green-50/50' : '' }}">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 w-10 h-10 {{ $isScheduled ? 'bg-green-100' : 'bg-blue-100' }} rounded-lg flex items-center justify-center">
                                        @if($isScheduled)
                                            <flux:icon.check-circle class="w-5 h-5 text-green-600" />
                                        @else
                                            <flux:icon.calendar class="w-5 h-5 text-blue-600" />
                                        @endif
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">
                                            {{ $slotDate->format('d M Y') }} ({{ $slotDate->locale('ms')->dayName }})
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            {{ $slotTime->format('g:i A') }}
                                        </p>
                                    </div>
                                    @if($isScheduled)
                                        <div class="flex items-center gap-2">
                                            @if($pendingCount > 0)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 text-amber-700 text-xs rounded-full">
                                                    <flux:icon.clock class="w-3 h-3" />
                                                    {{ $pendingCount }} menunggu
                                                </span>
                                            @endif
                                            @if($sentCount > 0)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">
                                                    <flux:icon.check class="w-3 h-3" />
                                                    {{ $sentCount }} dihantar
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                @if($isScheduled && $pendingCount > 0)
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-100 text-green-700 text-sm font-medium rounded-lg">
                                        <flux:icon.check-circle class="w-4 h-4" />
                                        Telah Dijadualkan
                                    </span>
                                @else
                                    <flux:button
                                        variant="outline"
                                        size="sm"
                                        wire:click="scheduleNotificationsForSlot('{{ $slot['session_date'] }}', '{{ $slot['session_time'] }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="scheduleNotificationsForSlot('{{ $slot['session_date'] }}', '{{ $slot['session_time'] }}')"
                                    >
                                        <span wire:loading.remove wire:target="scheduleNotificationsForSlot('{{ $slot['session_date'] }}', '{{ $slot['session_time'] }}')">Jadualkan</span>
                                        <span wire:loading wire:target="scheduleNotificationsForSlot('{{ $slot['session_date'] }}', '{{ $slot['session_time'] }}')">...</span>
                                    </flux:button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if(count($upcomingSlots) > 5)
                        <p class="text-sm text-gray-500 mt-2 text-center">
                            Dan {{ count($upcomingSlots) - 5 }} slot lagi dalam 7 hari akan datang
                        </p>
                    @endif
                @else
                    <div class="text-center py-6 text-gray-500">
                        <flux:icon.calendar-days class="w-8 h-8 mx-auto mb-2 text-gray-300" />
                        <p>Tiada slot jadual waktu dalam 7 hari akan datang.</p>
                    </div>
                @endif
            @else
                <div class="text-center py-6">
                    <flux:icon.exclamation-triangle class="w-8 h-8 mx-auto mb-2 text-yellow-500" />
                    <p class="text-gray-700 font-medium">Jadual waktu tidak aktif</p>
                    <p class="text-sm text-gray-500 mt-1">Sila aktifkan jadual waktu untuk menjadualkan notifikasi.</p>
                </div>
            @endif
        </div>
    </flux:card>

    <!-- Notification Settings Section -->
    <flux:card>
        <div class="p-6">
            <div class="mb-6">
                <flux:heading size="lg">Tetapan Notifikasi Kelas</flux:heading>
                <flux:text class="text-gray-500 mt-1">
                    Konfigurasikan notifikasi yang akan dihantar kepada pelajar dan guru untuk kelas ini.
                </flux:text>
            </div>

            <div class="space-y-4">
                @php $typeLabels = $this->getTypeLabels(); @endphp
                @foreach($this->settings as $setting)
                    @php
                        $label = $typeLabels[$setting->notification_type] ?? ['name' => $setting->notification_type, 'description' => ''];
                    @endphp
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-gray-900">{{ $label['name'] }}</p>
                                @if($setting->is_enabled)
                                    <flux:badge color="green" size="sm">Aktif</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">Tidak Aktif</flux:badge>
                                @endif
                            </div>
                            <p class="text-sm text-gray-500 mt-1">{{ $label['description'] }}</p>
                            @if($setting->template)
                                <p class="text-xs text-gray-400 mt-1">
                                    Templat: {{ $setting->template->name }}
                                </p>
                            @endif
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-1.5 text-sm text-gray-500">
                                @if($setting->send_to_students)
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                                        <flux:icon.users class="w-4 h-4" />
                                        <span class="hidden sm:inline">Pelajar</span>
                                    </span>
                                @endif
                                @if($setting->send_to_teacher)
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                                        <flux:icon.academic-cap class="w-4 h-4" />
                                        <span class="hidden sm:inline">Guru</span>
                                    </span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:button
                                    variant="outline"
                                    size="sm"
                                    wire:click="sendTestNotification({{ $setting->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="sendTestNotification({{ $setting->id }})"
                                    title="Hantar e-mel ujian"
                                >
                                    <flux:icon.paper-airplane class="w-4 h-4" />
                                </flux:button>
                                <flux:button
                                    variant="outline"
                                    size="sm"
                                    wire:click="editSetting({{ $setting->id }})"
                                    title="Edit tetapan"
                                >
                                    <flux:icon.pencil class="w-4 h-4" />
                                </flux:button>
                                <flux:switch
                                    wire:click="toggleSetting({{ $setting->id }})"
                                    :checked="$setting->is_enabled"
                                />
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </flux:card>

    <!-- Notification History Section -->
    <flux:card>
        <div class="p-6">
            <div class="mb-6">
                <flux:heading size="lg">Sejarah Notifikasi</flux:heading>
                <flux:text class="text-gray-500 mt-1">
                    Senarai notifikasi yang telah dijadualkan dan dihantar untuk kelas ini, dikumpulkan mengikut slot sesi.
                </flux:text>
            </div>

            @php $groupedHistory = $this->groupedNotificationHistory; @endphp

            @if(count($groupedHistory) > 0)
                <div class="space-y-4">
                    @foreach($groupedHistory as $slotKey => $group)
                        <div x-data="{ expanded: false }" class="border border-gray-200 rounded-lg overflow-hidden">
                            <!-- Session Slot Header (Clickable) -->
                            <button
                                type="button"
                                @click="expanded = !expanded"
                                class="w-full bg-gradient-to-r from-blue-50 to-indigo-50 px-4 py-3 hover:from-blue-100 hover:to-indigo-100 transition-colors cursor-pointer"
                                :class="expanded ? 'border-b border-gray-200' : ''"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <flux:icon.calendar class="w-5 h-5 text-blue-600" />
                                    </div>
                                    <div class="flex-1 text-left">
                                        <p class="font-semibold text-gray-900">Sesi: {{ $group['label'] }}</p>
                                        @if($group['date'])
                                            <p class="text-sm text-gray-500">
                                                {{ $group['date']->locale('ms')->dayName }}
                                                @if($group['date']->isFuture())
                                                    <span class="text-blue-600">({{ $group['date']->diffForHumans() }})</span>
                                                @elseif($group['date']->isToday())
                                                    <span class="text-green-600">(Hari Ini)</span>
                                                @else
                                                    <span class="text-gray-400">({{ $group['date']->diffForHumans() }})</span>
                                                @endif
                                            </p>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-white border border-gray-200 rounded-full text-sm text-gray-700">
                                            <flux:icon.bell class="w-4 h-4 text-gray-400" />
                                            {{ count($group['notifications']) }} notifikasi
                                        </span>
                                        <!-- Expand/Collapse Icon -->
                                        <div class="w-6 h-6 flex items-center justify-center">
                                            <flux:icon.chevron-down
                                                class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                                ::class="expanded ? 'rotate-180' : ''"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </button>

                            <!-- Notifications for this slot (Collapsible) -->
                            <div
                                x-show="expanded"
                                x-collapse
                                class="divide-y divide-gray-100"
                            >
                                @foreach($group['notifications'] as $notification)
                                    @php
                                        $typeLabel = $typeLabels[$notification->setting?->notification_type ?? ''] ?? ['name' => '-', 'description' => ''];
                                    @endphp
                                    <div class="px-4 py-3 hover:bg-gray-50 transition-colors">
                                        <div class="flex items-center justify-between gap-4">
                                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                                <!-- Notification Type Icon -->
                                                <div class="flex-shrink-0">
                                                    @if(str_contains($notification->setting?->notification_type ?? '', 'reminder'))
                                                        <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center">
                                                            <flux:icon.clock class="w-4 h-4 text-amber-600" />
                                                        </div>
                                                    @elseif(str_contains($notification->setting?->notification_type ?? '', 'followup'))
                                                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                                            <flux:icon.check-circle class="w-4 h-4 text-green-600" />
                                                        </div>
                                                    @else
                                                        <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                                                            <flux:icon.bell class="w-4 h-4 text-gray-600" />
                                                        </div>
                                                    @endif
                                                </div>

                                                <!-- Notification Details -->
                                                <div class="min-w-0 flex-1">
                                                    <p class="font-medium text-gray-900 text-sm">{{ $typeLabel['name'] }}</p>
                                                    <p class="text-xs text-gray-500">
                                                        Dijadualkan hantar: {{ $notification->scheduled_at->format('d M Y, g:i A') }}
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Status and Recipients -->
                                            <div class="flex items-center gap-4">
                                                <!-- Recipients Count -->
                                                <div class="text-center min-w-[70px]">
                                                    <div class="text-sm">
                                                        <span class="text-green-600 font-medium">{{ $notification->total_sent }}</span>
                                                        <span class="text-gray-400">/</span>
                                                        <span class="text-gray-600">{{ $notification->total_recipients }}</span>
                                                    </div>
                                                    @if($notification->total_failed > 0)
                                                        <span class="text-xs text-red-500">({{ $notification->total_failed }} gagal)</span>
                                                    @else
                                                        <span class="text-xs text-gray-400">penerima</span>
                                                    @endif
                                                </div>

                                                <!-- Status Badge -->
                                                <flux:badge :color="$notification->status_badge_color" size="sm">
                                                    {{ $notification->status_label }}
                                                </flux:badge>

                                                <!-- Cancel Action -->
                                                <div class="w-8">
                                                    @if($notification->isPending())
                                                        <flux:button
                                                            variant="ghost"
                                                            size="sm"
                                                            wire:click="cancelNotification({{ $notification->id }})"
                                                            wire:confirm="Adakah anda pasti untuk membatalkan notifikasi ini?"
                                                            class="text-red-600 hover:text-red-800"
                                                        >
                                                            <flux:icon.x-mark class="w-4 h-4" />
                                                        </flux:button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <flux:icon.bell-slash class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                    <p class="text-gray-700 font-medium">Tiada notifikasi dijadualkan lagi.</p>
                    <p class="text-sm text-gray-500 mt-1">Aktifkan tetapan notifikasi di atas dan klik "Jadualkan Semua" untuk mula menghantar notifikasi.</p>
                </div>
            @endif
        </div>
    </flux:card>

    <!-- Toast Notification -->
    <div
        x-data="{ show: false, message: '', type: 'success' }"
        x-on:notify.window="
            show = true;
            message = $event.detail.message || 'Operasi berjaya';
            type = $event.detail.type || 'success';
            setTimeout(() => show = false, 4000)
        "
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-2"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-2"
        class="fixed bottom-4 right-4 z-50"
        style="display: none;"
    >
        <div
            x-show="type === 'success'"
            class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg shadow-lg"
        >
            <flux:icon.check-circle class="w-5 h-5 text-green-600" />
            <span x-text="message"></span>
        </div>
        <div
            x-show="type === 'error'"
            class="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg shadow-lg"
        >
            <flux:icon.exclamation-circle class="w-5 h-5 text-red-600" />
            <span x-text="message"></span>
        </div>
        <div
            x-show="type === 'info'"
            class="flex items-center gap-2 px-4 py-3 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg shadow-lg"
        >
            <flux:icon.information-circle class="w-5 h-5 text-blue-600" />
            <span x-text="message"></span>
        </div>
    </div>

    <!-- Edit Setting Modal -->
    <flux:modal wire:model="showEditModal" class="max-w-3xl">
        <div class="p-6" x-data="{
            copyToClipboard(text) {
                navigator.clipboard.writeText(text);
                $dispatch('notify', { type: 'success', message: 'Placeholder disalin: ' + text });
            }
        }">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-4 border-b border-gray-200 mb-5">
                <div>
                    <flux:heading size="lg">Edit Tetapan Notifikasi</flux:heading>
                    <flux:text class="text-gray-500 text-sm mt-1">Konfigurasikan tetapan untuk notifikasi ini</flux:text>
                </div>
            </div>

            <div class="space-y-5">
                <!-- Template Selection Card -->
                <div class="bg-gradient-to-br from-slate-50 to-gray-50 border border-gray-200 rounded-xl p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                <flux:icon.document-text class="w-4 h-4 text-blue-600" />
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">Templat E-mel</p>
                                <p class="text-xs text-gray-500">Pilih templat untuk notifikasi ini</p>
                            </div>
                        </div>
                        @if($this->selectedTemplate)
                            <a
                                href="{{ route('admin.settings.notifications.builder', $this->selectedTemplate) }}"
                                target="_blank"
                                class="inline-flex items-center gap-2 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors"
                            >
                                <flux:icon.pencil-square class="w-4 h-4" />
                                Edit Templat
                            </a>
                        @endif
                    </div>

                    <flux:select wire:model.live="selectedTemplateId" class="w-full">
                        <option value="">-- Pilih Templat --</option>
                        @foreach($this->templates as $type => $templateGroup)
                            <optgroup label="{{ ucfirst(str_replace('_', ' ', $type)) }}">
                                @foreach($templateGroup as $template)
                                    <option value="{{ $template->id }}">
                                        {{ $template->name }} ({{ strtoupper($template->language) }})
                                        @if($template->isVisualEditor()) - Visual @endif
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Template Preview -->
                @if($this->selectedTemplate)
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <!-- Preview Header -->
                        <div class="bg-gradient-to-r from-gray-100 to-slate-100 px-4 py-3 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <flux:icon.eye class="w-4 h-4 text-gray-600" />
                                    <span class="font-medium text-gray-700 text-sm">Pratonton Templat</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($this->selectedTemplate->isVisualEditor())
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-purple-100 text-purple-700 text-xs font-medium rounded-full">
                                            <flux:icon.paint-brush class="w-3 h-3" />
                                            Visual Editor
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 text-gray-600 text-xs font-medium rounded-full">
                                            <flux:icon.code-bracket class="w-3 h-3" />
                                            Teks
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Preview Content -->
                        <div class="bg-white p-4 space-y-4">
                            @if($this->selectedTemplate->subject)
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Subjek</label>
                                    <div class="bg-gray-50 border border-gray-100 rounded-lg px-3 py-2.5 text-sm text-gray-900">
                                        {{ $this->selectedTemplate->subject }}
                                    </div>
                                </div>
                            @endif

                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Kandungan</label>
                                @if($this->selectedTemplate->isVisualEditor())
                                    <div class="bg-gradient-to-br from-purple-50 to-indigo-50 border border-purple-100 rounded-lg p-4 text-center">
                                        <flux:icon.paint-brush class="w-8 h-8 mx-auto text-purple-400 mb-2" />
                                        <p class="text-sm text-purple-700 font-medium">Templat Visual</p>
                                        <p class="text-xs text-purple-600 mt-1">Klik "Edit Templat" untuk melihat dan mengubah reka bentuk</p>
                                    </div>
                                @else
                                    <div class="bg-gray-50 border border-gray-100 rounded-lg p-3 max-h-40 overflow-y-auto">
                                        <div class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ $this->selectedTemplate->content }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <!-- No Template Selected State -->
                    <div class="border border-dashed border-gray-300 rounded-xl p-8 text-center bg-gray-50/50">
                        <flux:icon.document class="w-10 h-10 mx-auto text-gray-300 mb-3" />
                        <p class="text-gray-500 text-sm">Pilih templat untuk melihat pratonton</p>
                    </div>
                @endif

                <!-- Recipients Card -->
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-xl p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-7 h-7 bg-green-100 rounded-lg flex items-center justify-center">
                            <flux:icon.users class="w-4 h-4 text-green-600" />
                        </div>
                        <span class="font-semibold text-gray-900 text-sm">Penerima Notifikasi</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="flex items-center gap-3 p-3 bg-white border border-green-100 rounded-lg cursor-pointer hover:border-green-300 transition-colors">
                            <input type="checkbox" wire:model="sendToStudents" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500">
                            <div>
                                <p class="font-medium text-gray-900 text-sm">Pelajar</p>
                                <p class="text-xs text-gray-500">Semua pelajar dalam kelas</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 bg-white border border-green-100 rounded-lg cursor-pointer hover:border-green-300 transition-colors">
                            <input type="checkbox" wire:model="sendToTeacher" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500">
                            <div>
                                <p class="font-medium text-gray-900 text-sm">Guru</p>
                                <p class="text-xs text-gray-500">Guru yang mengajar kelas</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Custom Override Section -->
                <div class="border border-amber-200 rounded-xl overflow-hidden">
                    <div class="bg-gradient-to-r from-amber-50 to-yellow-50 px-4 py-3 border-b border-amber-200">
                        <div class="flex items-center gap-2">
                            <flux:icon.adjustments-horizontal class="w-4 h-4 text-amber-600" />
                            <span class="font-semibold text-gray-900 text-sm">Kandungan Tersuai (Pilihan)</span>
                        </div>
                        <p class="text-xs text-amber-700 mt-1">Gantikan kandungan templat dengan kandungan tersuai untuk kelas ini sahaja</p>
                    </div>
                    <div class="bg-white p-4 space-y-4">
                        <flux:field>
                            <flux:label>Subjek Tersuai</flux:label>
                            <flux:input wire:model="customSubject" placeholder="Biarkan kosong untuk menggunakan templat" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Kandungan Tersuai</flux:label>
                            <flux:textarea wire:model="customContent" rows="4" placeholder="Biarkan kosong untuk menggunakan templat" />
                        </flux:field>
                    </div>
                </div>

                <!-- Available Placeholders -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-7 h-7 bg-blue-100 rounded-lg flex items-center justify-center">
                            <flux:icon.code-bracket class="w-4 h-4 text-blue-600" />
                        </div>
                        <div>
                            <span class="font-semibold text-gray-900 text-sm">Placeholder</span>
                            <p class="text-xs text-blue-600">Klik untuk menyalin</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($this->availablePlaceholders as $placeholder => $description)
                            <button
                                type="button"
                                x-on:click="copyToClipboard('{{ $placeholder }}')"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-white border border-blue-200 rounded-lg hover:bg-blue-100 hover:border-blue-300 transition-all duration-150 cursor-pointer group shadow-sm"
                                title="{{ $description }}"
                            >
                                <code class="text-xs text-blue-700 font-mono">{{ $placeholder }}</code>
                                <flux:icon.clipboard-document class="w-3 h-3 text-blue-400 group-hover:text-blue-600 transition-colors" />
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-3 pt-5 mt-5 border-t border-gray-200">
                <flux:button variant="ghost" wire:click="$set('showEditModal', false)">Batal</flux:button>
                <flux:button variant="primary" wire:click="saveSetting" icon="check">Simpan Tetapan</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
