<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TmsProject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'description', 'color', 'icon', 'department_id',
        'owner_id', 'status', 'start_date', 'target_date', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'target_date' => 'date'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tms_project_members', 'project_id', 'user_id')
            ->withPivot('role')->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForDepartment($query, int $deptId)
    {
        return $query->where('department_id', $deptId);
    }
}
