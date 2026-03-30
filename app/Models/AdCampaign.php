<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdCampaign extends Model
{
    /** @use HasFactory<\Database\Factories\AdCampaignFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'content_id',
        'platform',
        'ad_id',
        'status',
        'budget',
        'start_date',
        'end_date',
        'notes',
        'assigned_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'budget' => 'decimal:2',
        ];
    }

    /**
     * Get the content this campaign is for
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    /**
     * Get the employee who assigned this campaign
     */
    public function assignedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_by');
    }

    /**
     * Get the stats for this campaign
     */
    public function stats(): HasMany
    {
        return $this->hasMany(AdStat::class);
    }
}
