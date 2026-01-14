<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'channel',
        'subject',
        'content',
        'design_json',
        'html_content',
        'editor_type',
        'language',
        'is_system',
        'is_active',
        'available_placeholders',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'available_placeholders' => 'array',
            'design_json' => 'array',
        ];
    }

    public function isVisualEditor(): bool
    {
        return $this->editor_type === 'visual';
    }

    public function isTextEditor(): bool
    {
        return $this->editor_type === 'text';
    }

    public function getEffectiveContent(): string
    {
        if ($this->isVisualEditor() && $this->html_content) {
            return $this->html_content;
        }

        return $this->content ?? '';
    }

    public static function getEditorTypes(): array
    {
        return [
            'text' => 'Teks',
            'visual' => 'Visual Builder',
        ];
    }

    public function classSettings(): HasMany
    {
        return $this->hasMany(ClassNotificationSetting::class, 'template_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeByLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }

    public function scopeEmail($query)
    {
        return $query->where('channel', 'email');
    }

    public function scopeSessionReminder($query)
    {
        return $query->where('type', 'session_reminder');
    }

    public function scopeSessionFollowup($query)
    {
        return $query->where('type', 'session_followup');
    }

    public static function getAvailablePlaceholders(): array
    {
        return [
            '{{student_name}}' => 'Nama penuh pelajar',
            '{{teacher_name}}' => 'Nama penuh guru',
            '{{class_name}}' => 'Tajuk kelas',
            '{{course_name}}' => 'Nama kursus',
            '{{session_date}}' => 'Tarikh sesi (formatted)',
            '{{session_time}}' => 'Masa sesi (formatted)',
            '{{session_datetime}}' => 'Tarikh dan masa penuh',
            '{{location}}' => 'Lokasi fizikal',
            '{{meeting_url}}' => 'URL mesyuarat dalam talian',
            '{{whatsapp_link}}' => 'Pautan kumpulan WhatsApp',
            '{{duration}}' => 'Tempoh sesi',
            '{{remaining_sessions}}' => 'Bilangan sesi yang tinggal',
            '{{total_sessions}}' => 'Jumlah sesi dalam kelas',
            '{{attendance_rate}}' => 'Kadar kehadiran pelajar',
        ];
    }

    public static function getNotificationTypes(): array
    {
        return [
            'session_reminder' => 'Peringatan Sesi',
            'session_followup' => 'Susulan Sesi',
            'class_update' => 'Kemaskini Kelas',
            'enrollment_welcome' => 'Selamat Datang Pendaftaran',
            'class_completed' => 'Kelas Selesai',
        ];
    }

    public static function getChannels(): array
    {
        return [
            'email' => 'E-mel',
            'whatsapp' => 'WhatsApp',
            'sms' => 'SMS',
        ];
    }
}
