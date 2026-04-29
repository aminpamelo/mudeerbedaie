<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsPlatform;
use Illuminate\Http\JsonResponse;

class CmsPlatformController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => CmsPlatform::enabled()->get(),
        ]);
    }
}
