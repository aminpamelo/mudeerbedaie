<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppCampaign extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_campaigns';

    protected $fillable = [
        'name',
        'source',
        'whatsapp_template_id',
        'template_name',
        'template_language',
        'variable_mapping',
        'status',
        'total_recipients',
        'skipped_count',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
        'estimated_cost_usd',
        'created_by',
        'started_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variable_mapping' => 'array',
            'estimated_cost_usd' => 'decimal:4',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignRecipient::class, 'whatsapp_campaign_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Percentage of recipients that have been processed (sent or failed).
     */
    public function getProgressPercentAttribute(): int
    {
        if ($this->total_recipients <= 0) {
            return 0;
        }

        $processed = $this->sent_count + $this->failed_count;

        return (int) min(100, round($processed / $this->total_recipients * 100));
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled'], true);
    }
}
