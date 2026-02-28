<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppConversation extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsAppConversationFactory> */
    use HasFactory;

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'phone_number',
        'student_id',
        'contact_name',
        'last_message_at',
        'last_message_preview',
        'unread_count',
        'is_service_window_open',
        'service_window_expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'service_window_expires_at' => 'datetime',
            'is_service_window_open' => 'boolean',
            'unread_count' => 'integer',
        ];
    }

    /**
     * @return HasMany<WhatsAppMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @param  Builder<WhatsAppConversation>  $query
     * @return Builder<WhatsAppConversation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param  Builder<WhatsAppConversation>  $query
     * @return Builder<WhatsAppConversation>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', 'archived');
    }

    /**
     * @param  Builder<WhatsAppConversation>  $query
     * @return Builder<WhatsAppConversation>
     */
    public function scopeWithUnread(Builder $query): Builder
    {
        return $query->where('unread_count', '>', 0);
    }

    /**
     * Check if the 24-hour service window is still open.
     */
    public function isServiceWindowOpen(): bool
    {
        return $this->service_window_expires_at !== null
            && $this->service_window_expires_at->isFuture();
    }

    /**
     * Mark all messages in this conversation as read.
     */
    public function markAsRead(): void
    {
        $this->update(['unread_count' => 0]);
    }
}
