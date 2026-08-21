<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomRateCode;
use App\Models\RoomRatePlan;
use App\Models\RoomRateDailyMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomRateCodeController extends Controller
{
    public function index()
    {
        $rateCodes = RoomRateCode::with('ratePlans', 'dailyMappings')->get();
        return response()->json(['data' => $rateCodes]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'Ma' => 'required|string|unique:room_rate_codes,Ma',
            'Description' => 'nullable|string',
            'BeginDate' => 'nullable|date',
            'EndDate' => 'nullable|date',
            'IncludeBF' => 'boolean',
            'Currency' => 'nullable|string',
            'IsDaily' => 'boolean',
        ]);

        $rateCode = RoomRateCode::create($request->all());

        // Always create a default rate plan when a new code is created
        RoomRatePlan::create([
            'RateCode' => $rateCode->Ma,
            'Code' => 'DEFAULT',
            'Description' => 'Mặc định',
            'Period' => []
        ]);

        return response()->json(['message' => 'Created successfully', 'data' => $rateCode], 201);
    }

    public function show($ma)
    {
        $rateCode = RoomRateCode::with('ratePlans', 'dailyMappings')->findOrFail($ma);
        return response()->json(['data' => $rateCode]);
    }

    public function update(Request $request, $ma)
    {
        $rateCode = RoomRateCode::findOrFail($ma);
        $rateCode->update($request->all());

        return response()->json(['message' => 'Updated successfully', 'data' => $rateCode]);
    }

    public function destroy($ma)
    {
        $rateCode = RoomRateCode::findOrFail($ma);
        $rateCode->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    // Custom method to save Rate Plans (Matrix Grid)
    public function saveRatePlan(Request $request, $ma)
    {
        $request->validate([
            'Code' => 'required|string',
            'Description' => 'nullable|string',
            'BeginDate' => 'nullable|date',
            'EndDate' => 'nullable|date',
            'Period' => 'nullable|array', // JSON matrix
        ]);

        $data = $request->only(['Description', 'BeginDate', 'EndDate', 'Period']);
        $data['RateCode'] = $ma;
        $data['Code'] = $request->Code;

        // Use updateOrCreate manually since we have composite keys
        $plan = RoomRatePlan::where('RateCode', $ma)->where('Code', $request->Code)->first();
        if ($plan) {
            $plan->update($data);
        } else {
            $plan = RoomRatePlan::create($data);
        }

        return response()->json(['message' => 'Rate Plan saved', 'data' => $plan]);
    }

    public function deleteRatePlan($ma, $code)
    {
        $plan = RoomRatePlan::where('RateCode', $ma)->where('Code', $code)->firstOrFail();
        $plan->delete();
        
        // Cascade delete daily mappings mapped to this code
        RoomRateDailyMapping::where('RateCode', $ma)->where('Code', $code)->delete();
        
        return response()->json(['message' => 'Rate Plan deleted']);
    }

    public function saveDailyMappings(Request $request, $ma)
    {
        $request->validate([
            'mappings' => 'present|array',
            'mappings.*.Date' => 'required|date',
            'mappings.*.Code' => 'required|string',
            'mode' => 'nullable|in:replace,merge',
            'delete_dates' => 'nullable|array',
            'delete_dates.*' => 'required|date',
        ]);

        $mode = $request->input('mode', 'replace');
        $mappings = collect($request->input('mappings', []))
            ->map(fn ($mapping) => [
                'Date' => \Carbon\Carbon::parse($mapping['Date'])->toDateString(),
                'Code' => trim((string) $mapping['Code']),
            ])
            ->keyBy('Date')
            ->values();
        $deleteDates = collect($request->input('delete_dates', []))
            ->map(fn ($date) => \Carbon\Carbon::parse($date)->toDateString())
            ->unique()
            ->values();

        $savedMappings = DB::transaction(function () use ($ma, $mode, $mappings, $deleteDates) {
            $rateCode = RoomRateCode::where('Ma', $ma)->lockForUpdate()->firstOrFail();
            $availablePlanCodes = RoomRatePlan::where('RateCode', $ma)->pluck('Code');
            $unknownPlanCodes = $mappings->pluck('Code')->unique()->diff($availablePlanCodes)->values();

            if ($unknownPlanCodes->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'mappings' => 'Loại giá không tồn tại trong Rate Code: ' . $unknownPlanCodes->implode(', '),
                ]);
            }

            if ($mode === 'replace') {
                RoomRateDailyMapping::where('RateCode', $ma)->delete();
            } elseif ($deleteDates->isNotEmpty()) {
                RoomRateDailyMapping::where('RateCode', $ma)
                    ->whereIn('Date', $deleteDates->all())
                    ->delete();
            }

            foreach ($mappings as $mapping) {
                DB::table('room_rate_daily_mappings')->updateOrInsert(
                    ['RateCode' => $ma, 'Date' => $mapping['Date']],
                    ['Code' => $mapping['Code']]
                );
            }

            if ($mappings->isNotEmpty() && !$rateCode->IsDaily) {
                $rateCode->update(['IsDaily' => true]);
            }

            return RoomRateDailyMapping::where('RateCode', $ma)
                ->orderBy('Date')
                ->get();
        });

        return response()->json([
            'message' => 'Daily mappings saved',
            'data' => $savedMappings,
        ]);
    }
}
