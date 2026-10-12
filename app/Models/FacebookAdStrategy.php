<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named advertising strategy / skill (e.g. "Broad Testing", "CBO Scaling")
 * with a written playbook, launch-wizard defaults, and bundled automation
 * rules. Campaigns are tagged with a strategy so the team knows which
 * playbook each campaign runs and can compare results per strategy.
 */
class FacebookAdStrategy extends Model
{
    /** @use HasFactory<\Database\Factories\FacebookAdStrategyFactory> */
    use HasFactory;

    public const COLORS = ['orange', 'green', 'blue', 'purple', 'red', 'yellow', 'gray'];

    protected $fillable = [
        'created_by',
        'name',
        'color',
        'description',
        'playbook',
        'launch_preset',
        'rule_presets',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'launch_preset' => 'array',
            'rule_presets' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(FacebookCampaignStrategy::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(FacebookAdRule::class);
    }
}
