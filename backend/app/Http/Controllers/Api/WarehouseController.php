<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    /** GET /api/warehouses */
    public function index(Request $request)
    {
        // Dòng 372 (23/11): ?hidden=1 trả các kho đã ẩn để hiện lại
        $warehouses = Warehouse::where('is_active', !$request->boolean('hidden'))
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $warehouses]);
    }

    /** POST /api/warehouses */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'outlet_id'  => 'nullable',
            'outlet_ids' => 'nullable|array',
        ]);

        if ($request->has('outlet_ids')) {
            $ids = is_array($request->outlet_ids) ? $request->outlet_ids : explode(',', (string) $request->outlet_ids);
            $validated['outlet_id'] = implode(',', array_values(array_filter(array_map('trim', $ids))));
        } elseif ($request->has('outlet_id') && is_array($request->outlet_id)) {
            $validated['outlet_id'] = implode(',', array_values(array_filter(array_map('trim', $request->outlet_id))));
        }

        $warehouse = Warehouse::create($validated);

        return response()->json(['success' => true, 'data' => $warehouse], 201);
    }

    /** PUT /api/warehouses/{id} */
    public function update(Request $request, $id)
    {
        $warehouse = Warehouse::findOrFail($id);

        $validated = $request->validate([
            'name'       => 'sometimes|string|max:100',
            'outlet_id'  => 'nullable',
            'outlet_ids' => 'nullable|array',
            'is_active'  => 'sometimes|boolean',
        ]);

        if ($request->has('outlet_ids')) {
            $ids = is_array($request->outlet_ids) ? $request->outlet_ids : explode(',', (string) $request->outlet_ids);
            $validated['outlet_id'] = implode(',', array_values(array_filter(array_map('trim', $ids))));
        } elseif ($request->has('outlet_id') && is_array($request->outlet_id)) {
            $validated['outlet_id'] = implode(',', array_values(array_filter(array_map('trim', $request->outlet_id))));
        }

        $warehouse->update($validated);

        return response()->json(['success' => true, 'data' => $warehouse]);
    }

    /** DELETE /api/warehouses/{id} */
    public function destroy($id)
    {
        $warehouse = Warehouse::findOrFail($id);

        // Dòng 372 (note 4/11): đã có phiếu kiểm kê thì không xóa dù chưa có nhập/xuất chi tiết
        if ($warehouse->hasLogs() || $warehouse->hasChecks()) {
            return response()->json([
                'success' => false,
                'message' => $warehouse->hasLogs()
                    ? 'Kho đã phát sinh dữ liệu nhập/xuất, không thể xóa. Hãy ẩn kho thay thế.'
                    : 'Kho đã có phiếu kiểm kê, không thể xóa. Hãy ẩn kho thay thế.',
            ], 422);
        }

        $warehouse->delete();

        return response()->json(['success' => true]);
    }
}
