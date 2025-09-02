<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    protected $fillable = [
        'user_id',
        'teacher_id',
        'ic_number',
        'phone',
        'status',
        'joined_at',
        'bank_account_holder',
        'bank_account_number',
        'bank_name',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'status' => 'string',
            'bank_account_number' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'teacher_id');
    }

    public function activeCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'teacher_id')->where('status', 'active');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassModel::class, 'teacher_id');
    }

    public function upcomingClasses(): HasMany
    {
        return $this->hasMany(ClassModel::class, 'teacher_id')->upcoming();
    }

    public function scheduledClasses(): HasMany
    {
        return $this->hasMany(ClassModel::class, 'teacher_id')->scheduled();
    }

    public function completedClasses(): HasMany
    {
        return $this->hasMany(ClassModel::class, 'teacher_id')->completed();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getFullNameAttribute(): string
    {
        return $this->user->name;
    }

    public function getEmailAttribute(): string
    {
        return $this->user->email;
    }

    public function getMaskedAccountNumberAttribute(): ?string
    {
        if (! $this->bank_account_number) {
            return null;
        }

        $accountNumber = $this->bank_account_number;
        $length = strlen($accountNumber);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).substr($accountNumber, -4);
    }

    public static function generateTeacherId(): string
    {
        // Get the last teacher ID number
        $lastTeacher = static::latest('id')->first();
        
        if (!$lastTeacher || !$lastTeacher->teacher_id) {
            $nextNumber = 1;
        } else {
            // Extract number from TID001, TID002, etc.
            preg_match('/TID(\d+)/', $lastTeacher->teacher_id, $matches);
            $nextNumber = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
        }
        
        // Format as TID001, TID002, etc.
        return 'TID' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    public static function getMalaysianBanks(): array
    {
        return [
            'Maybank' => 'Maybank',
            'CIMB Bank' => 'CIMB Bank',
            'Public Bank' => 'Public Bank',
            'RHB Bank' => 'RHB Bank',
            'Hong Leong Bank' => 'Hong Leong Bank',
            'AmBank' => 'AmBank',
            'Alliance Bank' => 'Alliance Bank',
            'Affin Bank' => 'Affin Bank',
            'Bank Rakyat' => 'Bank Rakyat',
            'Bank Islam' => 'Bank Islam',
            'HSBC Malaysia' => 'HSBC Malaysia',
            'Standard Chartered Malaysia' => 'Standard Chartered Malaysia',
            'OCBC Bank Malaysia' => 'OCBC Bank Malaysia',
            'United Overseas Bank Malaysia' => 'United Overseas Bank Malaysia',
            'Bank Simpanan Nasional' => 'Bank Simpanan Nasional',
        ];
    }
}
