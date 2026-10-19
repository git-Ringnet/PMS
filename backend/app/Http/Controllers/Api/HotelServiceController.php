<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelServiceResource;
use App\Models\HotelService;
use Illuminate\Http\Request;

class HotelServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $services = HotelService::with(['department', 'departments'])->get();
        return response()->json([
            'success' => true,
            'data' => HotelServiceResource::collection($services)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:hotel_services,code',
            'name' => 'required|string|max:255',
            'service_charge' => 'nullable|numeric|min:0|max:100',
            'tax' => 'nullable|numeric|min:0|max:100',
            'special_tax' => 'nullable|numeric|min:0|max:100',
            'include_service_charge' => 'nullable|boolean',
            'include_tax' => 'nullable|boolean',
            'include_special_tax' => 'nullable|boolean',
            'folio' => 'nullable|integer',
            'short_name' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
            'price' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            // department_id remains the primary department for legacy reports/data.
            // department_ids is the full list used to decide where a service can be posted.
            'department_id' => 'nullable|integer|exists:departments,id',
            'department_ids' => 'required|array|min:1',
            'department_ids.*' => 'integer|distinct|exists:departments,id',
        ]);

        $departmentIds = $validated['department_ids'];
        $validated['department_id'] = $validated['department_id'] ?? $departmentIds[0];
        unset($validated['department_ids']);

        $service = HotelService::create($validated);
        $service->departments()->sync($departmentIds);
        $service->load(['department', 'departments']);

        return response()->json([
            'success' => true,
            'data' => new HotelServiceResource($service)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $service = HotelService::with(['department', 'departments'])->find($id);
        if (!$service) {
            return response()->json(['message' => 'Service not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new HotelServiceResource($service)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $service = HotelService::find($id);
        if (!$service) {
            return response()->json(['message' => 'Service not found'], 404);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:hotel_services,code,' . $service->id,
            'name' => 'required|string|max:255',
            'service_charge' => 'nullable|numeric|min:0|max:100',
            'tax' => 'nullable|numeric|min:0|max:100',
            'special_tax' => 'nullable|numeric|min:0|max:100',
            'include_service_charge' => 'nullable|boolean',
            'include_tax' => 'nullable|boolean',
            'include_special_tax' => 'nullable|boolean',
            'folio' => 'nullable|integer',
            'short_name' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
            'price' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'department_id' => 'nullable|integer|exists:departments,id',
            'department_ids' => 'required|array|min:1',
            'department_ids.*' => 'integer|distinct|exists:departments,id',
        ]);

        $departmentIds = $validated['department_ids'];
        $validated['department_id'] = $validated['department_id'] ?? $departmentIds[0];
        unset($validated['department_ids']);

        app(\App\Services\ConfigurationReferenceGuard::class)->assertIdentityUnchanged($service, $validated, ['code'], true);
        $service->update($validated);
        $service->departments()->sync($departmentIds);
        $service->load(['department', 'departments']);

        return response()->json([
            'success' => true,
            'data' => new HotelServiceResource($service)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $service = HotelService::find($id);
        if (!$service) {
            return response()->json(['message' => 'Service not found'], 404);
        }

        app(\App\Services\ConfigurationReferenceGuard::class)->assertUnused($service);
        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service deleted successfully'
        ]);
    }
}
