<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\PayrollSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrPayrollSettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = PayrollSetting::orderBy('key')->get()
            ->pluck('value', 'key');

        return response()->json(['data' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $allowedKeys = [
            'unpaid_leave_divisor', 'pay_day',
            'company_name', 'company_address',
            'company_epf_number', 'company_socso_number', 'company_tax_number',
            'bank_name', 'bank_account',
            'epf_employee_default_rate',
        ];

        $data = $request->only($allowedKeys);

        foreach ($data as $key => $value) {
            if ($value !== null && $value !== '') {
                PayrollSetting::setValue($key, (string) $value);
            }
        }

        $settings = PayrollSetting::orderBy('key')->get()
            ->pluck('value', 'key');

        return response()->json([
            'data' => $settings,
            'message' => 'Payroll settings updated successfully.',
        ]);
    }
}
