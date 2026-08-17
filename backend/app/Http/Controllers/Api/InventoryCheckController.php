<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryCheck;
use App\Models\InventoryCheckItem;
use App\Models\InventoryDailyLog;
use App\Models\Product;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InventoryCheckController extends Controller
{
    /**
     * POST /api/inventory/checks/sync-previous-month
     * Lấy danh sách sản phẩm và Tồn cuối của tháng trước điền vào Tồn đầu kỳ & Thực tế của tháng mới
     */
    public function syncPreviousMonth(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'month'        => 'required|string|size:7', // YYYY-MM
        ]);

        $currentCarbon = Carbon::createFromFormat('Y-m', $validated['month']);
        $prevMonthStr  = $currentCarbon->copy()->subMonth()->format('Y-m');

        // 1. Tìm phiếu kiểm kê của tháng trước
        $prevCheck = InventoryCheck::with('items.product')
            ->where('warehouse_id', $validated['warehouse_id'])
            ->where('month', $prevMonthStr)
            ->first();

        // 2. Lấy nhật ký phát sinh (Nhập / Xuất / Chuyển) của tháng trước
        $prevLogs = InventoryDailyLog::where('warehouse_id', $validated['warehouse_id'])
            ->where('date', 'like', $prevMonthStr . '%')
            ->get()
            ->groupBy('product_id');

        // 3. Gom danh sách product_id từ tháng trước
        $productIds = [];
        if ($prevCheck) {
            $productIds = $prevCheck->items->pluck('product_id')->toArray();
        }
        foreach ($prevLogs->keys() as $pId) {
            $productIds[] = (int)$pId;
        }
        $productIds = array_values(array_unique(array_filter($productIds)));

        if (empty($productIds)) {
            return response()->json([
                'success' => false,
                'message' => "Không tìm thấy dữ liệu tồn kho nào từ tháng trước ({$prevMonthStr}).",
            ], 422);
        }

        // 4. Tìm hoặc tạo phiếu kiểm kê cho tháng hiện tại
        $check = InventoryCheck::withTrashed()
            ->where('warehouse_id', $validated['warehouse_id'])
            ->where('month', $validated['month'])
            ->first();

        if ($check) {
            if ($check->trashed()) {
                $check->restore();
            }
        } else {
            $check = InventoryCheck::create([
                'warehouse_id' => $validated['warehouse_id'],
                'month'        => $validated['month'],
                'created_by'   => auth()->id(),
            ]);
        }

        $prevItemsMap = $prevCheck ? $prevCheck->items->keyBy('product_id') : collect();

        $syncedCount = 0;
        foreach ($productIds as $pId) {
            $prevItem = $prevItemsMap->get($pId);
            $initialStock = 0;
            if ($prevItem) {
                $initialStock = (float)($prevItem->stoke_take !== null && $prevItem->stoke_take !== '' ? $prevItem->stoke_take : $prevItem->well_balance);
            }

            $prodLogs = $prevLogs->get($pId, collect());
            $totalReceive  = (float)$prodLogs->sum('receive');
            $totalExport   = (float)$prodLogs->sum('export');
            $totalTransfer = (float)$prodLogs->sum('transfer');

            // Tồn cuối tháng trước
            $finalBalancePrev = $initialStock + $totalReceive - $totalExport - $totalTransfer;

            $checkItem = InventoryCheckItem::firstOrNew([
                'check_id'   => $check->id,
                'product_id' => $pId,
            ]);

            $checkItem->well_balance  = $finalBalancePrev;
            $checkItem->stoke_take    = $finalBalancePrev;
            $checkItem->different_qty = 0;
            $checkItem->final_balance = $finalBalancePrev;
            if ($prevItem && $prevItem->unit) {
                $checkItem->unit = $prevItem->unit;
            }
            $checkItem->save();
            $syncedCount++;
        }

        $check->load(['items.product.category', 'creator']);

        return response()->json([
            'success' => true,
            'message' => "Đã lấy số liệu tồn cuối từ tháng {$prevMonthStr} sang tháng {$validated['month']} cho {$syncedCount} sản phẩm.",
            'data'    => $this->formatCheck($check),
        ]);
    }

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

        $check = InventoryCheck::with(['items.product.category', 'creator'])
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
            'created_by'   => 'nullable|integer|exists:users,id',
        ]);

        // Kiểm tra đã có phiếu chưa (bao gồm cả phiếu đã xóa tạm)
        $existing = InventoryCheck::withTrashed()
            ->where('warehouse_id', $validated['warehouse_id'])
            ->where('month', $validated['month'])
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->update([
                'note' => $validated['note'] ?? $existing->note,
                'created_by' => $validated['created_by'] ?? $existing->created_by,
            ]);
            $existing->load(['items.product.category', 'creator']);
            return response()->json(['success' => true, 'data' => $this->formatCheck($existing)], 200);
        }

        $check = InventoryCheck::create([
            'warehouse_id' => $validated['warehouse_id'],
            'month'        => $validated['month'],
            'note'         => $validated['note'] ?? null,
            'created_by'   => $validated['created_by'] ?? auth()->id(),
        ]);

        $check->load(['items.product.category', 'creator']);

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

        $check->load(['items.product.category', 'creator']);

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
        if (isset($validated['well_balance'])) {
            $item->final_balance = $validated['well_balance'];
            if (($item->stoke_take == 0 || $item->stoke_take === null) && !isset($validated['stoke_take'])) {
                $item->stoke_take = $validated['well_balance'];
            }
        } elseif (isset($validated['stoke_take']) && ($item->well_balance == 0 || $item->well_balance === null)) {
            $item->well_balance = $validated['stoke_take'];
            $item->final_balance = $validated['stoke_take'];
        }

        $item->save();
        $item->load(['product.category']);

        return response()->json([
            'success' => true,
            'data' => [
                'id'           => $item->id,
                'product_id'   => $item->product_id,
                'product_code' => $item->product?->product_code ?: $item->product_id,
                'product_name' => $item->product?->name,
                'unit'         => $item->unit ?: ($item->product?->unit ?: 'Cái'),
                'well_balance' => $item->well_balance,
                'stoke_take'   => $item->stoke_take,
                'different_qty'=> $item->different_qty,
                'final_balance'=> $item->final_balance,
                'note'         => $item->note,
            ]
        ]);
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

        $check->items()->delete();
        $check->forceDelete();

        return response()->json(['success' => true]);
    }

    public function productsInStock(Request $request)
    {
        $hkOutlets = \App\Models\HousekeepingOutlet::where('is_active', true)
            ->orderBy('order_index')
            ->get();

        $categories = \App\Models\ProductCategory::with(['products' => function($q) {
            $q->where('is_active', true)
              ->where(function($sub) {
                  $sub->where('is_in_stock', true)
                      ->orWhere('track_stock', true);
              })
              ->orderBy('name');
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

    public function exportExcel($id)
    {
        $check = InventoryCheck::with(['items.product.category', 'warehouse', 'creator'])->findOrFail($id);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Title
        $sheet->setCellValue('A1', 'PHIẾU KIỂM KÊ TỒN KHO ĐỊNH KỲ');
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Metadata
        $sheet->setCellValue('A3', 'Mã kiểm kê:');
        $sheet->setCellValue('B3', 'KK-' . $check->id);
        $sheet->setCellValue('A4', 'Tháng/Năm:');
        $sheet->setCellValue('B4', $check->month);
        $sheet->setCellValue('D3', 'Kho:');
        $sheet->setCellValue('E3', $check->warehouse?->name);
        $sheet->setCellValue('D4', 'Người kiểm kho:');
        $sheet->setCellValue('E4', $check->creator?->name ?: '—');
        $sheet->setCellValue('G3', 'Ghi chú:');
        $sheet->setCellValue('H3', $check->note ?: '—');

        // Headers
        $headers = [
            'STT', 'Mã kiểm kê', 'Mã SP', 'Tên SP', 'Đơn Vị', 'Tồn Đầu Kỳ', 'Số Lượng Thực Tế', 'Số Chênh Lệch', 'Ghi Chú'
        ];
        $sheet->fromArray($headers, null, 'A6');
        $sheet->getStyle('A6:I6')->getFont()->setBold(true);

        // Data
        $row = 7;
        $stt = 1;
        foreach ($check->items as $item) {
            $sheet->fromArray([
                $stt++,
                'KK-' . $check->id,
                $item->product?->product_code ?: $item->product_id,
                $item->product?->name,
                $item->unit ?: ($item->product?->unit ?: 'Cái'),
                $item->well_balance,
                $item->stoke_take,
                $item->different_qty,
                $item->note ?: ''
            ], null, 'A' . $row);
            $row++;
        }

        // Auto size columns
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $fileName = 'phieu_kiem_ke_' . $check->month . '.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function formatCheck(InventoryCheck $check): array
    {
        $items = $check->items->map(fn($item) => [
            'id'           => $item->id,
            'product_id'   => $item->product_id,
            'product_code' => $item->product?->product_code ?: $item->product_id,
            'product_name' => $item->product?->name ?? '',
            'unit'         => $item->unit ?: ($item->product?->unit ?: 'Cái'),
            'well_balance' => $item->well_balance,
            'stoke_take'   => $item->stoke_take,
            'different_qty'=> $item->different_qty,
            'final_balance'=> $item->final_balance,
            'note'         => $item->note,
        ])->sortBy('product_name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return [
            'id'           => $check->id,
            'warehouse_id' => $check->warehouse_id,
            'month'        => $check->month,
            'note'         => $check->note,
            'created_by'   => $check->created_by,
            'creator_name' => $check->creator?->name,
            'created_at'   => $check->created_at?->toDateTimeString(),
            'items'        => $items,
        ];
    }
}
