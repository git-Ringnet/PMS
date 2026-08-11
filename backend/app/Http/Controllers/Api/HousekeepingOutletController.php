<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HousekeepingOutlet;
use App\Models\HousekeepingServiceBill;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ServiceBill;
use Illuminate\Http\Request;

class HousekeepingOutletController extends Controller
{
    public function index()
    {
        return response()->json(HousekeepingOutlet::orderBy('order_index')->orderBy('id')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:housekeeping_outlets,code',
            'name' => 'required|string|max:100',
            'group_key' => 'required|string|max:30|unique:housekeeping_outlets,group_key',
            'service_code' => 'nullable|string|max:30',
            'is_active' => 'boolean',
            'order_index' => 'nullable|integer|min:0',
        ]);

        return response()->json(HousekeepingOutlet::create($data), 201);
    }

    public function show(HousekeepingOutlet $housekeepingOutlet)
    {
        return response()->json($housekeepingOutlet);
    }

    public function update(Request $request, HousekeepingOutlet $housekeepingOutlet)
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:housekeeping_outlets,code,' . $housekeepingOutlet->id,
            'name' => 'required|string|max:100',
            'group_key' => 'required|string|max:30|unique:housekeeping_outlets,group_key,' . $housekeepingOutlet->id,
            'service_code' => 'nullable|string|max:30',
            'is_active' => 'boolean',
            'order_index' => 'nullable|integer|min:0',
        ]);

        $housekeepingOutlet->update($data);
        return response()->json($housekeepingOutlet->fresh());
    }

    public function destroy(HousekeepingOutlet $housekeepingOutlet)
    {
        $housekeepingOutlet->update(['is_active' => false]);
        return response()->json(['message' => 'Đã ngừng outlet HK']);
    }

    public function forceDestroy(HousekeepingOutlet $housekeepingOutlet)
    {
        $values = array_values(array_unique(array_filter([
            $housekeepingOutlet->code,
            $housekeepingOutlet->name,
            $housekeepingOutlet->group_key,
        ])));

        $categoryQuery = ProductCategory::whereIn('outlet', $values);
        $categoryCount = (clone $categoryQuery)->count();
        $productCount = Product::whereHas('category', fn ($query) => $query->whereIn('outlet', $values))->count();
        $billCount = ServiceBill::whereIn('Outlet', $values)->count()
            + HousekeepingServiceBill::whereIn('Outlet', $values)->count();

        if ($categoryCount || $productCount || $billCount) {
            return response()->json([
                'message' => 'Không thể xóa vĩnh viễn outlet đã phát sinh dữ liệu. Vui lòng tắt outlet.',
                'references' => [
                    'product_groups' => $categoryCount,
                    'products' => $productCount,
                    'bills' => $billCount,
                ],
            ], 422);
        }

        $housekeepingOutlet->delete();
        return response()->json(['message' => 'Đã xóa vĩnh viễn outlet HK']);
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|exists:housekeeping_outlets,id',
            'orders.*.order_index' => 'required|integer|min:0',
        ]);

        foreach ($data['orders'] as $order) {
            HousekeepingOutlet::whereKey($order['id'])->update(['order_index' => $order['order_index']]);
        }

        return response()->json(['message' => 'Đã cập nhật thứ tự outlet HK']);
    }
}
