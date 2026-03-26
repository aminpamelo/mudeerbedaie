<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Employee extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $appends = [
        'employment_type_label',
    ];

    protected $fillable = [
        'user_id',
        'employee_id',
        'full_name',
        'ic_number',
        'date_of_birth',
        'gender',
        'religion',
        'race',
        'marital_status',
        'phone',
        'personal_email',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postcode',
        'profile_photo',
        'department_id',
        'position_id',
        'employment_type',
        'join_date',
        'probation_end_date',
        'confirmation_date',
        'contract_end_date',
        'status',
        'resignation_date',
        'last_working_date',
        'bank_name',
        'bank_account_number',
        'epf_number',
        'socso_number',
        'tax_number',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ic_number' => 'encrypted',
            'bank_account_number' => 'encrypted',
            'date_of_birth' => 'date',
            'join_date' => 'date',
            'probation_end_date' => 'date',
            'confirmation_date' => 'date',
            'contract_end_date' => 'date',
            'resignation_date' => 'date',
            'last_working_date' => 'date',
        ];
    }

    /**
     * Get the user account linked to this employee
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the department this employee belongs to
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the position this employee holds
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Get emergency contacts for this employee
     */
    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmployeeEmergencyContact::class);
    }

    /**
     * Get documents for this employee
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /**
     * Get history records for this employee
     */
    public function histories(): HasMany
    {
        return $this->hasMany(EmployeeHistory::class);
    }

    /**
     * Generate the next employee ID in BDE-XXXX format
     */
    public static function generateEmployeeId(): string
    {
        $employeeIds = static::withTrashed()
            ->whereNotNull('employee_id')
            ->pluck('employee_id')
            ->map(function ($id) {
                preg_match('/BDE-(\d+)/', $id, $matches);

                return isset($matches[1]) ? intval($matches[1]) : 0;
            })
            ->filter()
            ->sort()
            ->values();

        $nextNumber = $employeeIds->isEmpty() ? 1 : $employeeIds->max() + 1;

        return 'BDE-'.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get human-readable tenure since join date
     */
    public function getTenureAttribute(): string
    {
        if (! $this->join_date) {
            return '-';
        }

        $endDate = $this->last_working_date ?? Carbon::now();
        $diff = $this->join_date->diff($endDate);

        $parts = [];
        if ($diff->y > 0) {
            $parts[] = $diff->y.' '.($diff->y === 1 ? 'year' : 'years');
        }
        if ($diff->m > 0) {
            $parts[] = $diff->m.' '.($diff->m === 1 ? 'month' : 'months');
        }
        if (empty($parts) && $diff->d >= 0) {
            $parts[] = $diff->d.' '.($diff->d === 1 ? 'day' : 'days');
        }

        return implode(', ', $parts);
    }

    /**
     * Get color based on employee status
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'green',
            'probation' => 'yellow',
            'resigned', 'terminated' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get the employee's initials from full_name.
     */
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', trim($this->full_name));

        if (count($words) >= 2) {
            return strtoupper(mb_substr($words[0], 0, 1).mb_substr(end($words), 0, 1));
        }

        return strtoupper(mb_substr($this->full_name, 0, 2));
    }

    /**
     * Get the full address as a single string.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->postcode.' '.$this->city,
            $this->state,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get the employee's age based on date of birth.
     */
    public function getAgeAttribute(): int
    {
        return $this->date_of_birth ? $this->date_of_birth->age : 0;
    }

    /**
     * Get formatted employment type label.
     */
    public function getEmploymentTypeLabelAttribute(): string
    {
        return match ($this->employment_type) {
            'full_time' => 'Full Time',
            'part_time' => 'Part Time',
            'contract' => 'Contract',
            'intern' => 'Intern',
            default => ucfirst($this->employment_type ?? ''),
        };
    }

    /**
     * Get masked bank account number.
     */
    public function getMaskedBankAccountAttribute(): string
    {
        $account = $this->bank_account_number;

        if (! $account || strlen($account) <= 4) {
            return $account ?? '-';
        }

        return str_repeat('*', strlen($account) - 4).substr($account, -4);
    }

    /**
     * Get masked IC number (e.g., 901215-14-****)
     */
    public function getMaskedIcAttribute(): ?string
    {
        if (! $this->ic_number) {
            return null;
        }

        $ic = $this->ic_number;
        $length = strlen($ic);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        // Format: show first 8 chars, mask last 4 for 12-digit Malaysian IC
        if ($length === 12) {
            return substr($ic, 0, 6).'-'.substr($ic, 6, 2).'-****';
        }

        return substr($ic, 0, $length - 4).str_repeat('*', 4);
    }
}
