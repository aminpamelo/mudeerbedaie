<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CekbotBotSetting extends Model
{
    protected $fillable = [
        'cekbot_session_id',
        'bot_enabled',
        'reply_to_groups',
        'checks_enabled',
        'welcome_message',
        'default_reply',
        'away_message',
        'business_hours',
        'ai_enabled',
        'ai_system_prompt',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bot_enabled' => 'boolean',
            'reply_to_groups' => 'boolean',
            'checks_enabled' => 'boolean',
            'ai_enabled' => 'boolean',
            'business_hours' => 'array',
        ];
    }

    /**
     * Whether "now" falls inside configured business hours. Returns true when
     * business hours are not enabled (i.e. always open).
     *
     * business_hours shape: {enabled: bool, start: "09:00", end: "18:00", days: [1..7]}
     * where days use ISO-8601 weekday numbers (1 = Monday … 7 = Sunday).
     */
    public function isWithinBusinessHours(?\Illuminate\Support\Carbon $now = null): bool
    {
        $config = $this->business_hours;

        if (! is_array($config) || empty($config['enabled'])) {
            return true;
        }

        $now ??= now();
        $days = $config['days'] ?? [1, 2, 3, 4, 5];

        if (! in_array((int) $now->isoWeekday(), array_map('intval', $days), true)) {
            return false;
        }

        $start = $config['start'] ?? '09:00';
        $end = $config['end'] ?? '18:00';
        $current = $now->format('H:i');

        return $current >= $start && $current <= $end;
    }

    /**
     * @return BelongsTo<CekbotSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CekbotSession::class, 'cekbot_session_id');
    }
}
