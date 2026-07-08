<?php

namespace App\Http\Controllers;

use App\Models\FbPrintLog;
use Illuminate\Http\Request;

class FbPrintLogController extends Controller
{
    /**
     * Display a listing of the print logs for a specific order.
     */
    public function getByOrder($orderId)
    {
        $logs = FbPrintLog::where('order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->get();
            
        // If no data, we can return some dummy data to help UI design for now
        if ($logs->isEmpty()) {
            return response()->json([
                [
                    'id' => 9991,
                    'code' => 'PL-001',
                    'billCode' => 'HD' . str_pad($orderId, 6, '0', STR_PAD_LEFT),
                    'corderCode' => 'CO-1234',
                    'printer' => 'Máy in Bếp 1',
                    'printerType' => 'Bếp/Bar',
                    'status' => 'Thành công',
                    'printedDate' => now()->subMinutes(10)->format('d/m/Y H:i:s'),
                ],
                [
                    'id' => 9992,
                    'code' => 'PL-002',
                    'billCode' => 'HD' . str_pad($orderId, 6, '0', STR_PAD_LEFT),
                    'corderCode' => 'CO-1235',
                    'printer' => 'Máy in Bar',
                    'printerType' => 'Bếp/Bar',
                    'status' => 'Đang chờ',
                    'printedDate' => now()->subMinutes(2)->format('d/m/Y H:i:s'),
                ]
            ]);
        }

        $formatted = $logs->map(function($log) {
            return [
                'id' => $log->id,
                'code' => $log->id,
                'billCode' => 'HD' . str_pad($log->order_id, 6, '0', STR_PAD_LEFT),
                'corderCode' => $log->corder_code ?? '-',
                'printer' => $log->printer_name ?? 'Máy in mặc định',
                'printerType' => $log->printer_type == 1 ? 'Tem' : 'Bếp/Bar',
                'status' => $log->is_printed ? 'Thành công' : 'Đang chờ',
                'printedDate' => $log->printed_at ? $log->printed_at->format('d/m/Y H:i:s') : '-',
            ];
        });

        return response()->json($formatted);
    }
}
