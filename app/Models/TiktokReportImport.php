<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiktokReportImport extends Model
{
    protected $fillable = [
        'report_type',
        'file_path',
        'uploaded_by',
        'uploaded_at',
        'period_start',
        'period_end',
        'status',
        'total_rows',
        'matched_rows',
        'unmatched_rows',
        'error_log_json',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'period_start' => 'date',
            'period_end' => 'date',
            'error_log_json' => 'array',
        ];
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function liveReports(): HasMany
    {
        return $this->hasMany(TiktokLiveReport::class, 'import_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(TiktokOrder::class, 'import_id');
    }
}
