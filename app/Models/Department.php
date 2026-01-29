<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepartmentRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'status',
        'sort_order',
    ];

    /**
     * Get all users in this department
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_users')
            ->withPivot('role', 'assigned_by')
            ->withTimestamps();
    }

    /**
     * Get department PICs (Person in Charge)
     */
    public function pics(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_users')
            ->withPivot('role', 'assigned_by')
            ->withTimestamps()
            ->wherePivot('role', DepartmentRole::DEPARTMENT_PIC->value);
    }

    /**
     * Get department members (view-only)
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_users')
            ->withPivot('role', 'assigned_by')
            ->withTimestamps()
            ->wherePivot('role', DepartmentRole::MEMBER->value);
    }

    /**
     * Get all tasks in this department
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Scope to get only active departments
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to order by sort_order
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Check if user is a PIC of this department
     */
    public function hasPic(User $user): bool
    {
        return $this->pics()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if user is a member of this department
     */
    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if user has any access to this department
     */
    public function hasAccess(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Add a PIC to this department
     */
    public function addPic(User $user, ?User $assignedBy = null): void
    {
        $this->users()->syncWithoutDetaching([
            $user->id => [
                'role' => DepartmentRole::DEPARTMENT_PIC->value,
                'assigned_by' => $assignedBy?->id,
            ],
        ]);
    }

    /**
     * Remove a PIC from this department
     */
    public function removePic(User $user): void
    {
        $this->users()->detach($user->id);
    }

    /**
     * Add a member to this department
     */
    public function addMember(User $user, ?User $assignedBy = null): void
    {
        $this->users()->syncWithoutDetaching([
            $user->id => [
                'role' => DepartmentRole::MEMBER->value,
                'assigned_by' => $assignedBy?->id,
            ],
        ]);
    }

    /**
     * Get task counts by status
     */
    public function getTaskCountsByStatus(): array
    {
        return $this->tasks()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }
}
