<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryCheck;
use App\Models\InventoryCheckItem;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class InventoryCheckController extends Controller
{
    /**
     * GET /api/inventory/checks?warehouse_id=&month=YYYY-MM
     * Lấy phiếu kiểm kê + chi tiết sản phẩm
     */
    public function index(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|integer',
            'month'        => 'required|string|size:7', // YYYY-MM
        ]);

        $check = InventoryCheck::with(['items.product'])
            ->where('warehouse_id', $request->warehouse_id)
            ->where('month', $request->month)
            ->first();

        return response()->json([
            'success' => true,
            'data'    => $check ? $this->formatCheck($check) : null,
        ]);
    }

    /**
     * POST /api/inventory/checks
     * Tạo phiếu kiểm kê mới cho tháng (unique warehouse+month)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'month'        => 'required|string|size:7',
            'note'         => 'nullable|string',
        ]);

        // Kiểm tra đã có phiếu chưa
        $existing = InventoryCheck::where('warehouse_id', $validated['warehouse_id'])
            ->where('month', $validated['month'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Đã tồn tại phiếu kiểm kê cho kho và tháng này.',
            ], 422);
        }

        $check = InventoryCheck::create([
            'warehouse_id' => $validated['warehouse_id'],
            'month'        => $validated['month'],
            'note'         => $validated['note'] ?? null,
            'created_by'   => auth()->id(),
        ]);

        $check->load(['items.product']);

        return response()->json(['success' => true, 'data' => $this->formatCheck($check)], 201);
    }

    /**
     * POST /api/inventory/checks/{id}/items
     * Thêm sản phẩm vào phiếu kiểm kê (chỉ sản phẩm isInStock=1)
     * Body: { product_ids: [1, 2, 3] }
     */
    public function addItems(Request $request, $id)
    {
        $check = InventoryCheck::findOrFail($id);

        $validated = $request->validate([
            'product_ids'   => 'required|array|min:1',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        // Lấy danh sách ID sản phẩm hợp lệ từ mảng gửi lên
        $products = Product::whereIn('id', $validated['product_ids'])
            ->pluck('id');

        $added = 0;
        foreach ($products as $productId) {
            InventoryCheckItem::firstOrCreate(
                ['check_id' => $check->id, 'product_id' => $productId],
                ['well_balance' => 0, 'stoke_take' => 0, 'different_qty' => 0, 'final_balance' => 0]
            );
            $added++;
        }

        $check->load(['items.product']);

        return response()->json([
            'success' => true,
            'added'   => $added,
            'data'    => $this->formatCheck($check),
        ]);
    }

    /**
     * PUT /api/inventory/checks/{id}/items/{itemId}
     * Cập nhật tồn đầu kỳ (well_balance) và thực tế (stoke_take)
     * different_qty tự tính trong model event
     * final_balance = well_balance (set ban đầu, sau đó tự tính theo log tháng)
     */
    public function updateItem(Request $request, $id, $itemId)
    {
        $item = InventoryCheckItem::where('check_id', $id)->findOrFail($itemId);

        $validated = $request->validate([
            'well_balance' => 'sometimes|numeric|min:0',
            'stoke_take'   => 'sometimes|numeric|min:0',
            'unit'         => 'nullable|string|max:50',
            'note'         => 'nullable|string|max:200',
        ]);

        $item->fill($validated);

        // final_balance ban đầu = well_balance (sẽ được tính lại từ logs ở frontend)
        if (isset($validated['well_balance']) && !request('keep_final')) {
            $item->final_balance = $validated['well_balance'];
        }

        $item->save();

        return response()->json(['success' => true, 'data' => $item]);
    }

    /**
     * DELETE /api/inventory/checks/{id}
     * Xóa phiếu kiểm kê — chặn nếu đã phát sinh nhật ký trong tháng
     */
    public function destroy($id)
    {
        $check = InventoryCheck::findOrFail($id);

        if ($check->hasLogs()) {
            return response()->json([
                'success' => false,
                'message' => 'Tháng này đã phát sinh dữ liệu nhập/xuất tồn kho, không thể xóa phiếu kiểm kê.',
            ], 422);
        }

        $check->delete();

        return response()->json(['success' => true]);
    }

    public function productsInStock(Request $request)
    {
        $hkOutlets = \App\Models\HousekeepingOutlet::where('is_active', true)
            ->orderBy('order_index')
            ->get();

        $categories = \App\Models\ProductCategory::with(['products' => function($q) {
            $q->where('is_active', true)->orderBy('name');
        }])->get();

        $tree = [];
        foreach ($hkOutlets as $outlet) {
            $outletKeys = [
                strtolower($outlet->code),
                strtolower($outlet->name),
                strtolower($outlet->group_key)
            ];

            $outletCats = [];
            foreach ($categories as $cat) {
                $catOutlet = strtolower($cat->outlet ?? '');
                $catName = strtolower($cat->name ?? '');

                $matched = false;
                foreach ($outletKeys as $key) {
                    if ($catOutlet === $key || $catName === $key) {
                        $matched = true;
                        break;
                    }
                }

                if (!$matched) {
                    $tabMap = [
                        'minibar' => 'minibar',
                        'giặt ủi' => 'giatui',
                        'giatui' => 'giatui',
                        'hàng đền bù' => 'dengbu',
                        'dengbu' => 'dengbu',
                        'amenity' => 'amenity'
                    ];
                    if (isset($tabMap[$catOutlet]) && $tabMap[$catOutlet] === $outlet->group_key) {
                        $matched = true;
                    } elseif (isset($tabMap[$catName]) && $tabMap[$catName] === $outlet->group_key) {
                        $matched = true;
                    }
                }

                if ($matched && $cat->products->isNotEmpty()) {
                    $outletCats[] = [
                        'id' => $cat->id,
                        'name' => $cat->name,
                        'products' => $cat->products->map(fn($p) => [
                            'id' => $p->id,
                            'name' => $p->name,
                            'price' => $p->price
                        ])->values()->toArray()
                    ];
                }
            }

            if (!empty($outletCats)) {
                $tree[] = [
                    'id' => $outlet->id,
                    'code' => $outlet->code,
                    'name' => $outlet->name,
                    'group_key' => $outlet->group_key,
                    'categories' => $outletCats
                ];
            }
        }

        return response()->json(['success' => true, 'data' => $tree]);
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function formatCheck(InventoryCheck $check): array
    {
        return [
            'id'           => $check->id,
            'warehouse_id' => $check->warehouse_id,
            'month'        => $check->month,
            'note'         => $check->note,
            'created_at'   => $check->created_at?->toDateTimeString(),
            'items'        => $check->items->map(fn($item) => [
                'id'           => $item->id,
                'product_id'   => $item->product_id,
                'product_name' => $item->product?->name,
                'unit'         => $item->unit,
                'well_balance' => $item->well_balance,
                'stoke_take'   => $item->stoke_take,
                'different_qty'=> $item->different_qty,
                'final_balance'=> $item->final_balance,
                'note'         => $item->note,
            ])->values(),
        ];
    }
}
