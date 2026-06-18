<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RateCode;
use App\Models\RatePlan;
use Illuminate\Http\Request;

class RatePlanController extends Controller
{
    public function index($rateCode)
    {
        $plans = RatePlan::where('rate_code', $rateCode)
            ->orderBy('begin_date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $plans,
        ]);
    }

    public function store(Request $request, $rateCode)
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:10',
            'description' => 'nullable|string|max:150',
            'begin_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:begin_date',
            'period'      => 'nullable|array',
        ]);

        if (!RateCode::where('code', $rateCode)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Rate code not found',
            ], 404);
        }

        $exists = RatePlan::where('rate_code', $rateCode)
            ->where('code', $validated['code'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Sub-code ' . $validated['code'] . ' already exists in this rate code',
            ], 422);
        }

        $periodData = $request->input('period') ?? $request->input('value');

        if (empty($periodData)) {
            $roomClasses = \App\Models\RoomClass::all();
            $roomForms   = \App\Models\RoomForm::all();
            $defaultValue = [];
            foreach ($roomClasses as $rc) {
                foreach ($roomForms as $rf) {
                    $defaultValue[] = [
                        'RoomTypeId' => $rc->id,
                        'RoomKindId' => $rf->id,
                        'Price'      => 0.0,
                    ];
                }
            }
            $periodData = $defaultValue;
        }

        $plan = RatePlan::create([
            'rate_code'   => $rateCode,
            'code'        => $validated['code'],
            'description' => $validated['description'] ?? null,
            'begin_date'  => $validated['begin_date'],
            'end_date'    => $validated['end_date'],
            'period'      => $periodData,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $plan,
        ], 201);
    }

    public function update(Request $request, $rateCode, $plan)
    {
        $ratePlan = RatePlan::where('rate_code', $rateCode)
            ->where('id', $plan)
            ->first();

        if (!$ratePlan) {
            return response()->json([
                'success' => false,
                'message' => 'Rate plan not found',
            ], 404);
        }

        $validated = $request->validate([
            'code'        => 'required|string|max:10',
            'description' => 'nullable|string|max:150',
            'begin_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:begin_date',
            'period'      => 'nullable|array',
        ]);

        $exists = RatePlan::where('rate_code', $rateCode)
            ->where('code', $validated['code'])
            ->where('id', '!=', $plan)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Sub-code ' . $validated['code'] . ' already exists in this rate code',
            ], 422);
        }

        $ratePlan->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $ratePlan,
        ]);
    }

    public function destroy($rateCode, $plan)
    {
        $ratePlan = RatePlan::where('rate_code', $rateCode)
            ->where('id', $plan)
            ->first();

        if (!$ratePlan) {
            return response()->json([
                'success' => false,
                'message' => 'Rate plan not found',
            ], 404);
        }

        $ratePlan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rate plan deleted successfully',
        ]);
    }
}