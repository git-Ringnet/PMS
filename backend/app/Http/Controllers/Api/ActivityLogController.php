<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /** Bộ lọc Hành động → các giá trị action đã/đang lưu (log cũ dùng create/update/delete...). */
    private const ACTION_ALIASES = [
        'Add' => ['Add', 'New', 'create', 'AddService'],
        'Modify' => ['Modify', 'update'],
        'Cancel' => ['Cancel', 'delete', 'DeleteService'],
        'CheckIn' => ['CheckIn', 'check_in'],
        'CheckOut' => ['CheckOut', 'check_out'],
        'Payment' => ['Payment', 'payment'],
        'NoShow' => ['NoShow', 'no_show'],
        'Lock' => ['Lock', 'lock'],
        'Unlock' => ['Unlock', 'unlock'],
        'DayClose' => ['DayClose', 'day_close'],
        'Inventory' => ['Inventory', 'inventory'],
        'Report' => ['Report'],
        'Restore' => ['Restore'],
        'login' => ['login', 'login_failed'],
        'logout' => ['logout'],
    ];

    /**
     * Danh sách log có phân trang, lọc, sắp xếp.
     */
    public function index(Request $request)
    {
        $query = ActivityLog::query();

        // Lọc theo user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Lọc theo action (gộp các tên cũ / mới của cùng một hành động)
        if ($request->filled('action')) {
            $query->whereIn('action', self::ACTION_ALIASES[$request->action] ?? [$request->action]);
        }

        // Lọc theo module
        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        // Lọc theo component
        if ($request->filled('component')) {
            $query->where('component', $request->component);
        }

        // Lọc theo IP
        if ($request->filled('ip_address')) {
            $query->where('ip_address', 'like', '%' . $request->ip_address . '%');
        }

        // Lọc theo mã đăng ký: "22" hay "GAL22" đều khớp đúng đăng ký GAL22 (không khớp GAL220, số tiền...)
        if ($request->filled('registration_code')) {
            $numericId = preg_replace('/\D/', '', (string) $request->registration_code);
            if ($numericId === '') {
                $query->whereRaw('1 = 0');
            } else {
                $code = \App\Services\ActivityLogService::bookingPrefix() . ltrim($numericId, '0');
                $query->where(function ($q) use ($code, $numericId) {
                    // Log ghi trước khi đổi tiền tố (GAL -> NV) vẫn thuộc đúng đăng ký
                    $q->whereIn('booking_code', array_unique([$code, 'GAL' . ltrim($numericId, '0')]))
                        // Log cũ (chưa có cột booking_code)
                        ->orWhere(function ($legacy) use ($code, $numericId) {
                            $legacy->whereNull('booking_code')->where(function ($inner) use ($code, $numericId) {
                                $inner->where('target_label', $code)
                                    ->orWhere('target_label', 'like', "{$code} %")
                                    ->orWhere(fn ($b) => $b->where('target_type', 'Booking')->where('target_id', $numericId))
                                    ->orWhere('description', 'like', "%{$code} %")
                                    ->orWhere('description', 'like', "%{$code}:%")
                                    ->orWhere('description', 'like', "%{$code},%")
                                    ->orWhere('description', 'like', "%{$code}");
                            });
                        });
                });
            }
        }

        // Lọc theo mã phòng: nhập mã G (G0000048) hoặc số phòng (605)
        if ($request->filled('room_code')) {
            $roomCode = trim((string) $request->room_code);
            $isGCode = (bool) preg_match('/^G\d+$/i', $roomCode);
            // Số phòng → các mã G từng ở phòng đó (cột Mã phòng của log lưu mã G)
            $gCodes = $isGCode
                ? [strtoupper($roomCode)]
                : \App\Models\BookingRoom::withTrashed()->where('room_number', $roomCode)->pluck('id')->map(fn ($id) => (string) $id)->all();

            $query->where(function ($q) use ($roomCode, $gCodes, $isGCode) {
                foreach ($gCodes as $g) {
                    $q->orWhere('room_code', $g)
                        ->orWhere('room_code', 'like', "{$g},%")
                        ->orWhere('room_code', 'like', "%, {$g}")
                        ->orWhere('room_code', 'like', "%, {$g},%");
                }
                if (!$isGCode) {
                    // Log buồng phòng / khóa phòng lưu số phòng vật lý
                    $q->orWhere('room_code', $roomCode)
                        ->orWhere('room_code', 'like', "{$roomCode},%")
                        ->orWhere('room_code', 'like', "%, {$roomCode}")
                        ->orWhere('room_code', 'like', "%, {$roomCode},%");
                }
                // Log cũ (chưa có cột room_code)
                $q->orWhere(function ($legacy) use ($roomCode, $gCodes) {
                    $legacy->whereNull('room_code')->where(function ($inner) use ($roomCode, $gCodes) {
                        $inner->where(function ($sub) use ($roomCode, $gCodes) {
                            $sub->whereIn('target_type', ['Room', 'RoomLock', 'BookingRoom', 'BookingRoomService'])
                                ->where(fn ($t) => $t->where('target_id', $roomCode)->orWhereIn('target_id', $gCodes ?: ['-']));
                        })
                            ->orWhere('description', 'like', "%P.{$roomCode} %")
                            ->orWhere('description', 'like', "%P.{$roomCode})%");
                    });
                });
            });
        }

        // Lọc theo NGÀY HỆ THỐNG (ngày thao tác); log cũ chưa có system_date thì theo ngày ghi log
        if ($request->filled('date_from')) {
            $from = \Carbon\Carbon::createFromFormat('Y-m-d', $request->date_from, 'Asia/Ho_Chi_Minh')->startOfDay();
            $query->where(function ($q) use ($from) {
                $q->whereDate('system_date', '>=', $from->toDateString())
                    ->orWhere(fn ($legacy) => $legacy->whereNull('system_date')->where('created_at', '>=', $from));
            });
        }
        if ($request->filled('date_to')) {
            $to = \Carbon\Carbon::createFromFormat('Y-m-d', $request->date_to, 'Asia/Ho_Chi_Minh')->endOfDay();
            $query->where(function ($q) use ($to) {
                $q->whereDate('system_date', '<=', $to->toDateString())
                    ->orWhere(fn ($legacy) => $legacy->whereNull('system_date')->where('created_at', '<=', $to));
            });
        }

        // Tìm kiếm full-text
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('user_name', 'like', "%{$search}%")
                  ->orWhere('target_label', 'like', "%{$search}%")
                  ->orWhere('component', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        // Sắp xếp
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSort = ['id', 'created_at', 'system_date', 'user_name', 'action', 'module', 'component'];
        if (in_array($sortBy, $allowedSort)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        // Phân trang
        $perPage = min(max((int) $request->input('per_page', 30), 1), 500);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ActivityLogResource::collection($logs->items()),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Thống kê tổng quan.
     */
    public function stats()
    {
        $today = now()->timezone('Asia/Ho_Chi_Minh')->startOfDay();

        $totalToday = ActivityLog::where('created_at', '>=', $today)->count();
        $loginsToday = ActivityLog::where('created_at', '>=', $today)
            ->where('action', 'login')
            ->count();
        $failedLoginsToday = ActivityLog::where('created_at', '>=', $today)
            ->where('action', 'login_failed')
            ->count();

        // Top active users today
        $topUsers = ActivityLog::where('created_at', '>=', $today)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, user_name, COUNT(*) as action_count')
            ->groupBy('user_id', 'user_name')
            ->orderByDesc('action_count')
            ->limit(5)
            ->get();

        // Actions by module today
        $byModule = ActivityLog::where('created_at', '>=', $today)
            ->selectRaw('module, COUNT(*) as count')
            ->groupBy('module')
            ->orderByDesc('count')
            ->get()
            ->pluck('count', 'module');

        // Actions by type today
        $byAction = ActivityLog::where('created_at', '>=', $today)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->get()
            ->pluck('count', 'action');

        // Distinct users list (for filter dropdown)
        $allUsers = ActivityLog::whereNotNull('user_id')
            ->selectRaw('DISTINCT user_id, user_name, employee_code')
            ->orderBy('user_name')
            ->get();

        // Distinct components (for filter dropdown)
        $allComponents = ActivityLog::whereNotNull('component')
            ->selectRaw('DISTINCT component')
            ->orderBy('component')
            ->pluck('component');

        return response()->json([
            'success' => true,
            'data' => [
                'total_today' => $totalToday,
                'logins_today' => $loginsToday,
                'failed_logins_today' => $failedLoginsToday,
                'top_users' => $topUsers,
                'by_module' => $byModule,
                'by_action' => $byAction,
                'users_list' => $allUsers,
                'components_list' => $allComponents,
            ],
        ]);
    }

    /**
     * Checklist TC_HT_06 dòng 37: xuất nhật ký thao tác ra file .xlsx thật.
     * Trước đây xuất CSV nên Excel (máy dùng dấu ";" phân cách) dồn tất cả cột vào một ô.
     * Frontend gửi đúng các cột đang hiện, theo thứ tự user đã chọn (dòng 542) và giá trị đã định dạng.
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'headers' => 'required|array|min:1|max:30',
            'headers.*' => 'required|string|max:100',
            'rows' => 'present|array|max:20000',
            'rows.*' => 'array',
        ]);
        $headers = array_values($validated['headers']);
        $width = count($headers);
        $rows = array_map(function ($row) use ($width) {
            $cells = array_slice(array_values($row), 0, $width);
            $cells = array_map(fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE), $cells);

            return array_pad($cells, $width, null);
        }, $validated['rows']);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Lịch sử thao tác');
        $sheet->fromArray($headers, null, 'A1');
        // Ghi từng ô dạng chuỗi để mã phòng / mã đăng ký có số 0 đầu hoặc dấu "=" không bị Excel đổi.
        foreach ($rows as $r => $cells) {
            foreach ($cells as $c => $value) {
                $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c + 1) . ($r + 2);
                if (is_int($value) || is_float($value)) {
                    $sheet->setCellValue($coordinate, $value);
                } else {
                    $sheet->setCellValueExplicit($coordinate, (string) ($value ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
        }

        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($width);
        $lastRow = max(1, count($rows) + 1);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF006BDB']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['argb' => 'FFD9D9D9']]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP, 'wrapText' => true],
        ]);

        // Độ rộng theo nội dung (giới hạn), cột mô tả rộng hơn và tự xuống dòng để dễ đọc.
        foreach ($headers as $index => $header) {
            $longest = mb_strlen($header);
            foreach ($rows as $cells) {
                foreach (preg_split('/\R/u', (string) ($cells[$index] ?? '')) as $line) {
                    $longest = max($longest, mb_strlen($line));
                }
            }
            $limit = mb_stripos($header, 'mô tả') !== false ? 90 : 40;
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1))
                ->setWidth(min($limit, max(8, $longest + 2)));
        }

        $filename = 'Lich_su_thao_tac_' . now()->format('Y-m-d_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
