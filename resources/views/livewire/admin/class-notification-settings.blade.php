<?php

use App\Models\ClassModel;
use App\Models\ClassNotificationAttachment;
use App\Models\ClassNotificationSetting;
use App\Models\NotificationTemplate;
use App\Models\ScheduledNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ClassModel $class;

    public bool $autoScheduleEnabled = false;

    public bool $showEditModal = false;

    public ?int $editingSettingId = null;

    public ?int $selectedTemplateId = null;

    public ?string $customSubject = '';

    public ?string $customContent = '';

    public bool $sendToStudents = true;

    public bool $sendToTeacher = true;

    public bool $whatsappEnabled = false;

    public ?int $customMinutesBefore = null;

    // Content source selection: 'system' or 'custom'
    public string $contentSource = 'system';

    // Visual builder properties
    public string $editorType = 'text';

    public ?string $designJson = null;

    public ?string $htmlContent = null;

    public bool $hasVisualContent = false;

    // WhatsApp template properties
    public ?string $whatsappContent = '';

    public bool $useCustomWhatsappTemplate = false;

    public string $activeChannelTab = 'email';

    // Main channel tab for separating Email/WhatsApp views
    #[\Livewire\Attributes\Url(as: 'channel')]
    public string $activeMainTab = 'email';

    // Global channel enabled states
    public bool $emailChannelEnabled = true;

    public bool $whatsappChannelEnabled = true;

    // WhatsApp image properties
    public $whatsappImage = null;

    public ?string $existingWhatsappImagePath = null;

    // Attachment properties
    public $newAttachments = [];

    public Collection $attachments;

    // WhatsApp Template Modal properties
    public bool $showWhatsappModal = false;

    public ?int $editingWhatsappSettingId = null;

    public string $waTemplateContent = '';

    public $waTemplateImage = null;

    public ?string $waExistingImagePath = null;

    public function mount(ClassModel $class): void
    {
        $this->class = $class;
        $this->autoScheduleEnabled = $class->auto_schedule_notifications ?? false;
        $this->emailChannelEnabled = $class->email_channel_enabled ?? true;
        $this->whatsappChannelEnabled = $class->whatsapp_channel_enabled ?? true;
        $this->attachments = collect();
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

            if (! $existingSetting) {
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

    public function toggleAutoSchedule(): void
    {
        $newValue = ! $this->autoScheduleEnabled;
        $this->class->update(['auto_schedule_notifications' => $newValue]);
        $this->autoScheduleEnabled = $newValue;

        $this->dispatch('notify',
            type: 'success',
            message: $newValue
                ? 'Penjadualan automatik telah diaktifkan. Sistem akan menjadualkan notifikasi secara automatik setiap hari.'
                : 'Penjadualan automatik telah dinyahaktifkan. Sila jadualkan notifikasi secara manual.',
        );
    }

    public function setActiveMainTab(string $tab): void
    {
        $this->activeMainTab = $tab;
    }

    public function updatedEmailChannelEnabled(): void
    {
        $this->class->update(['email_channel_enabled' => $this->emailChannelEnabled]);

        $this->dispatch('notify',
            type: 'success',
            message: $this->emailChannelEnabled
                ? 'Saluran E-mel telah diaktifkan untuk semua notifikasi.'
                : 'Saluran E-mel telah dinyahaktifkan untuk semua notifikasi.',
        );
    }

    public function updatedWhatsappChannelEnabled(): void
    {
        $this->class->update(['whatsapp_channel_enabled' => $this->whatsappChannelEnabled]);

        $this->dispatch('notify',
            type: 'success',
            message: $this->whatsappChannelEnabled
                ? 'Saluran WhatsApp telah diaktifkan untuk semua notifikasi.'
                : 'Saluran WhatsApp telah dinyahaktifkan untuk semua notifikasi.',
        );
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
                $slotKey = $notification->scheduled_session_date->format('Y-m-d').'_'.$notification->scheduled_session_time;
                $slotLabel = $notification->scheduled_session_date->format('d M Y').' - '.\Carbon\Carbon::parse($notification->scheduled_session_time)->format('g:i A');
                $slotDate = $notification->scheduled_session_date;
            } elseif ($notification->session) {
                $slotKey = $notification->session->session_date->format('Y-m-d').'_'.$notification->session->session_time->format('H:i:s');
                $slotLabel = $notification->session->session_date->format('d M Y').' - '.$notification->session->session_time->format('g:i A');
                $slotDate = $notification->session->session_date;
            } else {
                $slotKey = 'unknown';
                $slotLabel = 'Tidak Diketahui';
                $slotDate = null;
            }

            if (! isset($grouped[$slotKey])) {
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

    public function getFilteredNotificationHistoryProperty(): array
    {
        $channel = $this->activeMainTab;

        // Build query based on channel
        $query = $this->class->scheduledNotifications()
            ->with(['session', 'setting'])
            ->with(['logs' => fn ($q) => $q->where('channel', $channel)]);

        // Filter based on channel settings on the notification setting
        // For Email: show if setting has email_enabled (defaults to true if null)
        // For WhatsApp: show if setting has whatsapp_enabled
        if ($channel === 'email') {
            $query->whereHas('setting', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('email_enabled', true)
                        ->orWhereNull('email_enabled'); // Default to true if not set
                });
            });
        } else {
            // WhatsApp channel
            $query->whereHas('setting', fn ($q) => $q->where('whatsapp_enabled', true));
        }

        $notifications = $query
            ->orderByDesc('scheduled_session_date')
            ->orderByDesc('scheduled_session_time')
            ->orderByDesc('scheduled_at')
            ->get();

        $grouped = [];

        foreach ($notifications as $notification) {
            if ($notification->scheduled_session_date) {
                $slotKey = $notification->scheduled_session_date->format('Y-m-d').'_'.$notification->scheduled_session_time;
                $slotLabel = $notification->scheduled_session_date->format('d M Y').' - '.\Carbon\Carbon::parse($notification->scheduled_session_time)->format('g:i A');
                $slotDate = $notification->scheduled_session_date;
            } elseif ($notification->session) {
                $slotKey = $notification->session->session_date->format('Y-m-d').'_'.$notification->session->session_time->format('H:i:s');
                $slotLabel = $notification->session->session_date->format('d M Y').' - '.$notification->session->session_time->format('g:i A');
                $slotDate = $notification->session->session_date;
            } else {
                $slotKey = 'unknown';
                $slotLabel = 'Tidak Diketahui';
                $slotDate = null;
            }

            if (! isset($grouped[$slotKey])) {
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
        if (! $this->selectedTemplateId) {
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
            $setting->update(['is_enabled' => ! $setting->is_enabled]);

            $this->dispatch('notify',
                type: 'success',
                message: $setting->is_enabled
                    ? 'Notifikasi telah diaktifkan'
                    : 'Notifikasi telah dinyahaktifkan',
            );
        }
    }

    public function toggleEmailSetting(int $settingId, ?bool $newValue = null): void
    {
        $setting = ClassNotificationSetting::find($settingId);
        if ($setting && $setting->class_id === $this->class->id) {
            // Get current WhatsApp state
            $currentWhatsappEnabled = $setting->whatsapp_enabled ?? false;

            // Determine new email state
            if ($newValue !== null) {
                // Use the explicitly passed value from the switch
                $newEmailEnabled = $newValue;
            } else {
                // Fallback: toggle based on current state
                $currentEmailEnabled = $setting->email_enabled;
                if ($currentEmailEnabled === null) {
                    $currentEmailEnabled = $setting->is_enabled && ! $currentWhatsappEnabled;
                }
                $newEmailEnabled = ! $currentEmailEnabled;
            }

            // Always explicitly set both channel flags to avoid null ambiguity
            $updateData = [
                'email_enabled' => $newEmailEnabled,
                'whatsapp_enabled' => $currentWhatsappEnabled, // Keep WhatsApp unchanged
                'is_enabled' => $newEmailEnabled || $currentWhatsappEnabled,
            ];

            $setting->update($updateData);

            $this->dispatch('notify',
                type: 'success',
                message: $newEmailEnabled
                    ? 'Notifikasi e-mel telah diaktifkan'
                    : 'Notifikasi e-mel telah dinyahaktifkan',
            );
        }
    }

    public function toggleWhatsappSetting(int $settingId, ?bool $newValue = null): void
    {
        $setting = ClassNotificationSetting::find($settingId);
        if ($setting && $setting->class_id === $this->class->id) {
            // Get current email state
            $currentEmailEnabled = $setting->email_enabled;
            $currentWhatsappEnabled = $setting->whatsapp_enabled ?? false;

            // If email_enabled is null (legacy data), determine from is_enabled
            if ($currentEmailEnabled === null) {
                $currentEmailEnabled = $setting->is_enabled && ! $currentWhatsappEnabled;
            }

            // Determine new WhatsApp state
            if ($newValue !== null) {
                // Use the explicitly passed value from the switch
                $newWhatsappEnabled = $newValue;
            } else {
                // Fallback: toggle based on current state
                $newWhatsappEnabled = ! $currentWhatsappEnabled;
            }

            // Always explicitly set both channel flags to avoid null ambiguity
            $updateData = [
                'email_enabled' => $currentEmailEnabled, // Keep Email unchanged
                'whatsapp_enabled' => $newWhatsappEnabled,
                'is_enabled' => $currentEmailEnabled || $newWhatsappEnabled,
            ];

            $setting->update($updateData);

            $this->dispatch('notify',
                type: 'success',
                message: $newWhatsappEnabled
                    ? 'Notifikasi WhatsApp telah diaktifkan'
                    : 'Notifikasi WhatsApp telah dinyahaktifkan',
            );
        }
    }

    public function editWhatsappTemplate(int $settingId): void
    {
        $setting = ClassNotificationSetting::find($settingId);
        if ($setting && $setting->class_id === $this->class->id) {
            $this->editingWhatsappSettingId = $settingId;
            $this->waTemplateContent = $setting->whatsapp_content ?? '';
            $this->waTemplateImage = null;
            $this->waExistingImagePath = $setting->whatsapp_image_path;
            $this->showWhatsappModal = true;
        }
    }

    public function saveWhatsappTemplate(): void
    {
        $setting = ClassNotificationSetting::find($this->editingWhatsappSettingId);
        if ($setting && $setting->class_id === $this->class->id) {
            $updateData = [
                'whatsapp_content' => $this->waTemplateContent,
                'use_custom_whatsapp_template' => ! empty($this->waTemplateContent),
            ];

            // Handle image upload
            if ($this->waTemplateImage) {
                // Delete old image if exists
                if ($this->waExistingImagePath) {
                    Storage::disk('public')->delete($this->waExistingImagePath);
                }

                $path = $this->waTemplateImage->store('whatsapp-templates', 'public');
                $updateData['whatsapp_image_path'] = $path;
                $this->waExistingImagePath = $path;
            }

            $setting->update($updateData);

            $this->dispatch('notify', type: 'success', message: 'Templat WhatsApp berjaya disimpan');
            $this->showWhatsappModal = false;
        }
    }

    public function removeWaTemplateImage(): void
    {
        $setting = ClassNotificationSetting::find($this->editingWhatsappSettingId);
        if ($setting && $setting->class_id === $this->class->id && $this->waExistingImagePath) {
            Storage::disk('public')->delete($this->waExistingImagePath);
            $setting->update(['whatsapp_image_path' => null]);
            $this->waExistingImagePath = null;
            $this->dispatch('notify', type: 'success', message: 'Gambar telah dipadamkan');
        }
    }

    #[\Livewire\Attributes\Computed]
    public function waNotificationTypeLabel(): array
    {
        if (! $this->editingWhatsappSettingId) {
            return ['name' => '', 'description' => ''];
        }

        $setting = ClassNotificationSetting::find($this->editingWhatsappSettingId);
        if (! $setting) {
            return ['name' => '', 'description' => ''];
        }

        return ClassNotificationSetting::getNotificationTypeLabels()[$setting->notification_type] ?? [
            'name' => $setting->notification_type,
            'description' => '',
        ];
    }

    #[\Livewire\Attributes\Computed]
    public function waPreviewContent(): string
    {
        if (empty($this->waTemplateContent)) {
            return '';
        }

        $replacements = [
            '{{student_name}}' => 'Ahmad bin Ali (Contoh)',
            '{{teacher_name}}' => 'Ustazah Noraini binti Sulaiman',
            '{{class_name}}' => $this->class->title,
            '{{course_name}}' => $this->class->course?->name ?? 'Kursus',
            '{{session_date}}' => now()->addDay()->format('d M Y'),
            '{{session_time}}' => '8:00 PM',
            '{{session_datetime}}' => now()->addDay()->format('d M Y') . ' 8:00 PM',
            '{{location}}' => $this->class->location ?? 'TBA',
            '{{meeting_url}}' => $this->class->meeting_url ?? 'http://example.com/meet',
            '{{whatsapp_link}}' => $this->class->whatsapp_group_link ?? '',
            '{{duration}}' => ($this->class->duration_minutes ?? 60) . ' minit',
            '{{remaining_sessions}}' => '8',
            '{{total_sessions}}' => '12',
            '{{attendance_rate}}' => '92%',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $this->waTemplateContent);
    }

    public function getWaPreviewContent(): string
    {
        return $this->waPreviewContent;
    }

    public function editSetting(int $settingId): void
    {
        $setting = ClassNotificationSetting::with('attachments')->find($settingId);
        if ($setting && $setting->class_id === $this->class->id) {
            $this->editingSettingId = $settingId;
            $this->selectedTemplateId = $setting->template_id;
            $this->customSubject = $setting->custom_subject;
            $this->customContent = $setting->custom_content;
            $this->sendToStudents = $setting->send_to_students;
            $this->sendToTeacher = $setting->send_to_teacher;
            $this->whatsappEnabled = $setting->whatsapp_enabled ?? false;
            $this->customMinutesBefore = $setting->custom_minutes_before;

            // Visual builder fields
            $this->editorType = $setting->editor_type ?? 'text';
            $this->designJson = $setting->design_json ? json_encode($setting->design_json) : null;
            $this->htmlContent = $setting->html_content;
            $this->hasVisualContent = ! empty($setting->html_content);

            // Determine content source based on template_id
            // If template_id is null, it means custom content mode is being used (even if content is empty)
            // If template_id is set, it means system template is being used
            $this->contentSource = $setting->template_id === null ? 'custom' : 'system';

            // WhatsApp template fields
            $this->whatsappContent = $setting->whatsapp_content ?? '';
            $this->useCustomWhatsappTemplate = $setting->use_custom_whatsapp_template ?? false;
            $this->activeChannelTab = 'email';

            // WhatsApp image
            $this->whatsappImage = null;
            $this->existingWhatsappImagePath = $setting->whatsapp_image_path;

            // Attachments
            $this->attachments = $setting->attachments;
            $this->newAttachments = [];

            $this->showEditModal = true;
        }
    }

    /**
     * Auto-select appropriate template when switching to system mode
     */
    public function updatedContentSource(string $value): void
    {
        if ($value === 'system' && empty($this->selectedTemplateId)) {
            // Try to auto-select an appropriate template based on notification type
            $setting = ClassNotificationSetting::find($this->editingSettingId);
            if ($setting) {
                $type = $setting->notification_type;
                $templateType = str_starts_with($type, 'session_reminder_')
                    ? 'session_reminder'
                    : (str_starts_with($type, 'session_followup_')
                        ? 'session_followup'
                        : $type);

                $template = NotificationTemplate::active()
                    ->where('type', $templateType)
                    ->where('language', 'ms')
                    ->first();

                if ($template) {
                    $this->selectedTemplateId = $template->id;
                }
            }
        }
    }

    public function saveSetting(): void
    {
        $setting = ClassNotificationSetting::find($this->editingSettingId);
        if ($setting && $setting->class_id === $this->class->id) {
            // Validate: if system mode, template must be selected
            if ($this->contentSource === 'system' && empty($this->selectedTemplateId)) {
                $this->dispatch('notify',
                    type: 'error',
                    message: 'Sila pilih templat sistem terlebih dahulu',
                );

                return;
            }

            // Prepare data based on content source
            $data = [
                'send_to_students' => $this->sendToStudents,
                'send_to_teacher' => $this->sendToTeacher,
                'whatsapp_enabled' => $this->whatsappEnabled,
                'custom_minutes_before' => $this->customMinutesBefore,
                // WhatsApp template data
                'whatsapp_content' => $this->useCustomWhatsappTemplate ? ($this->whatsappContent ?: null) : null,
                'use_custom_whatsapp_template' => $this->useCustomWhatsappTemplate,
            ];

            if ($this->contentSource === 'system') {
                // Using system template - clear custom content and keep template
                $data['template_id'] = $this->selectedTemplateId;
                $data['custom_subject'] = null;
                $data['custom_content'] = null;
                $data['editor_type'] = 'text';
                $data['design_json'] = null;
                $data['html_content'] = null;
            } else {
                // Using custom content - keep editor type and content
                $data['template_id'] = null; // Clear system template when using custom
                $data['editor_type'] = $this->editorType;

                if ($this->editorType === 'visual') {
                    $data['custom_subject'] = $this->customSubject ?: null; // Allow subject for visual editor
                    $data['custom_content'] = null;
                    $data['design_json'] = $this->designJson ? json_decode($this->designJson, true) : null;
                    $data['html_content'] = $this->htmlContent;
                } else {
                    $data['custom_subject'] = $this->customSubject ?: null;
                    $data['custom_content'] = $this->customContent ?: null;
                    $data['design_json'] = null;
                    $data['html_content'] = null;
                }
            }

            // Handle WhatsApp image upload
            if ($this->whatsappImage) {
                // Delete old image if exists
                if ($setting->whatsapp_image_path) {
                    Storage::disk('public')->delete($setting->whatsapp_image_path);
                }

                // Store new image
                $path = $this->whatsappImage->store('whatsapp-images', 'public');
                $data['whatsapp_image_path'] = $path;
            }

            $setting->update($data);

            $this->showEditModal = false;
            $this->resetEditForm();

            $this->dispatch('notify',
                type: 'success',
                message: 'Tetapan notifikasi telah dikemaskini',
            );
        }
    }

    public function removeWhatsappImage(): void
    {
        $setting = ClassNotificationSetting::find($this->editingSettingId);
        if ($setting && $setting->class_id === $this->class->id) {
            if ($setting->whatsapp_image_path) {
                Storage::disk('public')->delete($setting->whatsapp_image_path);
                $setting->update(['whatsapp_image_path' => null]);
            }
            $this->existingWhatsappImagePath = null;
            $this->whatsappImage = null;

            $this->dispatch('notify',
                type: 'success',
                message: 'Gambar WhatsApp telah dipadam',
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
        $this->whatsappEnabled = false;
        $this->customMinutesBefore = null;
        $this->contentSource = 'system';
        $this->editorType = 'text';
        $this->designJson = null;
        $this->htmlContent = null;
        $this->hasVisualContent = false;
        // WhatsApp template fields
        $this->whatsappContent = '';
        $this->useCustomWhatsappTemplate = false;
        $this->activeChannelTab = 'email';
        // WhatsApp image
        $this->whatsappImage = null;
        $this->existingWhatsappImagePath = null;
        // Attachments
        $this->attachments = collect();
        $this->newAttachments = [];
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

        if (! $timetable || ! $timetable->is_active) {
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
            // Normalize time format to HH:MM (remove seconds if present)
            $time = $notification->scheduled_session_time;
            if ($time && preg_match('/^(\d{1,2}:\d{2})(:\d{2})?$/', $time, $matches)) {
                $time = $matches[1]; // Get only HH:MM part
            }

            $slotKey = $notification->scheduled_session_date->format('Y-m-d').'_'.$time;

            if (! isset($scheduled[$slotKey])) {
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
        $slotKey = $date.'_'.$time;

        return isset($this->scheduledSlots[$slotKey]) && $this->scheduledSlots[$slotKey]['total'] > 0;
    }

    public function getSlotScheduledCount(string $date, string $time): int
    {
        $slotKey = $date.'_'.$time;

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
        $sessionDateTime = \Carbon\Carbon::parse($date.' '.$time);
        $totalScheduled = 0;

        // Normalize time for storage (HH:MM format)
        $normalizedTime = preg_replace('/^(\d{1,2}:\d{2})(:\d{2})?$/', '$1', $time);

        foreach ($settings as $setting) {
            $scheduledAt = $sessionDateTime->copy()->subMinutes($setting->getMinutesBefore());

            if ($scheduledAt->isFuture()) {
                // Check for existing with both time formats (HH:MM and HH:MM:SS)
                $exists = \App\Models\ScheduledNotification::where('class_id', $this->class->id)
                    ->where('scheduled_session_date', $sessionDate->toDateString())
                    ->where(function ($query) use ($normalizedTime) {
                        $query->where('scheduled_session_time', $normalizedTime)
                            ->orWhere('scheduled_session_time', $normalizedTime.':00');
                    })
                    ->where('class_notification_setting_id', $setting->id)
                    ->whereIn('status', ['pending', 'processing'])
                    ->exists();

                if (! $exists) {
                    \App\Models\ScheduledNotification::create([
                        'class_id' => $this->class->id,
                        'session_id' => null,
                        'scheduled_session_date' => $sessionDate->toDateString(),
                        'scheduled_session_time' => $normalizedTime,
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
                message: $totalScheduled.' notifikasi telah dijadualkan untuk slot ini',
            );
        } else {
            $this->dispatch('notify',
                type: 'info',
                message: 'Tiada notifikasi baharu untuk dijadualkan (mungkin sudah dijadualkan atau masa telah berlalu)',
            );
        }
    }

    public function cancelScheduledNotificationsForSlot(string $date, string $time): void
    {
        // Normalize time format to HH:MM (remove seconds if present)
        $normalizedTime = preg_replace('/^(\d{1,2}:\d{2})(:\d{2})?$/', '$1', $time);

        // Find and cancel all pending notifications for this slot
        // Use whereDate for proper date comparison with date-cast column
        $cancelled = ScheduledNotification::where('class_id', $this->class->id)
            ->whereDate('scheduled_session_date', $date)
            ->where(function ($query) use ($normalizedTime) {
                $query->where('scheduled_session_time', $normalizedTime)
                    ->orWhere('scheduled_session_time', $normalizedTime.':00');
            })
            ->whereIn('status', ['pending', 'processing'])
            ->update(['status' => 'cancelled']);

        // Clear cached computed property to force refresh on re-render
        unset($this->scheduledSlots);

        if ($cancelled > 0) {
            $this->dispatch('notify',
                type: 'success',
                message: $cancelled.' notifikasi telah dibatalkan untuk slot ini',
            );
        } else {
            $this->dispatch('notify',
                type: 'info',
                message: 'Tiada notifikasi untuk dibatalkan',
            );
        }
    }

    public function scheduleAllUpcomingNotifications(): void
    {
        $service = app(\App\Services\NotificationService::class);

        // Check if timetable exists
        if (! $this->class->timetable || ! $this->class->timetable->is_active) {
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
                message: count($scheduled).' notifikasi telah dijadualkan berdasarkan jadual waktu',
            );
        } else {
            $this->dispatch('notify',
                type: 'info',
                message: 'Tiada notifikasi baharu untuk dijadualkan (mungkin sudah dijadualkan atau tiada slot akan datang)',
            );
        }
    }

    public ?int $testSettingId = null;
    public string $testChannel = 'email';
    public bool $showTestModal = false;

    public function openTestModal(int $settingId, string $channel = 'email'): void
    {
        $this->testSettingId = $settingId;
        $this->testChannel = $channel;
        $this->showTestModal = true;
    }

    public function sendTestNotification(): void
    {
        $setting = ClassNotificationSetting::with(['template', 'attachments'])->find($this->testSettingId);

        if (! $setting || $setting->class_id !== $this->class->id) {
            $this->dispatch('notify',
                type: 'error',
                message: 'Tetapan tidak dijumpai',
            );
            $this->showTestModal = false;

            return;
        }

        // Get the current user for test
        $user = auth()->user();

        // Determine content source with priority: class custom > system template
        $template = $setting->template;
        $hasCustomVisualTemplate = $setting->isVisualEditor() && $setting->html_content;
        $hasCustomTextTemplate = $setting->isTextEditor() && $setting->custom_content;

        if ($hasCustomVisualTemplate) {
            // Use class-level visual template
            $content = $setting->html_content;
            $isVisualTemplate = true;
        } elseif ($hasCustomTextTemplate) {
            // Use class-level text template
            $content = $setting->custom_content;
            $isVisualTemplate = false;
        } elseif ($template && $template->isVisualEditor() && $template->html_content) {
            // Fall back to system visual template
            $content = $template->html_content;
            $isVisualTemplate = true;
        } elseif ($template) {
            // Fall back to system text template
            $content = $setting->getEffectiveContent();
            $isVisualTemplate = false;
        } else {
            $this->dispatch('notify',
                type: 'error',
                message: 'Sila pilih templat atau tetapkan kandungan tersuai terlebih dahulu',
            );
            $this->showTestModal = false;

            return;
        }

        // Get subject - use custom, template, or generate default based on notification type
        $subject = $setting->custom_subject ?? $template?->subject;
        if (! $subject) {
            // Generate default subject for visual templates without explicit subject
            $typeLabels = ClassNotificationSetting::getNotificationTypeLabels();
            $typeName = $typeLabels[$setting->notification_type]['name'] ?? 'Notifikasi';
            $subject = "{$typeName} - {$this->class->title}";
        }

        if (! $content) {
            $this->dispatch('notify',
                type: 'error',
                message: 'Kandungan templat tidak lengkap. Sila edit tetapan ini.',
            );
            $this->showTestModal = false;

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
            '{{session_datetime}}' => now()->addDay()->format('d M Y').' - 8:00 PM',
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
            if ($this->testChannel === 'whatsapp') {
                // Send WhatsApp test message
                $phoneNumber = $user->phone_number ?? $user->phone;

                if (empty($phoneNumber)) {
                    $this->dispatch('notify',
                        type: 'error',
                        message: 'Nombor telefon anda tidak ditetapkan. Sila kemaskini profil anda terlebih dahulu.',
                    );
                    $this->showTestModal = false;

                    return;
                }

                // Update config before instantiating service
                $apiToken = app(\App\Services\SettingsService::class)->get('whatsapp_api_token');
                if (! empty($apiToken)) {
                    config(['services.onsend.api_token' => $apiToken]);
                    config(['services.onsend.enabled' => true]);
                }

                $whatsApp = new \App\Services\WhatsAppService();

                if (! $whatsApp->isEnabled()) {
                    $this->dispatch('notify',
                        type: 'error',
                        message: 'Perkhidmatan WhatsApp tidak diaktifkan. Sila konfigurasikan dalam Tetapan > WhatsApp.',
                    );
                    $this->showTestModal = false;

                    return;
                }

                // Check for dedicated WhatsApp image FIRST (independent of template choice)
                // Prioritize: newly uploaded in modal > saved path in modal > saved in database
                $dedicatedWhatsAppImage = null;
                if ($this->whatsappImage) {
                    // Save temporarily uploaded image to public storage for API access
                    $tempPath = $this->whatsappImage->store('whatsapp-images/temp', 'public');
                    $dedicatedWhatsAppImage = Storage::disk('public')->url($tempPath);
                } elseif ($this->existingWhatsappImagePath) {
                    // Use existing saved image path from edit modal
                    $dedicatedWhatsAppImage = Storage::disk('public')->url($this->existingWhatsappImagePath);
                } elseif ($setting->hasWhatsAppImage()) {
                    // Fallback to setting's saved image in database
                    $dedicatedWhatsAppImage = $setting->getWhatsAppImageUrl();
                }

                // Determine WhatsApp content
                if ($setting->hasCustomWhatsAppTemplate()) {
                    // Use custom WhatsApp template with placeholder replacement
                    $whatsAppContent = str_replace(
                        array_keys($placeholders),
                        array_values($placeholders),
                        $setting->whatsapp_content
                    );
                    $whatsAppMessage = "[UJIAN] {$personalizedSubject}\n\n{$whatsAppContent}\n\n— Ini adalah mesej ujian —";
                    $imageUrls = []; // Custom WhatsApp templates don't include HTML images
                } else {
                    // Fall back to converting email content
                    // Extract image URLs from HTML content first
                    $imageUrls = $this->extractImageUrls($personalizedContent);

                    // Convert HTML to plain text for WhatsApp (without image URLs at end)
                    $plainTextContent = $this->convertHtmlToWhatsAppText($personalizedContent, false);
                    $whatsAppMessage = "[UJIAN] {$personalizedSubject}\n\n{$plainTextContent}\n\n— Ini adalah mesej ujian —";
                }

                // Normalize phone number
                $normalizedPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
                if (str_starts_with($normalizedPhone, '0')) {
                    $normalizedPhone = '60'.substr($normalizedPhone, 1);
                } elseif (! str_starts_with($normalizedPhone, '60')) {
                    $normalizedPhone = '60'.$normalizedPhone;
                }

                $imagesSent = 0;
                $imagesFailed = 0;

                // Send dedicated WhatsApp image first (if exists)
                $localDomainWarning = false;
                if ($dedicatedWhatsAppImage) {
                    // Check if URL is local/development (WhatsApp API can't access these)
                    $parsedUrl = parse_url($dedicatedWhatsAppImage);
                    $host = $parsedUrl['host'] ?? '';
                    if (str_ends_with($host, '.test') || str_ends_with($host, '.local') || $host === 'localhost' || str_starts_with($host, '127.') || str_starts_with($host, '192.168.')) {
                        $localDomainWarning = true;
                        \Illuminate\Support\Facades\Log::warning('WhatsApp image URL is local domain - API cannot access', [
                            'image_url' => $dedicatedWhatsAppImage,
                            'host' => $host,
                        ]);
                    }

                    \Illuminate\Support\Facades\Log::info('Sending WhatsApp test image', [
                        'phone' => $normalizedPhone,
                        'image_url' => $dedicatedWhatsAppImage,
                    ]);

                    $imageResult = $whatsApp->sendImage($normalizedPhone, $dedicatedWhatsAppImage);
                    if ($imageResult['success']) {
                        $imagesSent++;
                        // Small delay after image
                        usleep(500000); // 0.5 second delay
                    } else {
                        $imagesFailed++;
                        \Illuminate\Support\Facades\Log::warning('WhatsApp test image failed', [
                            'phone' => $normalizedPhone,
                            'image_url' => $dedicatedWhatsAppImage,
                            'error' => $imageResult['error'] ?? 'Unknown error',
                        ]);
                    }
                } else {
                    // Check if image should have been found but wasn't
                    if ($setting->whatsapp_image_path) {
                        \Illuminate\Support\Facades\Log::warning('WhatsApp image path exists in DB but not used', [
                            'setting_id' => $setting->id,
                            'whatsapp_image_path' => $setting->whatsapp_image_path,
                            'this_whatsappImage' => $this->whatsappImage ? 'set' : 'null',
                            'this_existingPath' => $this->existingWhatsappImagePath,
                        ]);
                    }
                }

                // Send text message
                $result = $whatsApp->send($normalizedPhone, $whatsAppMessage);

                // Then send extracted HTML images if any (only for auto-converted content)
                if ($result['success'] && ! empty($imageUrls)) {
                    // Small delay before sending images
                    usleep(500000); // 0.5 second delay

                    foreach ($imageUrls as $imageUrl) {
                        $imageResult = $whatsApp->sendImage($normalizedPhone, $imageUrl);
                        if ($imageResult['success']) {
                            $imagesSent++;
                        } else {
                            $imagesFailed++;
                        }
                        // Small delay between images
                        usleep(300000); // 0.3 second delay
                    }
                }

                if ($result['success']) {
                    $message = "Mesej WhatsApp ujian telah dihantar ke {$normalizedPhone}";
                    if ($imagesSent > 0) {
                        $message .= " ({$imagesSent} gambar berjaya dihantar)";
                    }
                    if ($imagesFailed > 0) {
                        if ($localDomainWarning) {
                            $message .= " ({$imagesFailed} gambar gagal - URL tempatan (.test) tidak boleh diakses oleh API WhatsApp. Gunakan domain awam atau ngrok untuk ujian gambar.)";
                        } else {
                            $message .= " ({$imagesFailed} gambar gagal)";
                        }
                    }
                    if (! $dedicatedWhatsAppImage && ! empty($setting->whatsapp_image_path)) {
                        $message .= " (Nota: Gambar dalam DB tidak digunakan)";
                    }
                    $this->dispatch('notify',
                        type: $imagesFailed > 0 ? 'warning' : 'success',
                        message: $message,
                    );
                } else {
                    $this->dispatch('notify',
                        type: 'error',
                        message: 'Gagal menghantar mesej WhatsApp: '.($result['error'] ?? 'Unknown error'),
                    );
                }
            } else {
                // Send Email test message
                $attachments = $setting->attachments()->ordered()->get();
                $fileAttachments = $attachments->filter(fn ($a) => ! $a->isImage() || ! $a->embed_in_email);

                // Build HTML content based on template type
                if ($isVisualTemplate) {
                    // Visual template is already HTML
                    $htmlContent = $personalizedContent;
                } else {
                    // Text template - convert to simple HTML
                    $htmlContent = '<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333;">'.
                        nl2br(e($personalizedContent)).
                        '</div>';
                }

                // Add test email footer
                $htmlContent .= '<br><br><div style="border-top: 1px solid #eee; padding-top: 10px; margin-top: 20px;"><em style="color: #999; font-size: 12px;">— Ini adalah e-mel ujian —</em></div>';

                \Illuminate\Support\Facades\Mail::html(
                    $htmlContent,
                    function ($message) use ($user, $personalizedSubject, $fileAttachments) {
                        $message->to($user->email, $user->name)
                            ->subject('[UJIAN] '.$personalizedSubject);

                        // Attach files
                        foreach ($fileAttachments as $file) {
                            if (\Illuminate\Support\Facades\Storage::disk($file->disk)->exists($file->file_path)) {
                                $message->attach($file->full_path, [
                                    'as' => $file->file_name,
                                    'mime' => $file->file_type,
                                ]);
                            }
                        }
                    }
                );

                $templateType = $isVisualTemplate ? 'visual' : 'teks';
                $this->dispatch('notify',
                    type: 'success',
                    message: "E-mel ujian (templat {$templateType}) telah dihantar ke {$user->email}",
                );
            }

            $this->showTestModal = false;
        } catch (\Exception $e) {
            $this->dispatch('notify',
                type: 'error',
                message: 'Gagal menghantar ujian: '.$e->getMessage(),
            );
            $this->showTestModal = false;
        }
    }

    /**
     * Extract image URLs from HTML content.
     */
    private function extractImageUrls(string $html): array
    {
        $imageUrls = [];
        preg_match_all('#<img[^>]+src=["\']([^"\']+)["\'][^>]*>#i', $html, $matches);

        if (! empty($matches[1])) {
            foreach (array_unique($matches[1]) as $url) {
                // Only include valid HTTP/HTTPS URLs (not data: URIs or placeholders)
                if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                    $imageUrls[] = $url;
                }
            }
        }

        return $imageUrls;
    }

    /**
     * Convert HTML to WhatsApp-friendly plain text.
     */
    private function convertHtmlToWhatsAppText(string $html, bool $includeImageUrls = true): string
    {
        // Convert HTML to plain text for WhatsApp
        $text = $html;

        // Extract image URLs before removing tags (for appending later if needed)
        $imageUrls = $includeImageUrls ? $this->extractImageUrls($html) : [];

        // FIRST: Remove non-content sections completely
        // Remove <style>...</style> blocks (CSS)
        $text = preg_replace('#<style[^>]*>.*?</style>#is', '', $text);

        // Remove <script>...</script> blocks (JavaScript)
        $text = preg_replace('#<script[^>]*>.*?</script>#is', '', $text);

        // Remove <head>...</head> section
        $text = preg_replace('#<head[^>]*>.*?</head>#is', '', $text);

        // Remove HTML comments
        $text = preg_replace('#<!--.*?-->#s', '', $text);

        // Remove DOCTYPE, html, body tags but keep content
        $text = preg_replace('#<!DOCTYPE[^>]*>#i', '', $text);
        $text = preg_replace('#</?html[^>]*>#i', '', $text);
        $text = preg_replace('#</?body[^>]*>#i', '', $text);
        $text = preg_replace('#</?table[^>]*>#i', '', $text);
        $text = preg_replace('#</?tr[^>]*>#i', '', $text);
        $text = preg_replace('#</?td[^>]*>#i', ' ', $text);
        $text = preg_replace('#</?th[^>]*>#i', ' ', $text);
        $text = preg_replace('#</?tbody[^>]*>#i', '', $text);
        $text = preg_replace('#</?thead[^>]*>#i', '', $text);

        // Remove meta, link, title tags
        $text = preg_replace('#<meta[^>]*>#i', '', $text);
        $text = preg_replace('#<link[^>]*>#i', '', $text);
        $text = preg_replace('#<title[^>]*>.*?</title>#is', '', $text);

        // NOW: Convert content HTML elements
        // Using # delimiter to avoid Blade compiler issues
        $text = preg_replace('#<br\s*/>#i', "\n", $text);  // <br />
        $text = preg_replace('#<br\s*>#i', "\n", $text);   // <br>
        $text = preg_replace('#</p>#i', "\n\n", $text);
        $text = preg_replace('#</div>#i', "\n", $text);
        $text = preg_replace('#</h[1-6]>#i', "\n\n", $text);
        $text = preg_replace('#<li>#i', "• ", $text);
        $text = preg_replace('#</li>#i', "\n", $text);

        // Bold text: <strong> or <b> -> *text*
        $text = preg_replace('#<(strong|b)>(.*?)</(strong|b)>#is', '*$2*', $text);

        // Italic text: <em> or <i> -> _text_
        $text = preg_replace('#<(em|i)>(.*?)</(em|i)>#is', '_$2_', $text);

        // Links: <a href="url">text</a> -> text (url)
        $text = preg_replace('#<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', '$2 ($1)', $text);

        // Strip remaining HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Clean up whitespace
        $text = preg_replace('#[ \t]+#', ' ', $text);      // Multiple spaces/tabs to single space
        $text = preg_replace('#\n[ \t]+#', "\n", $text);   // Remove leading spaces on lines
        $text = preg_replace('#[ \t]+\n#', "\n", $text);   // Remove trailing spaces on lines
        $text = preg_replace('#\n{3,}#', "\n\n", $text);   // Max 2 newlines
        $text = trim($text);

        // Append image URLs at the end if any were found
        if (! empty($imageUrls)) {
            $text .= "\n\n📷 *Gambar:*";
            foreach ($imageUrls as $url) {
                // Only include if it's a valid URL (not data: URIs or placeholders)
                if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                    $text .= "\n• {$url}";
                }
            }
        }

        return $text;
    }

    public function openVisualBuilder(): void
    {
        if (! $this->editingSettingId) {
            return;
        }

        // Store current setting ID in session and redirect to builder
        session(['editing_notification_setting_id' => $this->editingSettingId]);
        $this->redirect(route('admin.class-notification-builder', ['settingId' => $this->editingSettingId]));
    }

    public function updatedNewAttachments(): void
    {
        $this->validate([
            'newAttachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,ppt,pptx',
        ]);

        foreach ($this->newAttachments as $file) {
            $path = $file->store("notification-attachments/{$this->editingSettingId}", 'public');

            ClassNotificationAttachment::create([
                'class_notification_setting_id' => $this->editingSettingId,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'embed_in_email' => str_starts_with($file->getMimeType(), 'image/'),
            ]);
        }

        $this->attachments = ClassNotificationSetting::find($this->editingSettingId)->attachments;
        $this->newAttachments = [];

        $this->dispatch('notify',
            type: 'success',
            message: 'Lampiran telah dimuat naik',
        );
    }

    public function removeAttachment(int $attachmentId): void
    {
        $attachment = ClassNotificationAttachment::find($attachmentId);

        if ($attachment && $attachment->class_notification_setting_id === $this->editingSettingId) {
            Storage::disk($attachment->disk)->delete($attachment->file_path);
            $attachment->delete();

            $this->attachments = ClassNotificationSetting::find($this->editingSettingId)->attachments;

            $this->dispatch('notify',
                type: 'success',
                message: 'Lampiran telah dipadamkan',
            );
        }
    }

    public function toggleEmbedImage(int $attachmentId): void
    {
        $attachment = ClassNotificationAttachment::find($attachmentId);

        if ($attachment && $attachment->class_notification_setting_id === $this->editingSettingId) {
            $attachment->update(['embed_in_email' => ! $attachment->embed_in_email]);
            $this->attachments = ClassNotificationSetting::find($this->editingSettingId)->attachments;
        }
    }

    public function getEditingSettingProperty(): ?ClassNotificationSetting
    {
        if (! $this->editingSettingId) {
            return null;
        }

        return ClassNotificationSetting::find($this->editingSettingId);
    }
}; ?>

<div class="space-y-6">
    <!-- Auto-Schedule Toggle Section -->
    <flux:card>
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 {{ $autoScheduleEnabled ? 'bg-green-100' : 'bg-gray-100' }} rounded-xl flex items-center justify-center transition-colors">
                        @if($autoScheduleEnabled)
                            <flux:icon.bolt class="w-6 h-6 text-green-600" />
                        @else
                            <flux:icon.hand-raised class="w-6 h-6 text-gray-500" />
                        @endif
                    </div>
                    <div>
                        <flux:heading size="lg">Mod Penjadualan Notifikasi</flux:heading>
                        <flux:text class="text-gray-500 mt-1">
                            @if($autoScheduleEnabled)
                                <span class="text-green-600 font-medium">Automatik</span> — Sistem akan menjadualkan notifikasi secara automatik setiap hari untuk 7 hari akan datang.
                            @else
                                <span class="text-gray-600 font-medium">Manual</span> — Klik butang untuk menjadualkan notifikasi secara manual.
                            @endif
                        </flux:text>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    @if($autoScheduleEnabled)
                        <flux:badge color="green" size="lg">
                            <flux:icon.check-circle class="w-4 h-4 mr-1" />
                            Automatik
                        </flux:badge>
                    @else
                        <flux:badge color="zinc" size="lg">Manual</flux:badge>
                    @endif
                    <flux:switch
                        wire:click="toggleAutoSchedule"
                        :checked="$autoScheduleEnabled"
                    />
                </div>
            </div>

            @if($autoScheduleEnabled)
                <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-start gap-3">
                        <flux:icon.information-circle class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
                        <div>
                            <p class="text-sm font-medium text-green-800">Penjadualan Automatik Aktif</p>
                            <p class="text-sm text-green-700 mt-1">
                                Sistem akan menjadualkan notifikasi secara automatik setiap hari pada jam 00:30 untuk 7 hari akan datang.
                                Pastikan tetapan notifikasi di bawah telah diaktifkan.
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </flux:card>

    <!-- Quick Actions Section (Manual Scheduling) -->
    <flux:card>
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:heading size="lg">Jadualkan Notifikasi</flux:heading>
                    <flux:text class="text-gray-500 mt-1">
                        Jadualkan notifikasi berdasarkan jadual waktu kelas (7 hari akan datang).
                    </flux:text>
                </div>
                @if(!$autoScheduleEnabled)
                    <flux:button
                        variant="primary"
                        wire:click="scheduleAllUpcomingNotifications"
                        wire:loading.attr="disabled"
                        icon="bell-alert"
                    >
                        <span wire:loading.remove wire:target="scheduleAllUpcomingNotifications">Jadualkan Semua</span>
                        <span wire:loading wire:target="scheduleAllUpcomingNotifications">Menjadualkan...</span>
                    </flux:button>
                @else
                    <flux:badge color="green" size="lg">
                        <flux:icon.bolt class="w-4 h-4 mr-1" />
                        Dijadualkan Automatik
                    </flux:badge>
                @endif
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
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-100 text-green-700 text-sm font-medium rounded-lg">
                                            <flux:icon.check-circle class="w-4 h-4" />
                                            Telah Dijadualkan
                                        </span>
                                        <flux:button
                                            variant="danger"
                                            size="xs"
                                            wire:click="cancelScheduledNotificationsForSlot('{{ $slot['session_date'] }}', '{{ $slot['session_time'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="cancelScheduledNotificationsForSlot('{{ $slot['session_date'] }}', '{{ $slot['session_time'] }}')"
                                            wire:confirm="Adakah anda pasti untuk membatalkan {{ $pendingCount }} notifikasi yang dijadualkan untuk slot ini?"
                                            title="Batalkan Notifikasi"
                                        >
                                            <span wire:loading.remove wire:target="cancelScheduledNotificationsForSlot('{{ $slot['session_date'] }}', '{{ $slot['session_time'] }}')">
                                                <flux:icon.x-mark class="w-3.5 h-3.5" />
                                            </span>
                                            <span wire:loading wire:target="cancelScheduledNotificationsForSlot('{{ $slot['session_date'] }}', '{{ $slot['session_time'] }}')">
                                                <flux:icon.arrow-path class="w-3.5 h-3.5 animate-spin" />
                                            </span>
                                        </flux:button>
                                    </div>
                                @elseif($autoScheduleEnabled)
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 text-blue-600 text-sm font-medium rounded-lg">
                                        <flux:icon.bolt class="w-4 h-4" />
                                        Auto
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

            <!-- Channel Sub-tabs -->
            <div class="flex border-b border-gray-200 mb-4">
                <button
                    type="button"
                    wire:click="setActiveMainTab('email')"
                    class="flex-1 px-4 py-3 text-sm font-medium flex items-center justify-center gap-2 border-b-2 transition-colors {{ $activeMainTab === 'email' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}"
                >
                    <flux:icon.envelope class="w-4 h-4" />
                    E-mel
                    @php
                        $emailEnabledCount = $this->settings->where('is_enabled', true)->where('email_enabled', true)->count();
                    @endphp
                    @if($emailEnabledCount > 0)
                        <flux:badge color="blue" size="sm">{{ $emailEnabledCount }}</flux:badge>
                    @endif
                </button>
                <button
                    type="button"
                    wire:click="setActiveMainTab('whatsapp')"
                    class="flex-1 px-4 py-3 text-sm font-medium flex items-center justify-center gap-2 border-b-2 transition-colors {{ $activeMainTab === 'whatsapp' ? 'border-green-500 text-green-600 bg-green-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50' }}"
                >
                    <flux:icon.device-phone-mobile class="w-4 h-4" />
                    WhatsApp
                    @php
                        $whatsappEnabledCount = $this->settings->where('is_enabled', true)->where('whatsapp_enabled', true)->count();
                    @endphp
                    @if($whatsappEnabledCount > 0)
                        <flux:badge color="green" size="sm">{{ $whatsappEnabledCount }}</flux:badge>
                    @endif
                </button>
            </div>

            <!-- Global Channel Toggle -->
            @if($activeMainTab === 'email')
                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg mb-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <flux:icon.envelope class="w-5 h-5 text-blue-600" />
                            <div>
                                <p class="font-medium text-blue-900">Aktifkan Saluran E-mel</p>
                                <p class="text-sm text-blue-700">Kawalan utama untuk semua notifikasi e-mel kelas ini</p>
                            </div>
                        </div>
                        <flux:switch
                            wire:model.live="emailChannelEnabled"
                        />
                    </div>
                    @if(!$emailChannelEnabled)
                        <div class="mt-3 p-2 bg-blue-100 rounded text-sm text-blue-800">
                            <flux:icon.information-circle class="w-4 h-4 inline mr-1" />
                            Semua notifikasi e-mel dinyahaktifkan. Aktifkan suis di atas untuk menghantar notifikasi e-mel.
                        </div>
                    @endif
                </div>
            @else
                <div class="p-4 bg-green-50 border border-green-200 rounded-lg mb-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <flux:icon.device-phone-mobile class="w-5 h-5 text-green-600" />
                            <div>
                                <p class="font-medium text-green-900">Aktifkan Saluran WhatsApp</p>
                                <p class="text-sm text-green-700">Kawalan utama untuk semua notifikasi WhatsApp kelas ini</p>
                            </div>
                        </div>
                        <flux:switch
                            wire:model.live="whatsappChannelEnabled"
                        />
                    </div>
                    @if(!$whatsappChannelEnabled)
                        <div class="mt-3 p-2 bg-green-100 rounded text-sm text-green-800">
                            <flux:icon.information-circle class="w-4 h-4 inline mr-1" />
                            Semua notifikasi WhatsApp dinyahaktifkan. Aktifkan suis di atas untuk menghantar notifikasi WhatsApp.
                        </div>
                    @endif
                </div>
            @endif

            <div class="space-y-4">
                @php $typeLabels = $this->getTypeLabels(); @endphp
                @foreach($this->settings as $setting)
                    @php
                        $label = $typeLabels[$setting->notification_type] ?? ['name' => $setting->notification_type, 'description' => ''];
                        $readiness = $setting->template_readiness;
                        // Determine if each channel is enabled for this notification
                        // Channels are independent - use channel-specific flags
                        // For backward compatibility: if email_enabled is null, check is_enabled (legacy behavior)
                        $isWhatsappEnabled = $setting->whatsapp_enabled ?? false;
                        $isEmailEnabled = $setting->email_enabled ?? ($setting->is_enabled && !$isWhatsappEnabled);
                        $currentChannelEnabled = $activeMainTab === 'email' ? $isEmailEnabled : $isWhatsappEnabled;
                    @endphp
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors {{ !$currentChannelEnabled ? 'opacity-60' : '' }}">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-medium text-gray-900">{{ $label['name'] }}</p>
                                @if($activeMainTab === 'email')
                                    @if($isEmailEnabled)
                                        <flux:badge color="blue" size="sm">
                                            <flux:icon.envelope class="w-3 h-3 mr-0.5" />
                                            Aktif
                                        </flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">Tidak Aktif</flux:badge>
                                    @endif
                                @else
                                    @if($isWhatsappEnabled)
                                        <flux:badge color="green" size="sm">
                                            <flux:icon.device-phone-mobile class="w-3 h-3 mr-0.5" />
                                            Aktif
                                        </flux:badge>
                                        @if($setting->hasCustomWhatsAppTemplate())
                                            <flux:badge color="emerald" size="sm" title="Templat WhatsApp tersuai">
                                                <flux:icon.document-check class="w-3 h-3 mr-0.5" />
                                                Tersuai
                                            </flux:badge>
                                        @endif
                                    @else
                                        <flux:badge color="zinc" size="sm">Tidak Aktif</flux:badge>
                                    @endif
                                @endif
                                @if($currentChannelEnabled && $readiness['ready'])
                                    <flux:badge color="sky" size="sm" title="Templat sedia untuk penghantaran">
                                        <flux:icon.check-circle class="w-3 h-3 mr-0.5" />
                                        Sedia
                                        </flux:badge>
                                @elseif($currentChannelEnabled && !$readiness['ready'])
                                    <flux:badge color="amber" size="sm" title="{{ implode(', ', $readiness['issues']) }}">
                                        <flux:icon.exclamation-triangle class="w-3 h-3 mr-0.5" />
                                        {{ implode(', ', $readiness['issues']) }}
                                    </flux:badge>
                                @endif
                            </div>
                            <p class="text-sm text-gray-500 mt-1">{{ $label['description'] }}</p>

                            {{-- Channel-specific template info --}}
                            @if($activeMainTab === 'email')
                                @if($setting->template)
                                    <p class="text-xs text-gray-400 mt-1">
                                        <flux:icon.document-text class="w-3 h-3 inline mr-0.5" />
                                        Templat: {{ $setting->template->name }}
                                        @if($readiness['source'] === 'custom_visual')
                                            <span class="text-blue-600">(Templat visual tersuai)</span>
                                        @elseif($readiness['source'] === 'custom_text')
                                            <span class="text-blue-600">(Templat teks tersuai)</span>
                                        @endif
                                    </p>
                                @elseif($setting->hasCustomTemplate())
                                    <p class="text-xs text-blue-600 mt-1">
                                        <flux:icon.document-text class="w-3 h-3 inline mr-0.5" />
                                        @if($readiness['source'] === 'custom_visual')
                                            Templat visual tersuai
                                        @else
                                            Templat teks tersuai
                                        @endif
                                    </p>
                                @elseif($isEmailEnabled && !$readiness['ready'])
                                    <p class="text-xs text-amber-600 mt-1">
                                        <flux:icon.exclamation-circle class="w-3 h-3 inline mr-0.5" />
                                        Sila tetapkan templat atau kandungan tersuai
                                    </p>
                                @endif
                            @else
                                {{-- WhatsApp tab --}}
                                @if($setting->hasCustomWhatsAppTemplate())
                                    <p class="text-xs text-green-600 mt-1">
                                        <flux:icon.document-text class="w-3 h-3 inline mr-0.5" />
                                        Templat WhatsApp tersuai
                                        @if($setting->hasWhatsAppImage())
                                            <flux:icon.photo class="w-3 h-3 inline ml-2" />
                                            dengan gambar
                                        @endif
                                    </p>
                                @elseif($isWhatsappEnabled)
                                    <p class="text-xs text-gray-400 mt-1">
                                        <flux:icon.arrow-path class="w-3 h-3 inline mr-0.5" />
                                        Auto-tukar dari templat e-mel
                                    </p>
                                @endif
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
                                @if($activeMainTab === 'whatsapp')
                                    <flux:button
                                        variant="outline"
                                        size="sm"
                                        wire:click="openTestModal({{ $setting->id }}, 'whatsapp')"
                                        wire:loading.attr="disabled"
                                        title="Hantar mesej WhatsApp ujian"
                                    >
                                        <flux:icon.paper-airplane class="w-4 h-4" />
                                    </flux:button>
                                    <flux:button
                                        variant="outline"
                                        size="sm"
                                        wire:click="editWhatsappTemplate({{ $setting->id }})"
                                        title="Edit templat WhatsApp"
                                    >
                                        <flux:icon.pencil class="w-4 h-4" />
                                    </flux:button>
                                    <flux:switch
                                        x-on:change="$wire.toggleWhatsappSetting({{ $setting->id }}, $event.target.checked)"
                                        :checked="$isWhatsappEnabled"
                                        title="Aktif/Nyahaktif WhatsApp"
                                    />
                                @else
                                    <flux:button
                                        variant="outline"
                                        size="sm"
                                        wire:click="openTestModal({{ $setting->id }}, 'email')"
                                        wire:loading.attr="disabled"
                                        title="Hantar e-mel ujian"
                                    >
                                        <flux:icon.paper-airplane class="w-4 h-4" />
                                    </flux:button>
                                    <flux:button
                                        variant="outline"
                                        size="sm"
                                        wire:click="editSetting({{ $setting->id }})"
                                        title="Edit tetapan e-mel"
                                    >
                                        <flux:icon.pencil class="w-4 h-4" />
                                    </flux:button>
                                    <flux:switch
                                        x-on:change="$wire.toggleEmailSetting({{ $setting->id }}, $event.target.checked)"
                                        :checked="$isEmailEnabled"
                                        title="Aktif/Nyahaktif E-mel"
                                    />
                                @endif
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

            <!-- History Channel Tabs -->
            <div class="flex border-b border-gray-200 mb-4">
                <button
                    type="button"
                    wire:click="setActiveMainTab('email')"
                    class="flex-1 px-4 py-2 text-sm font-medium flex items-center justify-center gap-2 border-b-2 transition-colors {{ $activeMainTab === 'email' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                >
                    <flux:icon.envelope class="w-4 h-4" />
                    E-mel
                </button>
                <button
                    type="button"
                    wire:click="setActiveMainTab('whatsapp')"
                    class="flex-1 px-4 py-2 text-sm font-medium flex items-center justify-center gap-2 border-b-2 transition-colors {{ $activeMainTab === 'whatsapp' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                >
                    <flux:icon.device-phone-mobile class="w-4 h-4" />
                    WhatsApp
                </button>
            </div>

            @php $groupedHistory = $this->filteredNotificationHistory; @endphp

            @if(count($groupedHistory) > 0)
                <div class="space-y-4">
                    @foreach($groupedHistory as $slotKey => $group)
                        <div x-data="{ expanded: false }" class="border border-gray-200 rounded-lg overflow-hidden">
                            <!-- Session Slot Header (Clickable) -->
                            <button
                                type="button"
                                @click="expanded = !expanded"
                                class="w-full px-4 py-3 transition-colors cursor-pointer {{ $activeMainTab === 'email' ? 'bg-gradient-to-r from-blue-50 to-indigo-50 hover:from-blue-100 hover:to-indigo-100' : 'bg-gradient-to-r from-green-50 to-emerald-50 hover:from-green-100 hover:to-emerald-100' }}"
                                :class="expanded ? 'border-b border-gray-200' : ''"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center {{ $activeMainTab === 'email' ? 'bg-blue-100' : 'bg-green-100' }}">
                                        @if($activeMainTab === 'email')
                                            <flux:icon.envelope class="w-5 h-5 text-blue-600" />
                                        @else
                                            <flux:icon.device-phone-mobile class="w-5 h-5 text-green-600" />
                                        @endif
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
                                        // Group logs by channel
                                        $emailLogs = $notification->logs->where('channel', 'email');
                                        $whatsappLogs = $notification->logs->where('channel', 'whatsapp');
                                        $emailSent = $emailLogs->whereIn('status', ['sent', 'delivered'])->count();
                                        $emailFailed = $emailLogs->where('status', 'failed')->count();
                                        $whatsappSent = $whatsappLogs->whereIn('status', ['sent', 'delivered'])->count();
                                        $whatsappFailed = $whatsappLogs->where('status', 'failed')->count();
                                        $hasLogs = $notification->logs->isNotEmpty();
                                    @endphp
                                    <div x-data="{ showDetails: false }" class="px-4 py-3 hover:bg-gray-50 transition-colors">
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
                                                    @if($notification->failure_reason)
                                                        <p class="text-xs text-red-600 mt-1">
                                                            <flux:icon.exclamation-circle class="w-3 h-3 inline" />
                                                            {{ $notification->failure_reason }}
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>

                                            <!-- Status and Recipients -->
                                            <div class="flex items-center gap-4">
                                                <!-- Channel Breakdown -->
                                                <div class="flex items-center gap-2">
                                                    <!-- Email Status -->
                                                    @if($emailLogs->isNotEmpty() || $notification->setting?->template_id || $notification->setting?->html_content)
                                                        <div class="flex items-center gap-1" title="E-mel: {{ $emailSent }} dihantar, {{ $emailFailed }} gagal">
                                                            <flux:icon.envelope class="w-4 h-4 {{ $emailFailed > 0 ? 'text-red-500' : ($emailSent > 0 ? 'text-green-500' : 'text-gray-400') }}" />
                                                            @if($hasLogs)
                                                                <span class="text-xs {{ $emailFailed > 0 ? 'text-red-600' : 'text-green-600' }}">{{ $emailSent }}/{{ $emailLogs->count() }}</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    <!-- WhatsApp Status -->
                                                    @if($notification->setting?->whatsapp_enabled)
                                                        <div class="flex items-center gap-1" title="WhatsApp: {{ $whatsappSent }} dihantar, {{ $whatsappFailed }} gagal">
                                                            <svg class="w-4 h-4 {{ $whatsappFailed > 0 ? 'text-red-500' : ($whatsappSent > 0 ? 'text-green-500' : 'text-gray-400') }}" viewBox="0 0 24 24" fill="currentColor">
                                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                                            </svg>
                                                            @if($whatsappLogs->isNotEmpty())
                                                                <span class="text-xs {{ $whatsappFailed > 0 ? 'text-red-600' : 'text-green-600' }}">{{ $whatsappSent }}/{{ $whatsappLogs->count() }}</span>
                                                            @else
                                                                <span class="text-xs text-gray-400">-</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>

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

                                                <!-- Expand Details / Cancel Action -->
                                                <div class="flex items-center gap-1">
                                                    @if($hasLogs)
                                                        <button
                                                            @click="showDetails = !showDetails"
                                                            class="p-1 text-gray-400 hover:text-gray-600 transition-colors"
                                                            title="Lihat butiran"
                                                        >
                                                            <flux:icon.chevron-down class="w-4 h-4 transition-transform" ::class="showDetails ? 'rotate-180' : ''" />
                                                        </button>
                                                    @endif
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

                                        <!-- Expandable Log Details -->
                                        <div x-show="showDetails" x-collapse class="mt-3 ml-11">
                                            <div class="bg-gray-50 rounded-lg p-3 space-y-2">
                                                <p class="text-xs font-medium text-gray-700 mb-2">Log Penghantaran:</p>
                                                @forelse($notification->logs as $log)
                                                    <div class="flex items-center justify-between text-xs bg-white rounded px-3 py-2 border border-gray-100">
                                                        <div class="flex items-center gap-2">
                                                            <!-- Channel Icon -->
                                                            @if($log->channel === 'email')
                                                                <flux:icon.envelope class="w-3.5 h-3.5 text-blue-500" />
                                                            @elseif($log->channel === 'whatsapp')
                                                                <svg class="w-3.5 h-3.5 text-green-500" viewBox="0 0 24 24" fill="currentColor">
                                                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                                                </svg>
                                                            @else
                                                                <flux:icon.device-phone-mobile class="w-3.5 h-3.5 text-purple-500" />
                                                            @endif
                                                            <!-- Recipient Info -->
                                                            <span class="text-gray-600">
                                                                {{ $log->recipient_type === 'teacher' ? 'Guru' : 'Pelajar' }}:
                                                            </span>
                                                            <span class="font-medium text-gray-800">{{ $log->destination }}</span>
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <!-- Status -->
                                                            <flux:badge :color="$log->status_badge_color" size="sm">
                                                                {{ $log->status_label }}
                                                            </flux:badge>
                                                            <!-- Sent Time -->
                                                            @if($log->sent_at)
                                                                <span class="text-gray-400">{{ $log->sent_at->format('H:i') }}</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @if($log->error_message)
                                                        <div class="text-xs text-red-600 bg-red-50 rounded px-3 py-1.5 ml-5">
                                                            <flux:icon.exclamation-triangle class="w-3 h-3 inline mr-1" />
                                                            {{ $log->error_message }}
                                                        </div>
                                                    @endif
                                                @empty
                                                    <p class="text-xs text-gray-500 italic">Tiada log penghantaran.</p>
                                                @endforelse
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
                    @if($activeMainTab === 'email')
                        <flux:icon.envelope class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                        <p class="text-gray-700 font-medium">Tiada notifikasi e-mel dijadualkan lagi.</p>
                        <p class="text-sm text-gray-500 mt-1">Aktifkan tetapan notifikasi e-mel di atas untuk mula menghantar notifikasi.</p>
                    @else
                        <flux:icon.device-phone-mobile class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                        <p class="text-gray-700 font-medium">Tiada notifikasi WhatsApp dijadualkan lagi.</p>
                        <p class="text-sm text-gray-500 mt-1">Aktifkan tetapan notifikasi WhatsApp di atas untuk mula menghantar notifikasi.</p>
                    @endif
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
            showPlaceholders: false,
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
                <!-- STEP 1: Content Source Selection -->
                <div class="bg-gradient-to-br from-slate-50 to-gray-50 border border-gray-200 rounded-xl p-5">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <flux:icon.document-text class="w-4 h-4 text-blue-600" />
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900 text-sm">Sumber Kandungan E-mel</p>
                            <p class="text-xs text-gray-500">Pilih sama ada menggunakan templat sistem atau kandungan tersuai</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- System Template Option -->
                        <label class="relative flex flex-col p-4 border-2 rounded-xl cursor-pointer transition-all hover:shadow-md {{ $contentSource === 'system' ? 'border-blue-500 bg-blue-50 shadow-md' : 'border-gray-200 hover:border-blue-300' }}">
                            <input type="radio" name="content_source" wire:model.live="contentSource" value="system" class="sr-only">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-10 h-10 {{ $contentSource === 'system' ? 'bg-blue-100' : 'bg-gray-100' }} rounded-lg flex items-center justify-center">
                                    <flux:icon.rectangle-stack class="w-5 h-5 {{ $contentSource === 'system' ? 'text-blue-600' : 'text-gray-500' }}" />
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">Templat Sistem</p>
                                    <p class="text-xs {{ $contentSource === 'system' ? 'text-blue-600' : 'text-gray-500' }}">Guna templat yang telah disediakan</p>
                                </div>
                            </div>
                            @if($contentSource === 'system')
                                <div class="absolute top-2 right-2">
                                    <flux:icon.check-circle class="w-5 h-5 text-blue-600" />
                                </div>
                            @endif
                        </label>

                        <!-- Custom Content Option -->
                        <label class="relative flex flex-col p-4 border-2 rounded-xl cursor-pointer transition-all hover:shadow-md {{ $contentSource === 'custom' ? 'border-amber-500 bg-amber-50 shadow-md' : 'border-gray-200 hover:border-amber-300' }}">
                            <input type="radio" name="content_source" wire:model.live="contentSource" value="custom" class="sr-only">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-10 h-10 {{ $contentSource === 'custom' ? 'bg-amber-100' : 'bg-gray-100' }} rounded-lg flex items-center justify-center">
                                    <flux:icon.pencil-square class="w-5 h-5 {{ $contentSource === 'custom' ? 'text-amber-600' : 'text-gray-500' }}" />
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">Kandungan Tersuai</p>
                                    <p class="text-xs {{ $contentSource === 'custom' ? 'text-amber-600' : 'text-gray-500' }}">Cipta templat khusus untuk kelas ini</p>
                                </div>
                            </div>
                            @if($contentSource === 'custom')
                                <div class="absolute top-2 right-2">
                                    <flux:icon.check-circle class="w-5 h-5 text-amber-600" />
                                </div>
                            @endif
                        </label>
                    </div>
                </div>

                <!-- STEP 2A: System Template Selection (shown when system is selected) -->
                @if($contentSource === 'system')
                    <div class="border border-blue-200 rounded-xl overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-4 py-3 border-b border-blue-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <flux:icon.rectangle-stack class="w-4 h-4 text-blue-600" />
                                    <span class="font-semibold text-gray-900 text-sm">Pilih Templat Sistem</span>
                                </div>
                                @if($this->selectedTemplate)
                                    <a
                                        href="{{ route('admin.settings.notifications.builder', $this->selectedTemplate) }}"
                                        target="_blank"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition-colors"
                                    >
                                        <flux:icon.arrow-top-right-on-square class="w-3.5 h-3.5" />
                                        Edit Templat
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="bg-white dark:bg-zinc-800 p-4 space-y-4">
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

                            <!-- Template Preview -->
                            @if($this->selectedTemplate)
                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-medium text-gray-600">Pratonton</span>
                                            @if($this->selectedTemplate->isVisualEditor())
                                                <flux:badge color="purple" size="sm">
                                                    <flux:icon.paint-brush class="w-3 h-3 mr-1" />
                                                    Visual
                                                </flux:badge>
                                            @else
                                                <flux:badge color="zinc" size="sm">
                                                    <flux:icon.code-bracket class="w-3 h-3 mr-1" />
                                                    Teks
                                                </flux:badge>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="p-3 space-y-3">
                                        @if($this->selectedTemplate->subject)
                                            <div>
                                                <label class="block text-xs text-gray-500 mb-1">Subjek</label>
                                                <div class="bg-gray-50 rounded px-3 py-2 text-sm text-gray-900">
                                                    {{ $this->selectedTemplate->subject }}
                                                </div>
                                            </div>
                                        @endif

                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">Kandungan</label>
                                            @if($this->selectedTemplate->isVisualEditor())
                                                <div class="bg-gradient-to-br from-purple-50 to-indigo-50 border border-purple-100 rounded-lg p-3 text-center">
                                                    <flux:icon.paint-brush class="w-6 h-6 mx-auto text-purple-400 mb-1" />
                                                    <p class="text-xs text-purple-700">Templat visual - klik "Edit Templat" untuk melihat</p>
                                                </div>
                                            @else
                                                <div class="bg-gray-50 rounded p-2 max-h-32 overflow-y-auto">
                                                    <div class="text-xs text-gray-700 whitespace-pre-wrap">{{ Str::limit($this->selectedTemplate->content, 300) }}</div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="border border-dashed border-gray-300 rounded-lg p-6 text-center bg-gray-50/50">
                                    <flux:icon.document class="w-8 h-8 mx-auto text-gray-300 mb-2" />
                                    <p class="text-gray-500 text-sm">Pilih templat untuk melihat pratonton</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- STEP 2B: Custom Content Editor (shown when custom is selected) -->
                @if($contentSource === 'custom')
                    <div class="border border-amber-200 rounded-xl overflow-hidden">
                        <div class="bg-gradient-to-r from-amber-50 to-yellow-50 px-4 py-3 border-b border-amber-200">
                            <div class="flex items-center gap-2">
                                <flux:icon.pencil-square class="w-4 h-4 text-amber-600" />
                                <span class="font-semibold text-gray-900 text-sm">Kandungan Tersuai</span>
                            </div>
                            <p class="text-xs text-amber-700 mt-1">Templat ini akan digunakan khusus untuk kelas ini sahaja</p>
                        </div>
                        <div class="bg-white dark:bg-zinc-800 p-4 space-y-4">
                            <!-- Editor Type Selection -->
                            <div>
                                <flux:label class="mb-2">Jenis Editor</flux:label>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="flex items-center gap-3 p-3 border-2 rounded-lg cursor-pointer hover:border-amber-300 transition-colors {{ $editorType === 'text' ? 'border-amber-500 bg-amber-50' : 'border-gray-200' }}">
                                        <input type="radio" name="editor_type" wire:model.live="editorType" value="text" class="w-4 h-4 text-amber-600 border-gray-300 focus:ring-amber-500">
                                        <div>
                                            <p class="font-medium text-gray-900 text-sm">Teks Biasa</p>
                                            <p class="text-xs text-gray-500">Editor teks dengan placeholder</p>
                                        </div>
                                    </label>
                                    <label class="flex items-center gap-3 p-3 border-2 rounded-lg cursor-pointer hover:border-purple-300 transition-colors {{ $editorType === 'visual' ? 'border-purple-500 bg-purple-50' : 'border-gray-200' }}">
                                        <input type="radio" name="editor_type" wire:model.live="editorType" value="visual" class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                                        <div>
                                            <p class="font-medium text-gray-900 text-sm">Visual Builder</p>
                                            <p class="text-xs text-gray-500">Reka bentuk email dengan gambar</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            @if($editorType === 'visual')
                                <!-- Visual Builder Section -->
                                <div class="space-y-4">
                                    <!-- Subject field for visual editor -->
                                    <flux:field>
                                        <flux:label>Subjek E-mel</flux:label>
                                        <flux:input wire:model="customSubject" placeholder="Contoh: Peringatan Kelas @{{class_name}}" />
                                        <flux:description>Subjek e-mel akan digunakan untuk templat visual anda.</flux:description>
                                    </flux:field>

                                    <div class="p-4 border border-purple-200 rounded-lg bg-gradient-to-br from-purple-50 to-indigo-50">
                                        <div class="flex items-center justify-between mb-3">
                                            <div>
                                                <p class="font-semibold text-gray-900 text-sm">Templat Visual</p>
                                                <p class="text-xs text-purple-600">Reka bentuk email dengan gambar dan susun atur tersuai</p>
                                            </div>
                                            <flux:button
                                                wire:click="openVisualBuilder"
                                                variant="primary"
                                                size="sm"
                                                icon="pencil-square"
                                            >
                                                {{ $hasVisualContent ? 'Edit Reka Bentuk' : 'Cipta Reka Bentuk' }}
                                            </flux:button>
                                        </div>

                                    @if($hasVisualContent)
                                        <div class="p-3 bg-white rounded border border-purple-100">
                                            <div class="flex items-center gap-2">
                                                <flux:badge color="green" size="sm">Templat Disimpan</flux:badge>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1">Templat visual telah dikonfigurasi untuk notifikasi ini.</p>
                                        </div>
                                    @else
                                        <div class="p-3 bg-white rounded border border-purple-100 text-center">
                                            <flux:icon.paint-brush class="w-6 h-6 mx-auto text-purple-300 mb-1" />
                                            <p class="text-xs text-gray-500">Klik butang untuk mula mereka bentuk templat</p>
                                        </div>
                                    @endif
                                    </div>
                                </div>
                            @else
                                <!-- Text Editor Section -->
                                <div class="space-y-4">
                                    <flux:field>
                                        <flux:label>Subjek E-mel</flux:label>
                                        <flux:input wire:model="customSubject" placeholder="Contoh: Peringatan Kelas @{{class_name}}" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>Kandungan E-mel</flux:label>
                                        <flux:textarea wire:model="customContent" rows="5" placeholder="Tulis kandungan e-mel anda di sini. Gunakan placeholder seperti @{{student_name}} untuk personalisasi." />
                                    </flux:field>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Placeholder Reference (Collapsible) -->
                    <div class="border border-blue-200 rounded-xl overflow-hidden">
                        <button
                            type="button"
                            @click="showPlaceholders = !showPlaceholders"
                            class="w-full bg-gradient-to-r from-blue-50 to-indigo-50 px-4 py-3 flex items-center justify-between hover:from-blue-100 hover:to-indigo-100 transition-colors"
                        >
                            <div class="flex items-center gap-2">
                                <flux:icon.code-bracket class="w-4 h-4 text-blue-600" />
                                <span class="font-semibold text-gray-900 text-sm">Placeholder</span>
                                <span class="text-xs text-blue-600">(Klik untuk menyalin)</span>
                            </div>
                            <flux:icon.chevron-down
                                class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                ::class="showPlaceholders ? 'rotate-180' : ''"
                            />
                        </button>
                        <div x-show="showPlaceholders" x-collapse class="bg-white p-4">
                            <div class="flex flex-wrap gap-2">
                                @foreach($this->availablePlaceholders as $placeholder => $description)
                                    <button
                                        type="button"
                                        x-on:click="copyToClipboard('{{ $placeholder }}')"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 hover:border-blue-300 transition-all duration-150 cursor-pointer group"
                                        title="{{ $description }}"
                                    >
                                        <code class="text-xs text-blue-700 font-mono">{{ $placeholder }}</code>
                                        <flux:icon.clipboard-document class="w-3 h-3 text-blue-400 group-hover:text-blue-600 transition-colors" />
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Attachments Section (Always shown) -->
                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <div class="bg-gradient-to-r from-gray-50 to-slate-50 px-4 py-3 border-b border-gray-200">
                        <div class="flex items-center gap-2">
                            <flux:icon.paper-clip class="w-4 h-4 text-gray-600" />
                            <span class="font-semibold text-gray-900 text-sm">Lampiran</span>
                            <span class="text-xs text-gray-500">(Pilihan)</span>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-zinc-800 p-4 space-y-4">
                        <!-- Upload Area -->
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-gray-400 transition-colors">
                            <input type="file" wire:model="newAttachments" multiple class="hidden" id="attachment-upload-{{ $editingSettingId }}">
                            <label for="attachment-upload-{{ $editingSettingId }}" class="cursor-pointer block">
                                <flux:icon.cloud-arrow-up class="w-8 h-8 mx-auto text-gray-400" />
                                <p class="text-sm text-gray-600 mt-2">Klik untuk muat naik fail</p>
                                <p class="text-xs text-gray-400">JPG, PNG, PDF, DOC, XLS, PPT (Maks 10MB)</p>
                            </label>
                            <div wire:loading wire:target="newAttachments" class="mt-2">
                                <flux:badge color="blue" size="sm">Memuat naik...</flux:badge>
                            </div>
                        </div>

                        <!-- Attachment List -->
                        @if($attachments->count() > 0)
                            <div class="space-y-2">
                                @foreach($attachments as $attachment)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center gap-3">
                                            @if($attachment->isImage())
                                                <img src="{{ $attachment->url }}" class="w-10 h-10 object-cover rounded" alt="{{ $attachment->file_name }}" />
                                            @else
                                                <div class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center">
                                                    <flux:icon :name="$attachment->file_icon" class="w-5 h-5 text-gray-500" />
                                                </div>
                                            @endif
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">{{ Str::limit($attachment->file_name, 30) }}</p>
                                                <p class="text-xs text-gray-500">{{ $attachment->formatted_size }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @if($attachment->isImage())
                                                <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        wire:click="toggleEmbedImage({{ $attachment->id }})"
                                                        {{ $attachment->embed_in_email ? 'checked' : '' }}
                                                        class="w-3.5 h-3.5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                                    >
                                                    Embed
                                                </label>
                                            @endif
                                            <flux:button
                                                wire:click="removeAttachment({{ $attachment->id }})"
                                                wire:confirm="Adakah anda pasti untuk memadam lampiran ini?"
                                                variant="ghost"
                                                size="sm"
                                                class="text-red-600 hover:text-red-800"
                                            >
                                                <flux:icon.trash class="w-4 h-4" />
                                            </flux:button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Email Channel Info Card -->
                <div class="border border-blue-200 rounded-xl overflow-hidden bg-blue-50">
                    <div class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                <flux:icon.envelope class="w-4 h-4 text-blue-600" />
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-gray-900 text-sm">Tetapan E-mel</p>
                                <p class="text-xs text-blue-600">Kandungan e-mel dikonfigurasikan di bahagian "Sumber Kandungan" di atas.</p>
                            </div>
                            <flux:icon.check-circle class="w-5 h-5 text-blue-600" />
                        </div>
                    </div>
                </div>

                <!-- WhatsApp Configuration Note -->
                <div class="border border-green-200 rounded-xl overflow-hidden bg-green-50">
                    <div class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                <flux:icon.device-phone-mobile class="w-4 h-4 text-green-600" />
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-gray-900 text-sm">Tetapan WhatsApp</p>
                                <p class="text-xs text-green-600">Konfigurasi WhatsApp boleh dilakukan melalui tab WhatsApp di halaman utama notifikasi.</p>
                            </div>
                            <flux:icon.arrow-top-right-on-square class="w-5 h-5 text-green-600" />
                        </div>
                    </div>
                </div>

                <!-- Recipients Card (Always shown at bottom) -->
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
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-3 pt-5 mt-5 border-t border-gray-200">
                <flux:button variant="ghost" wire:click="$set('showEditModal', false)">Batal</flux:button>
                <flux:button variant="primary" wire:click="saveSetting" icon="check">Simpan Tetapan</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Test Notification Modal -->
    <flux:modal wire:model="showTestModal" class="max-w-lg">
        <div class="p-6">
            <div class="flex items-center justify-between pb-4 border-b border-gray-200 mb-5">
                <div>
                    <flux:heading size="lg">Hantar Mesej Ujian</flux:heading>
                    <flux:text class="text-gray-500 text-sm mt-1">Pilih saluran untuk menghantar mesej ujian</flux:text>
                </div>
            </div>

            <div class="space-y-4">
                <!-- Channel Selection -->
                <div>
                    <flux:label class="mb-3">Pilih Saluran</flux:label>
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Email Option -->
                        <div
                            wire:click="$set('testChannel', 'email')"
                            class="relative flex flex-col p-5 border-2 rounded-xl cursor-pointer transition-all hover:shadow-md {{ $testChannel === 'email' ? 'border-blue-500 bg-blue-50 shadow-md' : 'border-gray-200 hover:border-blue-300' }}"
                        >
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 {{ $testChannel === 'email' ? 'bg-blue-100' : 'bg-gray-100' }} rounded-xl flex items-center justify-center">
                                    <flux:icon.envelope class="w-6 h-6 {{ $testChannel === 'email' ? 'text-blue-600' : 'text-gray-500' }}" />
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 text-base">E-mel</p>
                                    <p class="text-sm text-gray-500 truncate max-w-[140px]">{{ auth()->user()->email }}</p>
                                </div>
                            </div>
                            @if($testChannel === 'email')
                                <div class="absolute top-3 right-3">
                                    <flux:icon.check-circle class="w-6 h-6 text-blue-600" />
                                </div>
                            @endif
                        </div>

                        <!-- WhatsApp Option -->
                        <div
                            wire:click="$set('testChannel', 'whatsapp')"
                            class="relative flex flex-col p-5 border-2 rounded-xl cursor-pointer transition-all hover:shadow-md {{ $testChannel === 'whatsapp' ? 'border-green-500 bg-green-50 shadow-md' : 'border-gray-200 hover:border-green-300' }}"
                        >
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 {{ $testChannel === 'whatsapp' ? 'bg-green-100' : 'bg-gray-100' }} rounded-xl flex items-center justify-center">
                                    <flux:icon.device-phone-mobile class="w-6 h-6 {{ $testChannel === 'whatsapp' ? 'text-green-600' : 'text-gray-500' }}" />
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 text-base">WhatsApp</p>
                                    <p class="text-sm text-gray-500">{{ auth()->user()->phone_number ?? auth()->user()->phone ?? 'Tidak ditetapkan' }}</p>
                                </div>
                            </div>
                            @if($testChannel === 'whatsapp')
                                <div class="absolute top-3 right-3">
                                    <flux:icon.check-circle class="w-6 h-6 text-green-600" />
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl">
                    <div class="flex items-start gap-3">
                        <flux:icon.information-circle class="w-5 h-5 text-gray-500 flex-shrink-0 mt-0.5" />
                        <div class="text-sm text-gray-600">
                            @if($testChannel === 'email')
                                Mesej ujian akan dihantar ke alamat e-mel anda: <strong>{{ auth()->user()->email }}</strong>
                            @else
                                Mesej ujian akan dihantar ke nombor WhatsApp anda: <strong>{{ auth()->user()->phone_number ?? auth()->user()->phone ?? 'Tidak ditetapkan' }}</strong>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-3 pt-5 mt-5 border-t border-gray-200">
                <flux:button variant="ghost" wire:click="$set('showTestModal', false)">Batal</flux:button>
                <flux:button
                    variant="primary"
                    wire:click="sendTestNotification"
                    wire:loading.attr="disabled"
                    icon="paper-airplane"
                >
                    <span wire:loading.remove wire:target="sendTestNotification">Hantar Ujian</span>
                    <span wire:loading wire:target="sendTestNotification">Menghantar...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- WhatsApp Template Modal -->
    <flux:modal wire:model="showWhatsappModal" class="max-w-5xl">
        <div class="p-6" x-data="waTemplateEditor()"
            x-init="initPreview(@js($this->waPreviewContent))">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-4 border-b border-gray-200 mb-5">
                <div>
                    <flux:heading size="lg">Edit Templat WhatsApp</flux:heading>
                    <flux:text class="text-gray-500 text-sm mt-1">
                        {{ $this->class->title }} - {{ $this->waNotificationTypeLabel['name'] ?? '' }}
                    </flux:text>
                </div>
                <div class="text-sm text-gray-500">
                    <span class="{{ strlen($waTemplateContent) > 4096 ? 'text-red-600 font-semibold' : '' }}">
                        {{ strlen($waTemplateContent) }} / 4,096
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column: Editor -->
                <div class="space-y-4">
                    <!-- Content Editor -->
                    <div>
                        <flux:label class="mb-2">Kandungan Mesej</flux:label>
                        <textarea
                            id="wa-template-content"
                            wire:model.live.debounce.300ms="waTemplateContent"
                            class="w-full h-64 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 resize-none font-mono text-sm"
                            placeholder="Tulis mesej WhatsApp anda di sini..."
                        ></textarea>
                    </div>

                    <!-- Placeholders -->
                    <div>
                        <flux:label class="mb-2">Placeholder (Klik untuk tambah)</flux:label>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="ph in placeholders" :key="ph.key">
                                <button
                                    type="button"
                                    @click="insertPlaceholder(ph.key)"
                                    class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 rounded border border-gray-300 transition-colors"
                                    x-text="ph.key"
                                ></button>
                            </template>
                        </div>
                    </div>

                    <!-- Format Guide -->
                    <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                        <p class="text-sm font-medium text-green-800 mb-2">Panduan Format WhatsApp</p>
                        <div class="grid grid-cols-2 gap-2 text-xs text-green-700">
                            <div><code class="bg-green-100 px-1 rounded">*bold*</code> = <strong>bold</strong></div>
                            <div><code class="bg-green-100 px-1 rounded">_italic_</code> = <em>italic</em></div>
                            <div><code class="bg-green-100 px-1 rounded">~strikethrough~</code> = <del>strikethrough</del></div>
                            <div><code class="bg-green-100 px-1 rounded">```code```</code> = <code>code</code></div>
                        </div>
                    </div>

                    <!-- Image Upload -->
                    <div>
                        <flux:label class="mb-2">Gambar Lampiran</flux:label>
                        @if($waExistingImagePath)
                            <div class="relative inline-block mb-2">
                                <img
                                    src="{{ Storage::disk('public')->url($waExistingImagePath) }}"
                                    alt="WhatsApp Image"
                                    class="w-32 h-32 object-cover rounded-lg border border-gray-200"
                                >
                                <button
                                    type="button"
                                    wire:click="removeWaTemplateImage"
                                    class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-lg"
                                    title="Padam gambar"
                                >
                                    <flux:icon.x-mark class="w-4 h-4" />
                                </button>
                            </div>
                        @endif

                        <div class="flex items-center justify-center w-full">
                            <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <flux:icon.arrow-up-tray class="w-6 h-6 text-gray-400 mb-2" />
                                    <p class="text-xs text-gray-500">
                                        <span class="font-semibold">Klik untuk muat naik</span> atau seret dan lepas
                                    </p>
                                    <p class="text-xs text-gray-400">PNG, JPG sehingga 2MB</p>
                                </div>
                                <input
                                    type="file"
                                    wire:model="waTemplateImage"
                                    accept="image/*"
                                    class="hidden"
                                >
                            </label>
                        </div>
                        @if($waTemplateImage)
                            <div class="mt-2 flex items-center gap-2 text-sm text-green-600">
                                <flux:icon.check-circle class="w-4 h-4" />
                                <span>Gambar baharu dipilih: {{ $waTemplateImage->getClientOriginalName() }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Right Column: Preview -->
                <div>
                    <flux:label class="mb-2">Pratonton Mesej</flux:label>
                    <div class="bg-[#e5ddd5] rounded-lg p-4 min-h-[400px]">
                        <!-- WhatsApp bubble style preview -->
                        <div class="max-w-[85%] ml-auto">
                            @if($waExistingImagePath || $waTemplateImage)
                                <div class="bg-[#dcf8c6] rounded-t-lg rounded-bl-lg p-1 mb-0.5">
                                    @if($waTemplateImage)
                                        <img
                                            src="{{ $waTemplateImage->temporaryUrl() }}"
                                            alt="Preview"
                                            class="w-full rounded-lg"
                                        >
                                    @elseif($waExistingImagePath)
                                        <img
                                            src="{{ Storage::disk('public')->url($waExistingImagePath) }}"
                                            alt="Preview"
                                            class="w-full rounded-lg"
                                        >
                                    @endif
                                </div>
                            @endif

                            <div class="bg-[#dcf8c6] rounded-lg rounded-tr-none p-3 shadow-sm">
                                <div x-show="previewContent" class="text-sm text-gray-800 whitespace-pre-wrap break-words" x-html="formatWhatsAppPreview(previewContent)"></div>
                                <p x-show="!previewContent" class="text-sm text-gray-400 italic">Pratonton mesej akan dipaparkan di sini...</p>
                                <div class="flex items-center justify-end gap-1 mt-1">
                                    <span class="text-xs text-gray-500">{{ now()->format('g:i A') }}</span>
                                    <svg class="w-4 h-4 text-blue-500" viewBox="0 0 16 15" fill="currentColor">
                                        <path d="M15.01 3.316l-.478-.372a.365.365 0 0 0-.51.063L8.666 9.879a.32.32 0 0 1-.484.033l-1.891-1.891a.366.366 0 0 0-.51-.063l-.478.372a.365.365 0 0 0-.063.51l2.67 3.474c.138.18.382.18.52 0l6.52-8.482a.366.366 0 0 0-.063-.51z"/>
                                        <path d="M11.01 3.316l-.478-.372a.365.365 0 0 0-.51.063L4.666 9.879a.32.32 0 0 1-.484.033L2.291 8.021a.366.366 0 0 0-.51-.063l-.478.372a.365.365 0 0 0-.063.51l2.67 3.474c.138.18.382.18.52 0l6.52-8.482a.366.366 0 0 0-.063-.51z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <p class="text-center text-xs text-gray-500 mt-4">
                            Ini adalah pratonton dengan data contoh. Data sebenar akan digunakan semasa penghantaran.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-3 pt-5 mt-5 border-t border-gray-200">
                <flux:button variant="ghost" wire:click="$set('showWhatsappModal', false)">Batal</flux:button>
                <flux:button
                    variant="primary"
                    wire:click="saveWhatsappTemplate"
                    wire:loading.attr="disabled"
                    icon="check"
                    class="!bg-green-600 hover:!bg-green-700"
                >
                    <span wire:loading.remove wire:target="saveWhatsappTemplate">Simpan Templat</span>
                    <span wire:loading wire:target="saveWhatsappTemplate">Menyimpan...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

@verbatim
<script>
    function waTemplateEditor() {
        return {
            previewContent: '',
            placeholders: [
                { key: '{{student_name}}', label: 'Nama Pelajar' },
                { key: '{{teacher_name}}', label: 'Nama Guru' },
                { key: '{{class_name}}', label: 'Nama Kelas' },
                { key: '{{course_name}}', label: 'Nama Kursus' },
                { key: '{{session_date}}', label: 'Tarikh Sesi' },
                { key: '{{session_time}}', label: 'Masa Sesi' },
                { key: '{{session_datetime}}', label: 'Tarikh & Masa' },
                { key: '{{location}}', label: 'Lokasi' },
                { key: '{{meeting_url}}', label: 'URL Mesyuarat' },
                { key: '{{whatsapp_link}}', label: 'Link WhatsApp' },
                { key: '{{duration}}', label: 'Tempoh' },
                { key: '{{remaining_sessions}}', label: 'Sesi Tinggal' },
                { key: '{{total_sessions}}', label: 'Jumlah Sesi' },
                { key: '{{attendance_rate}}', label: 'Kadar Kehadiran' },
            ],
            initPreview(content) {
                this.previewContent = content || '';
                // Watch for Livewire updates
                this.$watch('$wire.waTemplateContent', (value) => {
                    // Update preview when content changes - we need to get the computed property
                    this.$wire.call('getWaPreviewContent').then(result => {
                        this.previewContent = result || '';
                    });
                });
            },
            insertPlaceholder(placeholder) {
                const textarea = document.getElementById('wa-template-content');
                if (!textarea) return;

                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const text = textarea.value;

                textarea.value = text.substring(0, start) + placeholder + text.substring(end);
                textarea.focus();
                textarea.setSelectionRange(start + placeholder.length, start + placeholder.length);

                // Trigger Livewire update
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            },
            formatWhatsAppPreview(text) {
                if (!text) return '';
                return text
                    .replace(/\*([^*]+)\*/g, '<strong>$1</strong>')
                    .replace(/_([^_]+)_/g, '<em>$1</em>')
                    .replace(/~([^~]+)~/g, '<del>$1</del>')
                    .replace(/```([^`]+)```/g, '<code class="bg-gray-200 px-1 rounded text-sm">$1</code>')
                    .replace(/`([^`]+)`/g, '<code class="bg-gray-200 px-0.5 rounded text-sm">$1</code>')
                    .replace(/\n/g, '<br>');
            }
        };
    }

    function formatWhatsAppPreview(text) {
        if (!text) return '';
        return text
            .replace(/\*([^*]+)\*/g, '<strong>$1</strong>')
            .replace(/_([^_]+)_/g, '<em>$1</em>')
            .replace(/~([^~]+)~/g, '<del>$1</del>')
            .replace(/```([^`]+)```/g, '<code class="bg-gray-200 px-1 rounded text-sm">$1</code>')
            .replace(/`([^`]+)`/g, '<code class="bg-gray-200 px-0.5 rounded text-sm">$1</code>')
            .replace(/\n/g, '<br>');
    }
</script>
@endverbatim
