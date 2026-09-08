<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CekbotBotSetting extends Model
{
    protected $fillable = [
        'cekbot_session_id',
        'bot_enabled',
        'test_mode',
        'test_numbers',
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
            'test_mode' => 'boolean',
            'test_numbers' => 'array',
            'reply_to_groups' => 'boolean',
            'checks_enabled' => 'boolean',
            'ai_enabled' => 'boolean',
            'business_hours' => 'array',
        ];
    }

    /**
     * Whether the bot is allowed to reply to the given WhatsApp chat id.
     *
     * When test mode is on, the bot only replies to whitelisted numbers (so it
     * won't blast every customer during testing). With test mode off it replies
     * to everyone.
     */
    public function repliesTo(string $chatId): bool
    {
        if (! $this->test_mode) {
            return true;
        }

        $allowed = collect($this->test_numbers ?? [])
            ->map(fn ($number) => static::normalizePhone((string) $number))
            ->filter()
            ->all();

        if (empty($allowed)) {
            return false;
        }

        return in_array(static::normalizePhone($chatId), $allowed, true);
    }

    /**
     * Reduce a raw phone/chat id to digits only, coercing a leading Malaysian
     * "0" to the "60" country code so "0123456789", "+60 12-345 6789" and
     * "60123456789@c.us" all compare equal.
     */
    public static function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits !== '' && str_starts_with($digits, '0')) {
            $digits = '60'.substr($digits, 1);
        }

        return $digits;
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
