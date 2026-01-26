<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'channel',
        'subject',
        'content',
        'content_html',
        'variables',
        'category',
        'is_active',
        'stats',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'stats' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function communicationLogs(): HasMany
    {
        return $this->hasMany(CommunicationLog::class, 'template_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeEmail($query)
    {
        return $query->where('channel', 'email');
    }

    public function scopeWhatsapp($query)
    {
        return $query->where('channel', 'whatsapp');
    }

    public function scopeSms($query)
    {
        return $query->where('channel', 'sms');
    }

    public function isEmail(): bool
    {
        return $this->channel === 'email';
    }

    public function isWhatsapp(): bool
    {
        return $this->channel === 'whatsapp';
    }

    public function isSms(): bool
    {
        return $this->channel === 'sms';
    }

    public function mergeVariables(array $data): string
    {
        $content = $this->content;

        foreach ($data as $key => $value) {
            $content = str_replace("{{{$key}}}", (string) $value, $content);
        }

        return $content;
    }
}
