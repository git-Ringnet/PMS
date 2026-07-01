<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Outlet;

class OutletController extends Controller
{
    public function index()
    {
        $outlets = Outlet::with(['department', 'service'])->orderBy('order_index', 'asc')->get();
        return response()->json($outlets);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:outlets,code',
            'name' => 'required|string|max:100',
            'department_code' => 'nullable|string|max:10',
            'service_code' => 'nullable|string|max:10',
            'is_active' => 'boolean',
            'order_index' => 'nullable|integer',
            'check_voucher' => 'boolean',
            'check_combo' => 'boolean',
            'account_number' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:100',
            'payment_content' => 'nullable|string|max:200',
            'connector' => 'nullable|string|max:100',
            'vat_config_id' => 'nullable|integer',
        ]);

        $outlet = Outlet::create($validated);

        return response()->json([
            'message' => 'Outlet created successfully',
            'data' => $outlet->load(['department', 'service'])
        ], 201);
    }

    public function show($id)
    {
        $outlet = Outlet::with(['department', 'service'])->findOrFail($id);
        return response()->json($outlet);
    }

    public function update(Request $request, $id)
    {
        $outlet = Outlet::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|unique:outlets,code,' . $id,
            'name' => 'required|string|max:100',
            'department_code' => 'nullable|string|max:10',
            'service_code' => 'nullable|string|max:10',
            'is_active' => 'boolean',
            'order_index' => 'nullable|integer',
            'check_voucher' => 'boolean',
            'check_combo' => 'boolean',
            'account_number' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:100',
            'payment_content' => 'nullable|string|max:200',
            'connector' => 'nullable|string|max:100',
            'vat_config_id' => 'nullable|integer',
        ]);

        $outlet->update($validated);

        return response()->json([
            'message' => 'Outlet updated successfully',
            'data' => $outlet->load(['department', 'service'])
        ]);
    }

    public function destroy($id)
    {
        $outlet = Outlet::findOrFail($id);
        $outlet->delete();

        return response()->json([
            'message' => 'Outlet deleted successfully'
        ]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|exists:outlets,id',
            'orders.*.order_index' => 'required|integer',
        ]);

        foreach ($request->orders as $order) {
            Outlet::where('id', $order['id'])->update(['order_index' => $order['order_index']]);
        }

        return response()->json(['message' => 'Outlets reordered successfully']);
    }
}
