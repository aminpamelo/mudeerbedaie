<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassNotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'notification_type',
        'is_enabled',
        'template_id',
        'custom_minutes_before',
        'send_to_students',
        'send_to_teacher',
        'custom_subject',
        'custom_content',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'send_to_students' => 'boolean',
            'send_to_teacher' => 'boolean',
        ];
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    public function scheduledNotifications(): HasMany
    {
        return $this->hasMany(ScheduledNotification::class, 'class_notification_setting_id');
    }

    public function getEffectiveSubject(): ?string
    {
        return $this->custom_subject ?? $this->template?->subject;
    }

    public function getEffectiveContent(): ?string
    {
        return $this->custom_content ?? $this->template?->content;
    }

    public function getMinutesBefore(): int
    {
        return match ($this->notification_type) {
            'session_reminder_24h' => 1440,  // 24 * 60
            'session_reminder_3h' => 180,
            'session_reminder_1h' => 60,
            'session_reminder_30m' => 30,
            'session_reminder_custom' => $this->custom_minutes_before ?? 60,
            default => 0,
        };
    }

    public function getMinutesAfter(): int
    {
        return match ($this->notification_type) {
            'session_followup_immediate' => 0,
            'session_followup_1h' => 60,
            'session_followup_24h' => 1440,
            default => 0,
        };
    }

    public function isReminderType(): bool
    {
        return str_starts_with($this->notification_type, 'session_reminder_');
    }

    public function isFollowupType(): bool
    {
        return str_starts_with($this->notification_type, 'session_followup_');
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeForClass($query, int $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeReminders($query)
    {
        return $query->where('notification_type', 'like', 'session_reminder_%');
    }

    public function scopeFollowups($query)
    {
        return $query->where('notification_type', 'like', 'session_followup_%');
    }

    public static function getNotificationTypeLabels(): array
    {
        return [
            'session_reminder_24h' => [
                'name' => 'Peringatan 24 Jam Sebelum',
                'description' => 'Hantar notifikasi 24 jam sebelum sesi bermula',
            ],
            'session_reminder_3h' => [
                'name' => 'Peringatan 3 Jam Sebelum',
                'description' => 'Hantar notifikasi 3 jam sebelum sesi bermula',
            ],
            'session_reminder_1h' => [
                'name' => 'Peringatan 1 Jam Sebelum',
                'description' => 'Hantar notifikasi 1 jam sebelum sesi bermula',
            ],
            'session_reminder_30m' => [
                'name' => 'Peringatan 30 Minit Sebelum',
                'description' => 'Hantar notifikasi 30 minit sebelum sesi bermula',
            ],
            'session_reminder_custom' => [
                'name' => 'Peringatan Masa Tersuai',
                'description' => 'Hantar notifikasi pada masa tersuai sebelum sesi bermula',
            ],
            'session_followup_immediate' => [
                'name' => 'Susulan Segera Selepas Sesi',
                'description' => 'Hantar notifikasi sejurus selepas sesi tamat',
            ],
            'session_followup_1h' => [
                'name' => 'Susulan 1 Jam Selepas',
                'description' => 'Hantar notifikasi 1 jam selepas sesi tamat',
            ],
            'session_followup_24h' => [
                'name' => 'Susulan 24 Jam Selepas',
                'description' => 'Hantar notifikasi 24 jam selepas sesi tamat',
            ],
            'enrollment_welcome' => [
                'name' => 'Selamat Datang Pendaftaran',
                'description' => 'Hantar notifikasi apabila pelajar didaftarkan ke dalam kelas',
            ],
            'class_completed' => [
                'name' => 'Kelas Selesai',
                'description' => 'Hantar notifikasi apabila kelas selesai sepenuhnya',
            ],
        ];
    }
}
