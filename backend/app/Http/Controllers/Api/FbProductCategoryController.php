<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FbProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FbProductCategoryController extends Controller
{
    public function index()
    {
        $categories = FbProductCategory::with(['products', 'parent'])->orderBy('order_index')->get();
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:fb_product_categories,id',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'order_index' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('product_categories', 'public');
            $validated['image'] = $path;
        }

        $category = FbProductCategory::create($validated);

        \App\Services\ActivityLogService::logCreate(
            $request,
            $category,
            'fnb',
            'FbProductCategoryController',
            "Tạo danh mục sản phẩm: {$category->name}",
            $category->code
        );

        return response()->json($category, 201);
    }

    public function update(Request $request, $id)
    {
        $category = FbProductCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:fb_product_categories,id',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'order_index' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $path = $request->file('image')->store('product_categories', 'public');
            $validated['image'] = $path;
        } elseif ($request->input('remove_image') == '1') {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $validated['image'] = null;
        }

        $category->update($validated);

        \App\Services\ActivityLogService::logUpdate(
            $request,
            $category,
            [],
            'fnb',
            'FbProductCategoryController',
            "Cập nhật danh mục sản phẩm: {$category->name}",
            $category->code
        );

        return response()->json($category);
    }

    public function destroy(Request $request, $id)
    {
        $category = FbProductCategory::findOrFail($id);

        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $reason = $request->input('reason', 'Không có lý do');
        \App\Services\ActivityLogService::logDelete(
            $request,
            $category,
            'fnb',
            'FbProductCategoryController',
            "Xoá danh mục sản phẩm: {$category->name} - Lý do: {$reason}",
            $category->code
        );

        $category->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
