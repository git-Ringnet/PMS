<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryDailyLog;
use App\Models\InventoryTransfer;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryTransferController extends Controller
{
    /**
     * POST /api/inventory/transfer
     * Chuyển hàng từ kho A sang kho B
     * Business rules:
     *   - quantity <= tồn cuối hiện tại của kho nguồn
     *   - Insert 1 dòng SP6008 (transfer record)
     *   - Upsert 2 dòng SP6007: kho A tăng transfer, kho B tăng receive
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id'             => 'required|integer|exists:warehouses,id',
            'transfer_to_warehouse_id' => 'required|integer|exists:warehouses,id|different:warehouse_id',
            'product_id'               => 'required|integer|exists:products,id',
            'date'                     => 'required|date',
            'quantity'                 => 'required|numeric|min:0.001',
        ]);

        // ─── Validate: quantity <= tồn cuối của kho nguồn ───────────
        $currentBalance = $this->calculateCurrentBalance(
            $validated['warehouse_id'],
            $validated['product_id'],
            $validated['date']
        );

        if ($validated['quantity'] > $currentBalance) {
            return response()->json([
                'success' => false,
                'message' => "Số lượng chuyển ({$validated['quantity']}) vượt quá tồn hiện tại ({$currentBalance}).",
            ], 422);
        }

        DB::transaction(function () use ($validated) {
            $hour = now()->format('H:i');

            // 1. Ghi vào bảng chuyển kho (SP6008)
            InventoryTransfer::create([
                'warehouse_id'             => $validated['warehouse_id'],
                'transfer_to_warehouse_id' => $validated['transfer_to_warehouse_id'],
                'product_id'               => $validated['product_id'],
                'date'                     => $validated['date'],
                'quantity'                 => $validated['quantity'],
                'hour'                     => $hour,
                'created_by'               => auth()->id(),
            ]);

            // 2. Kho nguồn: tăng cột transfer (xuất chuyển)
            $this->upsertLog(
                $validated['warehouse_id'],
                $validated['date'],
                $validated['product_id'],
                'transfer',
                $validated['quantity']
            );

            // 3. Kho đích: tăng cột receive (nhập chuyển)
            $this->upsertLog(
                $validated['transfer_to_warehouse_id'],
                $validated['date'],
                $validated['product_id'],
                'receive',
                $validated['quantity']
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Chuyển kho thành công.',
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────

    /**
     * Tính tồn cuối tại ngày cụ thể cho 1 sản phẩm trong 1 kho
     * Tồn cuối = FinalBalance (tháng kiểm kê) + Σreceive - Σexport - Σtransfer từ đầu tháng đến nay
     */
    private function calculateCurrentBalance(int $warehouseId, int $productId, string $date): float
    {
        $month = substr($date, 0, 7); // YYYY-MM

        // Tìm final_balance từ phiếu kiểm kê tháng gần nhất <= tháng hiện tại
        $checkItem = DB::table('inventory_checks as c')
            ->join('inventory_check_items as ci', 'ci.check_id', '=', 'c.id')
            ->where('c.warehouse_id', $warehouseId)
            ->where('ci.product_id', $productId)
            ->where('c.month', '<=', $month)
            ->whereNull('c.deleted_at')
            ->orderByDesc('c.month')
            ->select('ci.final_balance', 'c.month')
            ->first();

        $openBalance  = $checkItem?->final_balance ?? 0;
        $checkMonth   = $checkItem?->month ?? $month; // Tính từ tháng kiểm kê

        // Tổng nhật ký từ đầu checkMonth đến ngày $date
        $logs = InventoryDailyLog::where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->where('date', '>=', $checkMonth . '-01')
            ->where('date', '<=', $date)
            ->get();

        $totalReceive  = $logs->sum('receive');
        $totalExport   = $logs->sum('export');
        $totalTransfer = $logs->sum('transfer');

        return max(0, $openBalance + $totalReceive - $totalExport - $totalTransfer);
    }

    private function upsertLog(int $warehouseId, string $date, int $productId, string $field, float $qty): void
    {
        $existing = InventoryDailyLog::where([
            'warehouse_id' => $warehouseId,
            'date'         => $date,
            'product_id'   => $productId,
        ])->first();

        if ($existing) {
            $existing->$field = ($existing->$field ?? 0) + $qty;
            $existing->save();
        } else {
            InventoryDailyLog::create([
                'warehouse_id' => $warehouseId,
                'date'         => $date,
                'product_id'   => $productId,
                $field         => $qty,
            ]);
        }
    }
}
