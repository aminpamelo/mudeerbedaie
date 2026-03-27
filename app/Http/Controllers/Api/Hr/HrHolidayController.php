<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreHolidayRequest;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrHolidayController extends Controller
{
    /**
     * List all holidays with optional year filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Holiday::query();

        if ($year = $request->get('year')) {
            $query->forYear((int) $year);
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        $holidays = $query->orderBy('date')->paginate(15);

        return response()->json($holidays);
    }

    /**
     * Create a new holiday.
     */
    public function store(StoreHolidayRequest $request): JsonResponse
    {
        $holiday = Holiday::create($request->validated());

        return response()->json([
            'data' => $holiday,
            'message' => 'Holiday created successfully.',
        ], 201);
    }

    /**
     * Show a single holiday.
     */
    public function show(Holiday $holiday): JsonResponse
    {
        return response()->json(['data' => $holiday]);
    }

    /**
     * Update a holiday.
     */
    public function update(StoreHolidayRequest $request, Holiday $holiday): JsonResponse
    {
        $holiday->update($request->validated());

        return response()->json([
            'data' => $holiday->fresh(),
            'message' => 'Holiday updated successfully.',
        ]);
    }

    /**
     * Delete a holiday.
     */
    public function destroy(Holiday $holiday): JsonResponse
    {
        $holiday->delete();

        return response()->json(['message' => 'Holiday deleted successfully.']);
    }

    /**
     * Bulk import Malaysian 2026 holidays.
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $year = $request->get('year', 2026);

        $holidays = [
            ['name' => 'New Year\'s Day', 'date' => "{$year}-01-01", 'type' => 'national', 'states' => null, 'is_recurring' => true],
            ['name' => 'Thaipusam', 'date' => "{$year}-01-25", 'type' => 'national', 'states' => null, 'is_recurring' => false],
            ['name' => 'Nuzul Al-Quran', 'date' => "{$year}-02-17", 'type' => 'national', 'states' => null, 'is_recurring' => false],
            ['name' => 'Labour Day', 'date' => "{$year}-05-01", 'type' => 'national', 'states' => null, 'is_recurring' => true],
            ['name' => 'Vesak Day', 'date' => "{$year}-05-12", 'type' => 'national', 'states' => null, 'is_recurring' => false],
            ['name' => 'Yang di-Pertuan Agong Birthday', 'date' => "{$year}-06-01", 'type' => 'national', 'states' => null, 'is_recurring' => false],
            ['name' => 'Hari Raya Aidilfitri', 'date' => "{$year}-03-30", 'type' => 'national', 'states' => null, 'is_recurring' => false],
            ['name' => 'Hari Raya Aidilfitri (2nd Day)', 'date' => "{$year}-03-31", 'type' => 'national', 'states' => null, 'is_recurring' => false],
            ['name' => 'Hari Raya Haji', 'date' => "{$year}-06-07", 'type' => 'national', 'states' => null, 'is_recurring' => false],
            ['name' => 'Hari Raya Haji (2nd Day)', 'date' => "{$year}-06-08", 'type' => 'national', 'states' => null, 'is_recurring' => false],
            ['name' => 'Awal Muharram', 'date' => "{$year}-06-27", 'type' => 'national', 'states' => null, 'is_recurring' => false],
            ['name' => 'Malaysia Day', 'date' => "{$year}-09-16", 'type' => 'national', 'states' => null, 'is_recurring' => true],
            ['name' => 'Maulidur Rasul', 'date' => "{$year}-09-05", 'type' => 'national', 'states' => null, 'is_recurring' => false],
            ['name' => 'Deepavali', 'date' => "{$year}-10-20", 'type' => 'national', 'states' => null, 'is_recurring' => false],
            ['name' => 'Christmas Day', 'date' => "{$year}-12-25", 'type' => 'national', 'states' => null, 'is_recurring' => true],
            ['name' => 'Merdeka Day', 'date' => "{$year}-08-31", 'type' => 'national', 'states' => null, 'is_recurring' => true],
        ];

        return DB::transaction(function () use ($holidays, $year) {
            $created = 0;

            foreach ($holidays as $holiday) {
                Holiday::firstOrCreate(
                    ['date' => $holiday['date'], 'year' => $year],
                    array_merge($holiday, ['year' => $year])
                );
                $created++;
            }

            return response()->json([
                'message' => "{$created} holidays imported for year {$year}.",
            ], 201);
        });
    }
}
