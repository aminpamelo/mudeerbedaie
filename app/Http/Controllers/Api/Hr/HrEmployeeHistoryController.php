<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;

class HrEmployeeHistoryController extends Controller
{
    /**
     * List history entries for an employee.
     */
    public function index(Employee $employee): JsonResponse
    {
        $histories = $employee->histories()
            ->with('changedByUser:id,name')
            ->latest('created_at')
            ->get();

        return response()->json(['data' => $histories]);
    }
}
