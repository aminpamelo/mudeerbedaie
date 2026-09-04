<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CekbotAutoReply extends Model
{
    /** @use HasFactory<\Database\Factories\CekbotAutoReplyFactory> */
    use HasFactory;

    protected $fillable = [
        'cekbot_session_id',
        'name',
        'match_type',
        'keywords',
        'reply_body',
        'is_active',
        'priority',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CekbotSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CekbotSession::class, 'cekbot_session_id');
    }

    /**
     * Whether an incoming message body triggers this rule.
     */
    public function matches(string $body): bool
    {
        $haystack = Str::lower(trim($body));

        if ($haystack === '') {
            return false;
        }

        foreach ((array) $this->keywords as $keyword) {
            $needle = Str::lower(trim((string) $keyword));
            if ($needle === '') {
                continue;
            }

            $hit = match ($this->match_type) {
                'exact' => $haystack === $needle,
                'starts' => Str::startsWith($haystack, $needle),
                'regex' => @preg_match('/'.$needle.'/i', $body) === 1,
                default => Str::contains($haystack, $needle), // contains
            };

            if ($hit) {
                return true;
            }
        }

        return false;
    }
}
