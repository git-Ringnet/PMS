<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryDailyLog;
use App\Models\InventoryTransfer;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryLogController extends Controller
{
    /**
     * GET /api/inventory/logs?warehouse_id=&month=YYYY-MM
     * Lấy toàn bộ nhật ký nhập/xuất/chuyển của kho trong tháng
     * Trả về dạng: { product_id: { date: { receive, export, transfer } } }
     */
    public function index(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|integer',
            'month'        => 'required|string|size:7', // YYYY-MM
        ]);

        $logs = InventoryDailyLog::where('warehouse_id', $request->warehouse_id)
            ->where('date', 'like', $request->month . '%')
            ->get();

        // Nhóm theo product_id → date
        $grouped = [];
        foreach ($logs as $log) {
            $grouped[$log->product_id][$log->date] = [
                'receive'  => $log->receive,
                'export'   => $log->export,
                'transfer' => $log->transfer,
            ];
        }

        return response()->json(['success' => true, 'data' => $grouped]);
    }

    /**
     * PUT /api/inventory/logs
     * Upsert 1 ô nhật ký (warehouse + date + product)
     * Body: { warehouse_id, date, product_id, receive?, export?, transfer? }
     */
    public function upsert(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'date'         => 'required|date',
            'product_id'   => 'required|integer|exists:products,id',
            'receive'      => 'nullable|numeric|min:0',
            'export'       => 'nullable|numeric|min:0',
            'transfer'     => 'nullable|numeric|min:0',
        ]);

        $key = [
            'warehouse_id' => $validated['warehouse_id'],
            'date'         => $validated['date'],
            'product_id'   => $validated['product_id'],
        ];

        $values = array_filter([
            'receive'  => $validated['receive'] ?? null,
            'export'   => $validated['export'] ?? null,
            'transfer' => $validated['transfer'] ?? null,
        ], fn($v) => $v !== null);

        $log = InventoryDailyLog::updateOrCreate($key, $values);

        return response()->json(['success' => true, 'data' => $log]);
    }

    /**
     * POST /api/inventory/get-bill
     * Tự động lấy số lượng đã bán từ HK bills (SP6000/SP6001) để điền vào cột Xuất
     *
     * Body: { warehouse_id, date: YYYY-MM-DD }
     * Logic:
     *   1. Lấy outlet_id của kho (SP5409)
     *   2. Query SP6000 (bill header) theo outlet + date + status != 0 (chưa xóa)
     *   3. JOIN SP6001 (chi tiết) lấy MaProduct + SUM(Quantity) — bỏ Deleted=1
     *   4. Upsert vào inventory_daily_logs (cột export)
     */
    public function getBill(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'date'         => 'required|date',
        ]);

        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);

        if (empty($warehouse->outlet_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Kho này chưa gán Outlet. Vui lòng cập nhật thông tin kho.',
            ], 422);
        }

        // Query tổng số lượng bán theo sản phẩm từ HK bills (SP6000/SP6001)
        // Bảng: housekeeping_service_bills (Ma, Outlet, Date, Status)
        //        housekeeping_service_bill_details (BillId, MaProduct, Quantity, Deleted)
        $billData = DB::table('housekeeping_service_bills as b')
            ->join('housekeeping_service_bill_details as i', 'i.BillId', '=', 'b.Ma')
            ->where('b.Outlet', $warehouse->outlet_id)
            ->whereDate('b.Date', $validated['date'])
            ->where('b.Status', '!=', 0)   // bill chưa bị xóa
            ->where('i.Deleted', 0)         // item chưa bị xóa
            ->groupBy('i.MaProduct')
            ->select('i.MaProduct as product_id', DB::raw('SUM(i.Quantity) as total_qty'))
            ->get();

        if ($billData->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Không có dữ liệu hóa đơn cho ngày và kho này.',
                'updated' => 0,
            ]);
        }

        $updated = 0;
        foreach ($billData as $row) {
            InventoryDailyLog::updateOrCreate(
                [
                    'warehouse_id' => $validated['warehouse_id'],
                    'date'         => $validated['date'],
                    'product_id'   => $row->product_id,
                ],
                ['export' => $row->total_qty]
            );
            $updated++;
        }

        return response()->json([
            'success' => true,
            'message' => "Đã cập nhật xuất kho cho {$updated} sản phẩm từ hóa đơn.",
            'updated' => $updated,
        ]);
    }
}
