<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trang Tổng quan (dashboard) module Báo cáo quản lý.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardOverviewService $service)
    {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->overview()]);
    }

    public function activityRooms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:arrivals,departures,inhouse',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->activityRooms($validated['type']),
        ]);
    }
}
