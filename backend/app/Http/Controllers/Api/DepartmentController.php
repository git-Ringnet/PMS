<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Department;
use App\Models\HotelService;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::with(['hotelServices' => fn ($query) => $query->orderBy('code')])
            ->where('show', 1)
            ->orderBy('code')
            ->get();
        return response()->json([
            'success' => true,
            'data' => $departments
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:departments,code',
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:50',
        ]);

        $department = Department::create([
            ...$validated,
            'show' => 1
        ]);

        return response()->json([
            'success' => true,
            'data' => $department,
            'message' => 'Tạo phòng ban thành công!'
        ], 201);
    }

    public function attachService(Request $request, Department $department)
    {
        $validated = $request->validate([
            'hotel_service_id' => 'required|integer|exists:hotel_services,id',
            'description' => 'nullable|string|max:255',
        ]);

        $department->hotelServices()->syncWithoutDetaching([
            $validated['hotel_service_id'] => ['description' => $validated['description'] ?? null],
        ]);

        return response()->json(['success' => true]);
    }

    public function updateService(Request $request, Department $department, HotelService $hotelService)
    {
        $validated = $request->validate(['description' => 'nullable|string|max:255']);
        $department->hotelServices()->updateExistingPivot($hotelService->id, [
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    public function detachService(Department $department, HotelService $hotelService)
    {
        $department->hotelServices()->detach($hotelService->id);
        return response()->json(['success' => true]);
    }
}
