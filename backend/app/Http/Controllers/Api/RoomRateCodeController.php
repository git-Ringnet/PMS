<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomRateCode;
use App\Models\RoomRatePlan;
use App\Models\RoomRateDailyMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'mappings' => 'required|array',
            'mappings.*.Date' => 'required|date',
            'mappings.*.Code' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->mappings as $mapping) {
                $existing = RoomRateDailyMapping::where('RateCode', $ma)
                    ->where('Date', $mapping['Date'])
                    ->first();
                if ($existing) {
                    $existing->update(['Code' => $mapping['Code']]);
                } else {
                    RoomRateDailyMapping::create([
                        'RateCode' => $ma,
                        'Date' => $mapping['Date'],
                        'Code' => $mapping['Code']
                    ]);
                }
            }
            DB::commit();
            return response()->json(['message' => 'Daily mappings saved']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error saving daily mappings', 'error' => $e->getMessage()], 500);
        }
    }
}
