<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class LiveHostApplicant extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id', 'applicant_number', 'email',
        'form_data', 'form_schema_snapshot',
        'source', 'current_stage_id', 'status', 'rating', 'notes',
        'applied_at', 'hired_at', 'hired_user_id',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'applied_at' => 'datetime',
            'hired_at' => 'datetime',
            'form_data' => 'array',
            'form_schema_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $applicant) {
            $snapshot = $applicant->form_schema_snapshot ?? [];
            $emailField = null;
            foreach (($snapshot['pages'] ?? []) as $page) {
                foreach (($page['fields'] ?? []) as $field) {
                    if (($field['role'] ?? null) === 'email') {
                        $emailField = $field;
                        break 2;
                    }
                }
            }

            if ($emailField) {
                $value = $applicant->form_data[$emailField['id']] ?? null;
                if ($value !== null) {
                    $applicant->email = (string) $value;
                }
            }
        });
    }

    public function valueByRole(string $role): mixed
    {
        $schema = $this->form_schema_snapshot ?? [];
        foreach (($schema['pages'] ?? []) as $page) {
            foreach (($page['fields'] ?? []) as $field) {
                if (($field['role'] ?? null) === $role) {
                    return $this->form_data[$field['id']] ?? null;
                }
            }
        }

        return null;
    }

    public function getNameAttribute(): ?string
    {
        return $this->valueByRole('name') ?? $this->email;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->valueByRole('phone');
    }

    public function getResumePathAttribute(): ?string
    {
        return $this->valueByRole('resume');
    }

    public function getLocationAttribute(): ?string
    {
        $value = $this->valueByRole('location') ?? $this->valueByRole('domicile');
        if ($value !== null && $value !== '') {
            return (string) $value;
        }

        $schema = $this->form_schema_snapshot ?? [];
        $keywords = ['location', 'lokasi', 'domisili', 'domicile', 'kota', 'city', 'alamat'];
        foreach (($schema['pages'] ?? []) as $page) {
            foreach (($page['fields'] ?? []) as $field) {
                $id = $field['id'] ?? '';
                $label = mb_strtolower((string) ($field['label'] ?? ''));
                $matchesKeyword = false;
                foreach ($keywords as $keyword) {
                    if (str_contains($label, $keyword)) {
                        $matchesKeyword = true;
                        break;
                    }
                }
                if ($id === 'f_location' || $matchesKeyword) {
                    $found = $this->form_data[$id] ?? null;
                    if ($found !== null && $found !== '') {
                        return (string) $found;
                    }
                }
            }
        }

        return null;
    }

    public function appliedAtLabel(): ?string
    {
        if (! $this->applied_at) {
            return null;
        }

        $wib = $this->applied_at->timezone('Asia/Jakarta');

        return $wib->format('d M Y').' • '.$wib->format('H:i').' WIB';
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(LiveHostRecruitmentCampaign::class, 'campaign_id');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(LiveHostRecruitmentStage::class, 'current_stage_id');
    }

    public function hiredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hired_user_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(LiveHostApplicantStageHistory::class, 'applicant_id')->latest();
    }

    public function stageRows(): HasMany
    {
        return $this->hasMany(LiveHostApplicantStage::class, 'applicant_id');
    }

    public function currentStageRow(): HasOne
    {
        return $this->hasOne(LiveHostApplicantStage::class, 'applicant_id')
            ->whereNull('exited_at')
            ->latestOfMany('entered_at');
    }

    public static function generateApplicantNumber(): string
    {
        return DB::transaction(function () {
            $yearMonth = now()->format('Ym');
            $prefix = "LHA-{$yearMonth}-";
            $last = static::query()
                ->where('applicant_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('applicant_number')
                ->first();
            $next = $last ? ((int) substr($last->applicant_number, -4)) + 1 : 1;

            return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }
}
