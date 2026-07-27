<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FbPromotion;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogService;

class FbPromotionController extends Controller
{
    public function index(Request $request)
    {
        $promotions = FbPromotion::with(['products.product.outletPrices', 'outlet'])->get();
        return response()->json($promotions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'outlet_id' => 'nullable|integer',
            'discount_percent' => 'nullable|numeric',
            'increase_percent' => 'nullable|numeric',
            'discount_amount' => 'nullable|numeric',
            'increase_amount' => 'nullable|numeric',
            'is_auto_apply' => 'boolean',
            'is_active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'apply_by_time' => 'boolean',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'company_id' => 'nullable|integer',
            'customer_source_id' => 'nullable|integer',
            'is_all_product' => 'boolean',
            'products' => 'nullable|array'
        ]);

        DB::beginTransaction();
        try {
            $promotion = FbPromotion::create($validated);

            if (!$promotion->is_all_product && !empty($validated['products'])) {
                $productsData = array_map(function($productId) use ($promotion) {
                    return [
                        'fb_promotion_id' => $promotion->id,
                        'fb_product_id' => $productId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }, $validated['products']);
                $promotion->products()->insert($productsData);
            }

            DB::commit();

            DB::commit();

            ActivityLogService::logCreate(
                $request,
                $promotion,
                'fnb',
                'Khuyến mãi',
                'Thêm mới khuyến mãi: ' . $promotion->name
            );

            return response()->json($promotion->load('products.product.outletPrices'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error creating promotion', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $promotion = FbPromotion::with('products.product.outletPrices')->findOrFail($id);
        return response()->json($promotion);
    }

    public function update(Request $request, $id)
    {
        $promotion = FbPromotion::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'outlet_id' => 'nullable|integer',
            'discount_percent' => 'nullable|numeric',
            'increase_percent' => 'nullable|numeric',
            'discount_amount' => 'nullable|numeric',
            'increase_amount' => 'nullable|numeric',
            'is_auto_apply' => 'boolean',
            'is_active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'apply_by_time' => 'boolean',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'company_id' => 'nullable|integer',
            'customer_source_id' => 'nullable|integer',
            'is_all_product' => 'boolean',
            'products' => 'nullable|array'
        ]);

        DB::beginTransaction();
        try {
            $promotion->update($validated);

            if (!$promotion->is_all_product) {
                // Delete old products and insert new ones
                $promotion->products()->delete();
                if (!empty($validated['products'])) {
                    $productsData = array_map(function($productId) use ($promotion) {
                        return [
                            'fb_promotion_id' => $promotion->id,
                            'fb_product_id' => $productId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }, $validated['products']);
                    $promotion->products()->insert($productsData);
                }
            } else {
                $promotion->products()->delete();
            }

            DB::commit();

            DB::commit();

            ActivityLogService::logUpdate(
                $request,
                $promotion,
                $promotion->getOriginal(),
                'fnb',
                'Khuyến mãi',
                'Cập nhật khuyến mãi: ' . $promotion->name
            );

            return response()->json($promotion->load('products.product.outletPrices'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error updating promotion', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $promotion = FbPromotion::findOrFail($id);
        
        $reason = $request->input('reason', '');
        ActivityLogService::logDelete(
            $request,
            $promotion,
            'fnb',
            'Khuyến mãi',
            "Xóa khuyến mãi: {$promotion->name}. Lý do: {$reason}"
        );
        
        $promotion->products()->delete();
        $promotion->delete();

        return response()->json(null, 204);
    }
}
