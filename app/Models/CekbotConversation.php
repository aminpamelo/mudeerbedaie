<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CekbotConversation extends Model
{
    /** @use HasFactory<\Database\Factories\CekbotConversationFactory> */
    use HasFactory;

    protected $fillable = [
        'cekbot_session_id',
        'chat_id',
        'name',
        'is_group',
        'unread_count',
        'last_message_at',
        'last_message_preview',
        'archived_at',
        'handed_over_at',
        'handed_over_by',
        'assigned_to',
        'labels',
        'lead_category_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
            'unread_count' => 'integer',
            'last_message_at' => 'datetime',
            'archived_at' => 'datetime',
            'handed_over_at' => 'datetime',
            'labels' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<CekbotConversationNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CekbotConversationNote::class, 'cekbot_conversation_id');
    }

    /**
     * @return BelongsTo<CekbotSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(CekbotSession::class, 'cekbot_session_id');
    }

    /**
     * The pipeline category this lead is filed under (for the Kanban board).
     *
     * @return BelongsTo<CekbotLeadCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CekbotLeadCategory::class, 'lead_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handedOverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }

    /**
     * Whether a human has taken over (bot paused).
     */
    public function isHandedOver(): bool
    {
        return $this->handed_over_at !== null;
    }

    /**
     * @return HasMany<CekbotMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CekbotMessage::class, 'cekbot_conversation_id');
    }

    /**
     * @param  Builder<CekbotConversation>  $query
     * @return Builder<CekbotConversation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function markAsRead(): void
    {
        if ($this->unread_count > 0) {
            $this->update(['unread_count' => 0]);
        }
    }

    /**
     * The phone number portion of the chat id (digits only), for display.
     */
    public function phoneNumber(): string
    {
        return \Illuminate\Support\Str::before($this->chat_id, '@');
    }
}
