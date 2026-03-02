<?php

namespace App\Models;

use App\AcademicStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassAssignmentApproval extends Model
{
    /** @use HasFactory<\Database\Factories\ClassAssignmentApprovalFactory> */
    use HasFactory;

    protected $fillable = [
        'class_id',
        'student_id',
        'product_order_id',
        'status',
        'enroll_with_subscription',
        'assigned_by',
        'approved_by',
        'notes',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'enroll_with_subscription' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    // Relationships

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function productOrder(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class);
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // Actions

    public function approve(User $approvedBy, bool $enrollWithSubscription = false): void
    {
        $class = $this->class;
        $student = $this->student;
        $order = $this->productOrder;

        // Create class_students record
        $class->addStudent($student, $order->order_number);

        // Optionally create course-level enrollment
        if ($enrollWithSubscription && $class->course_id) {
            $existingEnrollment = Enrollment::where('student_id', $student->id)
                ->where('course_id', $class->course_id)
                ->first();

            if (! $existingEnrollment) {
                Enrollment::create([
                    'student_id' => $student->id,
                    'course_id' => $class->course_id,
                    'enrolled_by' => $approvedBy->id,
                    'status' => 'active',
                    'academic_status' => AcademicStatus::ACTIVE,
                    'payment_method_type' => 'manual',
                    'enrollment_date' => now(),
                    'start_date' => now(),
                ]);
            }
        }

        $this->update([
            'status' => 'approved',
            'enroll_with_subscription' => $enrollWithSubscription,
            'approved_by' => $approvedBy->id,
            'approved_at' => now(),
        ]);
    }

    public function reject(User $rejectedBy, ?string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => $rejectedBy->id,
            'approved_at' => now(),
            'notes' => $notes,
        ]);
    }
}
