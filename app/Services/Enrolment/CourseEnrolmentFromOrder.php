<?php

namespace App\Services\Enrolment;

use App\Models\Enrollment;
use App\Models\ProductOrder;
use App\Models\Student;
use Illuminate\Support\Facades\Log;

/**
 * Turns paid storefront course purchases into course enrolments.
 *
 * When an order that contains course line items is paid, the buyer (a user with
 * a Student record) is enrolled into each purchased course. Guest orders have no
 * Student to enrol, so they are left for staff to handle manually.
 */
class CourseEnrolmentFromOrder
{
    /**
     * @return int number of enrolments created
     */
    public function fulfil(ProductOrder $order): int
    {
        $courseIds = $order->items()
            ->whereNotNull('course_id')
            ->pluck('course_id')
            ->unique()
            ->values();

        if ($courseIds->isEmpty()) {
            return 0;
        }

        $student = $this->resolveStudent($order);

        if (! $student) {
            Log::info('Course order paid but no student to enrol; leaving for manual handling', [
                'order_id' => $order->id,
                'course_ids' => $courseIds->all(),
            ]);

            return 0;
        }

        $created = 0;

        foreach ($order->items()->whereNotNull('course_id')->get() as $item) {
            $already = Enrollment::query()
                ->where('student_id', $student->id)
                ->where('course_id', $item->course_id)
                ->whereIn('status', ['enrolled', 'active'])
                ->exists();

            if ($already) {
                continue;
            }

            Enrollment::create([
                'student_id' => $student->id,
                'course_id' => $item->course_id,
                'enrolled_by' => $order->customer_id ?? $student->user_id,
                'status' => 'enrolled',
                'enrollment_date' => now(),
                'start_date' => now(),
                'enrollment_fee' => $item->unit_price,
                'notes' => 'Storefront purchase — order '.$order->order_number,
            ]);

            $created++;
        }

        if ($created > 0) {
            $order->addSystemNote("Enrolled student into {$created} course(s) from storefront purchase.");
        }

        return $created;
    }

    private function resolveStudent(ProductOrder $order): ?Student
    {
        if ($order->student_id) {
            return Student::find($order->student_id);
        }

        if ($order->customer_id) {
            return Student::where('user_id', $order->customer_id)->first();
        }

        return null;
    }
}
