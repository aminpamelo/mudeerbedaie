<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TmsProjectMember extends Model
{
    protected $fillable = ['project_id', 'user_id', 'role'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(TmsProject::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
