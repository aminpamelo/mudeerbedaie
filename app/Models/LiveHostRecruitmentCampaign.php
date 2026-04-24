<?php

namespace App\Models;

use App\Support\Recruitment\DefaultFormSchema;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveHostRecruitmentCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'description', 'status', 'target_count',
        'opens_at', 'closes_at', 'created_by', 'form_schema',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'target_count' => 'integer',
            'form_schema' => 'array',
        ];
    }

    public function getAllFields(): array
    {
        $fields = [];
        foreach (($this->form_schema['pages'] ?? []) as $page) {
            foreach (($page['fields'] ?? []) as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public function getFieldByRole(string $role): ?array
    {
        foreach ($this->getAllFields() as $field) {
            if (($field['role'] ?? null) === $role) {
                return $field;
            }
        }

        return null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(LiveHostRecruitmentStage::class, 'campaign_id')->orderBy('position');
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(LiveHostApplicant::class, 'campaign_id');
    }

    public function isAcceptingApplications(): bool
    {
        if ($this->status !== 'open') {
            return false;
        }
        if ($this->closes_at !== null && $this->closes_at->isPast()) {
            return false;
        }

        return true;
    }

    protected static function booted(): void
    {
        static::creating(function (self $campaign) {
            if (empty($campaign->form_schema)) {
                $campaign->form_schema = DefaultFormSchema::get();
            }
        });

        static::created(function (self $campaign) {
            $campaign->stages()->createMany([
                ['position' => 1, 'name' => 'Review', 'is_final' => false],
                ['position' => 2, 'name' => 'Interview', 'is_final' => false],
                ['position' => 3, 'name' => 'Test Live', 'is_final' => false],
                ['position' => 4, 'name' => 'Final', 'is_final' => true],
            ]);
        });
    }
}
