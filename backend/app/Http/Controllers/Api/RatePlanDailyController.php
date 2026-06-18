<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RatePlan;
use App\Models\RatePlanDaily;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RatePlanDailyController extends Controller
{
    public function index(Request $request, $rateCode)
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $dailies = RatePlanDaily::where('rate_code', $rateCode)
            ->whereBetween('date', [$request->from, $request->to])
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $dailies,
        ]);
    }

    public function apply(Request $request, $rateCode)
    {
        $validated = $request->validate([
            'code'             => 'required|string|max:10',
            'from'             => 'required|date',
            'to'               => 'required|date|after_or_equal:from',
            'days_of_week'     => 'nullable|array',
            'days_of_week.*'   => 'integer|between:0,6',
        ]);

        $plan = RatePlan::where('rate_code', $rateCode)
            ->where('code', $validated['code'])
            ->first();

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Sub-code ' . $validated['code'] . ' not found in this rate code',
            ], 422);
        }

        // Default: apply to all days of week
        $daysOfWeek = $validated['days_of_week'] ?? [0, 1, 2, 3, 4, 5, 6];
        $effectiveFrom = Carbon::parse($validated['from'])->max(Carbon::parse($plan->begin_date));
        $effectiveTo   = Carbon::parse($validated['to'])->min(Carbon::parse($plan->end_date));

        if ($effectiveFrom->gt($effectiveTo)) {
            return response()->json([
                'success' => false,
                'message' => 'Apply range is outside the selected sub-code effective dates',
            ], 422);
        }

        $current    = $effectiveFrom->copy();
        $to         = $effectiveTo->copy();
        $count      = 0;

        while ($current->lte($to)) {
            if (in_array($current->dayOfWeek, $daysOfWeek)) {
                RatePlanDaily::updateOrCreate(
                    [
                        'rate_code' => $rateCode,
                        'date'      => $current->format('Y-m-d'),
                    ],
                    [
                        'code' => $validated['code'],
                    ]
                );
                $count++;
            }
            $current->addDay();
        }

        return response()->json([
            'success' => true,
            'message' => "Applied {$count} days with sub-code {$validated['code']}",
            'count'   => $count,
            'from'    => $effectiveFrom->format('Y-m-d'),
            'to'      => $effectiveTo->format('Y-m-d'),
        ]);
    }

    public function destroy($rateCode, $id)
    {
        $daily = RatePlanDaily::where('rate_code', $rateCode)
            ->where('id', $id)
            ->first();

        if (!$daily) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found',
            ], 404);
        }

        $daily->delete();

        return response()->json([
            'success' => true,
            'message' => 'Record deleted successfully',
        ]);
    }

    public function destroyRange(Request $request, $rateCode)
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $count = RatePlanDaily::where('rate_code', $rateCode)
            ->whereBetween('date', [$validated['from'], $validated['to']])
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Deleted {$count} records",
            'count'   => $count,
        ]);
    }
}