<?php

namespace App\Models;

use Database\Factories\LiveHostMenteeDisciplinaryRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveHostMenteeDisciplinaryRecord extends Model
{
    /** @use HasFactory<LiveHostMenteeDisciplinaryRecordFactory> */
    use HasFactory;

    protected $table = 'live_host_mentee_disciplinary_records';

    public const CATEGORIES = ['lateness', 'absence', 'rule_violation', 'misconduct', 'other'];

    public const SEVERITIES = ['minor', 'major'];

    protected $fillable = [
        'mentee_id', 'incident_date', 'category', 'severity', 'description', 'recorded_by', 'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(LiveHostMentee::class, 'mentee_id');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
