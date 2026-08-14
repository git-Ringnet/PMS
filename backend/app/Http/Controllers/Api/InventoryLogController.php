<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryCheck;
use App\Models\InventoryCheckItem;
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
     *   2. Query SP6000 (bill header) theo outlet + date + status != 2 (chưa xóa)
     *   3. JOIN SP6001 (chi tiết) lấy MaProduct + SUM(Quantity) — bỏ Deleted=1
     *   4. Filter chỉ lấy các sản phẩm có trong inventory_check_items của kho
     *   5. Upsert vào inventory_daily_logs (cột export)
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

        $month = substr($validated['date'], 0, 7);
        $check = InventoryCheck::where('warehouse_id', $validated['warehouse_id'])
            ->where('month', $month)
            ->first();

        if (!$check) {
            return response()->json([
                'success' => false,
                'message' => "Chưa có bảng kiểm kê tháng {$month} cho kho này.",
            ], 422);
        }

        $allowedProductIds = InventoryCheckItem::where('check_id', $check->id)
            ->pluck('product_id')
            ->toArray();

        $billData = DB::table('housekeeping_service_bills as b')
            ->join('housekeeping_service_bill_details as i', 'i.BillId', '=', 'b.Ma')
            ->where('b.Outlet', $warehouse->outlet_id)
            ->whereDate('b.Date', $validated['date'])
            ->where('b.Status', '!=', 2)   // bill chưa bị xóa
            ->where('i.Deleted', 0)         // item chưa bị xóa
            ->whereIn('i.MaProduct', $allowedProductIds)
            ->groupBy('i.MaProduct')
            ->select('i.MaProduct as product_id', DB::raw('SUM(i.Quantity) as total_qty'))
            ->get();

        if ($billData->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hóa đơn hợp lệ nào cho các sản phẩm đã kiểm kê trong ngày này.',
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

    /**
     * GET /api/inventory/logs/export?warehouse_id=&month=YYYY-MM
     * Xuất excel nhật ký kho cả tháng
     */
    public function exportExcel(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|integer',
            'month'        => 'required|string|size:7', // YYYY-MM
        ]);

        $warehouseId = $request->warehouse_id;
        $month = $request->month;

        $warehouse = Warehouse::findOrFail($warehouseId);
        $check = \App\Models\InventoryCheck::with(['items.product'])
            ->where('warehouse_id', $warehouseId)
            ->where('month', $month)
            ->first();

        $logs = InventoryDailyLog::where('warehouse_id', $warehouseId)
            ->where('date', 'like', $month . '%')
            ->get();

        $daysInMonth = \Carbon\Carbon::parse($month . '-01')->daysInMonth;

        $logsGrouped = [];
        foreach ($logs as $log) {
            $day = (int) substr($log->date, 8, 2);
            $logsGrouped[$log->product_id][$day] = [
                'receive'  => $log->receive,
                'export'   => $log->export,
                'transfer' => $log->transfer,
            ];
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Title
        $sheet->setCellValue('A1', 'BẢNG NHẬT KÝ KIỂM KÊ VÀ LƯU CHUYỂN TỒN KHO');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->setCellValue('A2', "Kho hàng: {$warehouse->name} | Tháng: {$month}");
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(11);

        // Header styles
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0EA5E9']
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'DDDDDD']
                ]
            ]
        ];

        // Draw headers
        $sheet->setCellValue('A4', 'STT');
        $sheet->mergeCells('A4:A5');
        
        $sheet->setCellValue('B4', 'Mã SP');
        $sheet->mergeCells('B4:B5');
        
        $sheet->setCellValue('C4', 'Tên SP');
        $sheet->mergeCells('C4:C5');
        
        $sheet->setCellValue('D4', 'Đơn vị');
        $sheet->mergeCells('D4:D5');
        
        $sheet->setCellValue('E4', 'Tồn ĐK');
        $sheet->mergeCells('E4:E5');

        // Draw Days
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $startColIdx = 6 + 3 * ($d - 1);
            $startColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIdx);
            $endColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIdx + 2);
            
            $cellCoord = $startColLetter . '4';
            $sheet->setCellValue($cellCoord, "Ngày {$d}");
            $sheet->mergeCells("{$startColLetter}4:{$endColLetter}4");

            // Sub headers in row 5
            $sheet->setCellValue($startColLetter . '5', 'Nhập');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIdx + 1) . '5', 'Xuất');
            $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIdx + 2) . '5', 'Chuyển');

            // Apply styles based on even/odd day column
            $dayStyle = $headerStyle;
            if ($d % 2 === 0) {
                $dayStyle['fill']['startColor']['rgb'] = '0284C7'; // Darker sky blue for alternate days
            }
            $sheet->getStyle("{$startColLetter}4:{$endColLetter}4")->applyFromArray($dayStyle);
            $sheet->getStyle("{$startColLetter}5:" . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIdx + 2) . '5')->applyFromArray($dayStyle);
        }

        // Totals headers
        $totalReceiveColIdx = 6 + 3 * $daysInMonth;
        $totalReceiveCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalReceiveColIdx);
        $totalExportCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalReceiveColIdx + 1);
        $totalTransferCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalReceiveColIdx + 2);
        $finalStockCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalReceiveColIdx + 3);

        $sheet->setCellValue($totalReceiveCol . '4', 'SLN');
        $sheet->mergeCells("{$totalReceiveCol}4:{$totalReceiveCol}5");

        $sheet->setCellValue($totalExportCol . '4', 'SLX');
        $sheet->mergeCells("{$totalExportCol}4:{$totalExportCol}5");

        $sheet->setCellValue($totalTransferCol . '4', 'SLC');
        $sheet->mergeCells("{$totalTransferCol}4:{$totalTransferCol}5");

        $sheet->setCellValue($finalStockCol . '4', 'Tồn Cuối');
        $sheet->mergeCells("{$finalStockCol}4:{$finalStockCol}5");

        // Apply styles to static headers
        $sheet->getStyle('A4:E5')->applyFromArray($headerStyle);
        $sheet->getStyle("{$totalReceiveCol}4:{$finalStockCol}5")->applyFromArray($headerStyle);

        // Fill data
        $row = 6;
        $stt = 1;
        if ($check) {
            foreach ($check->items as $item) {
                $prodId = $item->product_id;
                $productLogs = $logsGrouped[$prodId] ?? [];
                
                $totalReceive = 0;
                $totalExport = 0;
                $totalTransfer = 0;
                foreach ($productLogs as $dayLog) {
                    $totalReceive += $dayLog['receive'] ?? 0;
                    $totalExport += $dayLog['export'] ?? 0;
                    $totalTransfer += $dayLog['transfer'] ?? 0;
                }
                
                $initialBalance = $item->well_balance;
                $finalStock = $initialBalance + $totalReceive - $totalExport - $totalTransfer;

                // Write static cols
                $sheet->setCellValue('A' . $row, $stt);
                $sheet->setCellValue('B' . $row, $item->product?->product_code ?: $prodId);
                $sheet->setCellValue('C' . $row, $item->product?->name);
                $sheet->setCellValue('D' . $row, $item->unit ?: ($item->product?->unit ?: 'Cái'));
                $sheet->setCellValue('E' . $row, $initialBalance);

                // Write daily logs
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $startColIdx = 6 + 3 * ($d - 1);
                    $dayLog = $productLogs[$d] ?? [];
                    
                    $receive = $dayLog['receive'] ?? '';
                    $export = $dayLog['export'] ?? '';
                    $transfer = $dayLog['transfer'] ?? '';

                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIdx) . $row, $receive ?: '');
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIdx + 1) . $row, $export ?: '');
                    $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColIdx + 2) . $row, $transfer ?: '');
                }

                // Write totals
                $sheet->setCellValue($totalReceiveCol . $row, $totalReceive ?: '');
                $sheet->setCellValue($totalExportCol . $row, $totalExport ?: '');
                $sheet->setCellValue($totalTransferCol . $row, $totalTransfer ?: '');
                $sheet->setCellValue($finalStockCol . $row, $finalStock);

                // Apply borders and styling to this row
                $rowStyle = [
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => 'E2E8F0']
                        ]
                    ]
                ];
                $sheet->getStyle("A{$row}:{$finalStockCol}{$row}")->applyFromArray($rowStyle);
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

                $row++;
                $stt++;
            }
        }

        // Auto size columns
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);
        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('E')->setAutoSize(true);
        $sheet->getColumnDimension($totalReceiveCol)->setAutoSize(true);
        $sheet->getColumnDimension($totalExportCol)->setAutoSize(true);
        $sheet->getColumnDimension($totalTransferCol)->setAutoSize(true);
        $sheet->getColumnDimension($finalStockCol)->setAutoSize(true);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $fileName = 'nhat_ky_kho_' . $warehouse->name . '_' . $month . '.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
