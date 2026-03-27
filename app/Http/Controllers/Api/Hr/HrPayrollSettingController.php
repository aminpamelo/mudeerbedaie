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
        $settings = PayrollSetting::orderBy('key')->get();

        return response()->json(['data' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['required', 'string'],
        ]);

        foreach ($validated['settings'] as $setting) {
            PayrollSetting::setValue($setting['key'], $setting['value']);
        }

        return response()->json([
            'data' => PayrollSetting::orderBy('key')->get(),
            'message' => 'Payroll settings updated successfully.',
        ]);
    }
}
