<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\DepartmentRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'locale',
        'host_color',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is teacher
     */
    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    /**
     * Check if user is student
     */
    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    /**
     * Check if user is live host
     */
    public function isLiveHost(): bool
    {
        return $this->role === 'live_host';
    }

    /**
     * Check if user is admin livehost
     */
    public function isAdminLivehost(): bool
    {
        return $this->role === 'admin_livehost';
    }

    /**
     * Check if user is class admin
     */
    public function isClassAdmin(): bool
    {
        return $this->role === 'class_admin';
    }

    /**
     * Check if user is a PIC Department (users.role = 'pic_department')
     */
    public function isPicDepartmentRole(): bool
    {
        return $this->role === 'pic_department';
    }

    /**
     * Check if user is a Member Department (users.role = 'member_department')
     */
    public function isMemberDepartmentRole(): bool
    {
        return $this->role === 'member_department';
    }

    /**
     * Check if user is department staff (either PIC or member)
     */
    public function isDepartmentStaff(): bool
    {
        return $this->isPicDepartmentRole() || $this->isMemberDepartmentRole();
    }

    /**
     * Get assigned class IDs for this class admin (from PIC relationship)
     *
     * @return array<int>
     */
    public function getAssignedClassIds(): array
    {
        return $this->picClasses()->pluck('classes.id')->toArray();
    }

    /**
     * Check if user can manage a specific class (admin, class_admin assigned, or teacher owner)
     */
    public function canManageClass(ClassModel $class): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isClassAdmin()) {
            return $this->isPicOf($class);
        }

        if ($this->isTeacher() && $this->teacher && $class->teacher_id === $this->teacher->id) {
            return true;
        }

        return false;
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if user is inactive
     */
    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    /**
     * Check if user is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Activate the user
     */
    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Deactivate the user
     */
    public function deactivate(): void
    {
        $this->update(['status' => 'inactive']);
    }

    /**
     * Suspend the user
     */
    public function suspend(): void
    {
        $this->update(['status' => 'suspended']);
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Get status badge color
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            'active' => 'green',
            'inactive' => 'gray',
            'suspended' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get formatted role name
     */
    public function getRoleNameAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->role));
    }

    /**
     * Get the student profile for this user
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * Get the teacher profile for this user
     */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /**
     * Get the courses created by this user
     */
    public function createdCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'created_by');
    }

    /**
     * Get the enrollments created by this user (admin/teacher enrolling students)
     */
    public function enrolledStudents(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'enrolled_by');
    }

    /**
     * Get payment methods for this user
     */
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * Get default payment method for this user
     */
    public function defaultPaymentMethod(): HasOne
    {
        return $this->hasOne(PaymentMethod::class)->where('is_default', true);
    }

    /**
     * Get orders for this user (if they're a student)
     */
    public function orders()
    {
        return $this->hasManyThrough(Order::class, Student::class, 'user_id', 'student_id');
    }

    /**
     * Get stripe customer record for this user
     */
    public function stripeCustomer(): HasOne
    {
        return $this->hasOne(StripeCustomer::class);
    }

    /**
     * Get platform accounts for this user (for live hosts)
     */
    public function platformAccounts(): HasMany
    {
        return $this->hasMany(PlatformAccount::class);
    }

    /**
     * Get assigned platform accounts for this live host (many-to-many)
     */
    public function assignedPlatformAccounts(): BelongsToMany
    {
        return $this->belongsToMany(PlatformAccount::class, 'live_host_platform_account')
            ->withTimestamps();
    }

    /**
     * Get classes where this user is assigned as PIC (Person In Charge)
     */
    public function picClasses(): BelongsToMany
    {
        return $this->belongsToMany(ClassModel::class, 'class_pics', 'user_id', 'class_id')
            ->withTimestamps();
    }

    /**
     * Check if user is PIC of a specific class
     */
    public function isPicOf(ClassModel $class): bool
    {
        return $this->picClasses()->where('class_id', $class->id)->exists();
    }

    /**
     * Get live sessions through platform accounts
     */
    public function liveSessions()
    {
        return $this->hasManyThrough(
            LiveSession::class,
            PlatformAccount::class,
            'user_id',
            'platform_account_id'
        );
    }

    /**
     * Get schedule assignments for this live host
     */
    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(LiveScheduleAssignment::class, 'live_host_id');
    }

    /**
     * Get the host color or generate a default one
     */
    public function getHostColorAttribute($value): string
    {
        if ($value) {
            return $value;
        }

        // Generate a consistent color based on user ID
        $colors = [
            '#10B981', // green
            '#F59E0B', // amber
            '#3B82F6', // blue
            '#EF4444', // red
            '#8B5CF6', // purple
            '#EC4899', // pink
            '#14B8A6', // teal
            '#F97316', // orange
        ];

        return $colors[$this->id % count($colors)];
    }

    /**
     * Get contrasting text color for host color
     */
    public function getHostTextColorAttribute(): string
    {
        $hex = ltrim($this->host_color, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Calculate luminance
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.5 ? '#000000' : '#FFFFFF';
    }

    /**
     * Get the user's preferred locale
     */
    public function getPreferredLocale(): string
    {
        return $this->locale ?? config('app.locale');
    }

    /**
     * Update user's preferred locale
     */
    public function setLocale(string $locale): void
    {
        if (in_array($locale, ['en', 'ms'])) {
            $this->update(['locale' => $locale]);
        }
    }

    /**
     * Get impersonation logs where this user was the impersonator
     */
    public function impersonationLogsAsImpersonator(): HasMany
    {
        return $this->hasMany(ImpersonationLog::class, 'impersonator_id');
    }

    /**
     * Get impersonation logs where this user was impersonated
     */
    public function impersonationLogsAsTarget(): HasMany
    {
        return $this->hasMany(ImpersonationLog::class, 'impersonated_id');
    }

    // =========================================================================
    // TASK MANAGEMENT - Department Relationships
    // =========================================================================

    /**
     * Get all departments this user belongs to
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_users')
            ->withPivot('role', 'assigned_by')
            ->withTimestamps();
    }

    /**
     * Get departments where user is PIC (Person in Charge)
     */
    public function picDepartments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_users')
            ->withPivot('role', 'assigned_by')
            ->withTimestamps()
            ->wherePivot('role', DepartmentRole::DEPARTMENT_PIC->value);
    }

    /**
     * Get tasks assigned to this user
     */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    /**
     * Get tasks created by this user
     */
    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    /**
     * Check if user is a PIC of any department
     */
    public function isDepartmentPic(): bool
    {
        return $this->picDepartments()->exists();
    }

    /**
     * Check if user is PIC of a specific department (includes parent PIC for child departments)
     */
    public function isPicOfDepartment(Department $department): bool
    {
        // Direct PIC check
        if ($this->picDepartments()->where('department_id', $department->id)->exists()) {
            return true;
        }

        // If child department, check if user is PIC of parent
        if ($department->parent_id) {
            return $this->picDepartments()->where('department_id', $department->parent_id)->exists();
        }

        return false;
    }

    /**
     * Check if user can manage tasks in a department (PIC only - full control including settings)
     */
    public function canManageTasks(Department $department): bool
    {
        return $this->isPicOfDepartment($department);
    }

    /**
     * Check if user can create tasks in a department (PIC only)
     */
    public function canCreateTasks(Department $department): bool
    {
        return $this->isPicOfDepartment($department);
    }

    /**
     * Check if user can edit/update tasks in a department (PIC and Members)
     */
    public function canEditTasks(Department $department): bool
    {
        // Direct membership check
        if ($this->departments->contains('id', $department->id)) {
            return true;
        }

        // If child department, check parent membership
        if ($department->parent_id) {
            return $this->departments->contains('id', $department->parent_id);
        }

        return false;
    }

    /**
     * Check if user can view tasks in a department
     */
    public function canViewTasks(Department $department): bool
    {
        // Admin can view all departments (read-only)
        if ($this->isAdmin()) {
            return true;
        }

        // Direct membership check
        if ($this->departments->contains('id', $department->id)) {
            return true;
        }

        // If child department, check parent membership
        if ($department->parent_id) {
            return $this->departments->contains('id', $department->parent_id);
        }

        return false;
    }

    /**
     * Get all accessible department IDs (direct + child departments of PIC parents)
     */
    public function getAccessibleDepartmentIds(): \Illuminate\Support\Collection
    {
        $directIds = $this->departments->pluck('id');

        // For PIC departments that have children, include child IDs
        $picIds = $this->picDepartments->pluck('id');
        $childIds = Department::whereIn('parent_id', $picIds)->pluck('id');

        return $directIds->merge($childIds)->unique();
    }

    /**
     * Get all departments accessible to this user (direct + children of PIC parents)
     */
    public function getAccessibleDepartments(): \Illuminate\Database\Eloquent\Collection
    {
        $ids = $this->getAccessibleDepartmentIds();

        return Department::whereIn('id', $ids)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get all departments where user can create tasks (PIC departments + their children)
     */
    public function getCreatableDepartments(): \Illuminate\Database\Eloquent\Collection
    {
        $picIds = $this->picDepartments()->where('status', 'active')->pluck('departments.id');
        $childIds = Department::whereIn('parent_id', $picIds)->where('status', 'active')->pluck('id');

        return Department::whereIn('id', $picIds->merge($childIds))
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Check if user has access to task management module
     */
    public function hasTaskManagementAccess(): bool
    {
        return $this->isAdmin() || $this->departments()->exists();
    }
}
