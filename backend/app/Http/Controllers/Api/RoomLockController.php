<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomLock;
use App\Models\Room;
use App\Models\BookingRoom;
use Illuminate\Http\Request;

class RoomLockController extends Controller
{
    /**
     * Display a listing of room locks.
     */
    public function index(Request $request)
    {
        $query = RoomLock::with(['room.roomForm', 'room.roomClass']);

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $locks = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $locks,
        ]);
    }

    /**
     * Store a newly created room lock.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'room_number' => 'required|exists:rooms,room_number',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'reason' => 'nullable|string|max:255',
            'maintenance_percent' => 'nullable|integer|min:0|max:100',
            'status' => 'nullable|string|max:50',
            'username' => 'nullable|string|max:50',
            'lock_type' => 'required|string|in:OOO,OOS',
            'force' => 'nullable',
        ], [
            'room_number.required' => 'Số phòng là bắt buộc.',
            'room_number.exists' => 'Phòng không tồn tại.',
            'start_date.required' => 'Ngày bắt đầu là bắt buộc.',
            'start_date.date' => 'Ngày bắt đầu không đúng định dạng ngày giờ.',
            'end_date.required' => 'Ngày mở khóa là bắt buộc.',
            'end_date.date' => 'Ngày mở khóa không đúng định dạng ngày giờ.',
            'lock_type.required' => 'Loại khóa phòng là bắt buộc.',
            'lock_type.in' => 'Loại khóa phòng phải là OOO hoặc OOS.',
        ]);

        $room = Room::where('room_number', $validated['room_number'])->firstOrFail();
        $roomId = $room->id;

        // Adjust dates according to start_date being today (system date) or in the future
        $rawStart = $request->input('start_date');
        $rawEnd = $request->input('end_date');

        $latestRoll = \App\Models\SystemDateRoll::latest('id')->first();
        $sysDateStr = $latestRoll
            ? \Carbon\Carbon::parse($latestRoll->system_date)->toDateString()
            : \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->toDateString();

        $localNow = \Carbon\Carbon::now('Asia/Ho_Chi_Minh');

        $reqStart = \Carbon\Carbon::parse($validated['start_date']);
        $reqStartDateStr = $reqStart->format('Y-m-d');

        $hasNoTime = !str_contains($rawStart, ' ') || str_ends_with($rawStart, ' 00:00:00') || str_ends_with($rawStart, ' 00:00');
        if ($hasNoTime) {
            if ($reqStartDateStr === $sysDateStr) {
                // Start date is system date, use current execution time
                $validated['start_date'] = $reqStartDateStr . ' ' . $localNow->format('H:i:s');
            } elseif ($reqStartDateStr > $sysDateStr) {
                // Start date is in the future (> sysDate), start at 00:00:00
                $validated['start_date'] = $reqStartDateStr . ' 00:00:00';
            } else {
                // Start date is in the past, start at 00:00:00
                $validated['start_date'] = $reqStartDateStr . ' 00:00:00';
            }
        }

        $defaultEndTime = \App\Models\HotelConfig::where('name', 'FrmOOO_DefineLockByTime')->first()?->value ?? '23:59';
        $hasDefaultEndTime = !str_contains($rawEnd, ' ') || str_ends_with($rawEnd, ' 23:59:00') || str_ends_with($rawEnd, ' 23:59') || str_ends_with($rawEnd, ' ' . $defaultEndTime . ':00');
        if ($hasDefaultEndTime) {
            $reqEnd = \Carbon\Carbon::parse($validated['end_date']);
            $validated['end_date'] = $reqEnd->format('Y-m-d ' . $defaultEndTime . ':59');
        }

        // 1. Validate date/time bounds
        $timeError = $this->validateLockPeriod($validated['start_date'], $validated['end_date']);
        if ($timeError) {
            return response()->json(['success' => false, 'message' => $timeError], 422);
        }

        // 2. Check for overlapping OOO/OOS locks
        $hasOverlapLocks = $this->checkOverlapLocks($room->room_number, $validated['start_date'], $validated['end_date']);
        if ($hasOverlapLocks) {
            return response()->json(['success' => false, 'message' => 'Không được phép khóa phòng do phòng đã có lịch khóa OOO/OOS khác trùng lặp thời gian này.'], 422);
        }

        // 3. Check booking overlap (STRICT BLOCK ALWAYS)
        $booking = $this->checkBookingOverlap($room->room_number, $validated['start_date'], $validated['end_date']);
        if (!empty($booking)) {
            $bkStartStr = \Carbon\Carbon::parse($booking['start_date'])->format('d/m/Y');
            $bkEndStr = \Carbon\Carbon::parse($booking['end_date'])->format('d/m/Y');
            return response()->json([
                'success' => false,
                'message' => "Không được phép khóa phòng vì trùng lịch với booking {$booking['booking_code']} ({$bkStartStr} ~ {$bkEndStr})."
            ], 422);
        }

        // 4. Check AV capacity (AllowOverRoomTypeRoomKind) - Kiểm tra Overbooking TRƯỚC
        $allowOverAv = \App\Models\HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->first()?->value ?? '0';
        $avError = $this->checkAvForRoomClass($room->room_class_id, $validated['start_date'], $validated['end_date'], $room->room_number);
        if (!empty($avError)) {
            if ($allowOverAv === '0') {
                return response()->json([
                    'success' => false,
                    'message' => "Không thể khóa phòng vì loại phòng {$avError['class_name']} sẽ bị hết phòng trống (AV < 0) vào ngày {$avError['date']}."
                ], 422);
            }

            if (!filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN)) {
                return response()->json([
                    'success' => false,
                    'require_confirm' => true,
                    'message' => "Khóa phòng {$room->room_number} sẽ làm loại phòng {$avError['class_name']} bị âm phòng (AV < 0) vào ngày {$avError['date']}. Bạn có muốn tiếp tục thao tác khóa phòng?",
                ], 422);
            }
        }

        // 5. Check unassignable booking availability (AllowLockRoomCauseUnassignableRoomBK) - Kiểm tra unassignable SAU
        $unassignableConfig = \App\Models\HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->first()?->value ?? '0';
        $unassignableViolation = $this->checkUnassignableBookingsAvailability($room->room_class_id, $validated['start_date'], $validated['end_date'], [(string)$room->room_number]);
        if (!empty($unassignableViolation)) {
            if ($unassignableConfig === '0') {
                return response()->json([
                    'success' => false,
                    'message' => "Không thể khóa phòng vì loại phòng {$unassignableViolation['class_name']} sẽ không đủ phòng trống liên tục để gán cho booking {$unassignableViolation['booking_code']} ({$unassignableViolation['arrival']} ~ {$unassignableViolation['departure']})."
                ], 422);
            }

            if (!filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN)) {
                return response()->json([
                    'success' => false,
                    'require_confirm' => true,
                    'message' => "Khóa phòng sẽ dẫn đến booking {$unassignableViolation['booking_code']} ({$unassignableViolation['arrival']} ~ {$unassignableViolation['departure']}) của loại phòng {$unassignableViolation['class_name']} không đủ phòng trống liên tục để gán số phòng. Bạn có muốn tiếp tục thao tác khóa phòng?",
                ], 422);
            }
        }

        $validated['room_number'] = $room->room_number;
        $validated['is_active'] = 1;
        if (!isset($validated['username'])) {
            $validated['username'] = $request->user()?->username ?? $request->user()?->name ?? 'NB0016';
        }

        // Determine correct status based on system date
        $latestRoll = \App\Models\SystemDateRoll::latest('id')->first();
        $sysDateStr = $latestRoll
            ? \Carbon\Carbon::parse($latestRoll->system_date)->toDateString()
            : \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->toDateString();

        $startDateStr = \Carbon\Carbon::parse($validated['start_date'])->toDateString();
        $determinedStatus = ($startDateStr <= $sysDateStr) ? 'Active' : 'New';
        $validated['status'] = $determinedStatus;

        // Remove non-schema field
        unset($validated['force']);

        $lock = RoomLock::create($validated);

        // Update room status to maintenance ONLY if it is active starting today
        if ($determinedStatus === 'Active') {
            $lockCode = $lock->lock_type === 'OOS' ? 'oos' : 'ooo';
            Room::where('room_number', $room->room_number)->update(['room_status_code' => $lockCode]);
            event(new \App\Events\RoomStatusUpdated($room->id, $lockCode, 'Phòng khóa bảo trì'));
        }

        $lock->load(['room.roomForm', 'room.roomClass']);

        return response()->json([
            'success' => true,
            'data' => $lock,
        ], 201);
    }

    /**
     * Bulk lock rooms.
     */
    public function bulkLock(Request $request)
    {
        if (!app(\App\Services\RoomStatusPermissionService::class)->canChange($request)) {
            return response()->json(['success' => false, 'message' => 'User không có quyền khóa/đổi trạng thái phòng tại module này.'], 403);
        }

        $inputLocks = $request->input('locks');
        $rawLocks = [];

        if (is_array($inputLocks) && count($inputLocks) > 0) {
            foreach ($inputLocks as $item) {
                if (!empty($item['room_number']) && !empty($item['start_date']) && !empty($item['end_date'])) {
                    $rawLocks[] = [
                        'room_number' => (string)$item['room_number'],
                        'start_date' => $item['start_date'],
                        'end_date' => $item['end_date'],
                        'lock_type' => strtoupper($item['lock_type'] ?? $request->input('lock_type', 'OOO')),
                        'reason' => $item['reason'] ?? $request->input('reason', ''),
                        'maintenance_percent' => $item['maintenance_percent'] ?? $request->input('maintenance_percent', 0),
                        'status' => $item['status'] ?? $request->input('status', 'New'),
                    ];
                }
            }
        } else {
            $validated = $request->validate([
                'room_numbers' => 'required|array',
                'room_numbers.*' => 'exists:rooms,room_number',
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'reason' => 'nullable|string|max:255',
                'maintenance_percent' => 'nullable|integer|min:0|max:100',
                'status' => 'nullable|string|max:50',
                'username' => 'nullable|string|max:50',
                'lock_type' => 'required|string|in:OOO,OOS',
                'force' => 'nullable',
            ], [
                'room_numbers.required' => 'Danh sách phòng là bắt buộc.',
                'room_numbers.array' => 'Danh sách phòng phải là một mảng.',
                'room_numbers.*.exists' => 'Một trong các phòng đã chọn không tồn tại.',
                'start_date.required' => 'Ngày bắt đầu là bắt buộc.',
                'start_date.date' => 'Ngày bắt đầu không đúng định dạng ngày giờ.',
                'end_date.required' => 'Ngày mở khóa là bắt buộc.',
                'end_date.date' => 'Ngày mở khóa không đúng định dạng ngày giờ.',
                'lock_type.required' => 'Loại khóa phòng là bắt buộc.',
                'lock_type.in' => 'Loại khóa phòng phải là OOO hoặc OOS.',
            ]);

            foreach ($validated['room_numbers'] as $rNo) {
                $rawLocks[] = [
                    'room_number' => (string)$rNo,
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'lock_type' => strtoupper($validated['lock_type']),
                    'reason' => $validated['reason'] ?? '',
                    'maintenance_percent' => $validated['maintenance_percent'] ?? 0,
                    'status' => $validated['status'] ?? 'New',
                ];
            }
        }

        if (empty($rawLocks)) {
            return response()->json(['success' => false, 'message' => 'Không có danh sách phòng cần khóa.'], 422);
        }

        $latestRoll = \App\Models\SystemDateRoll::latest('id')->first();
        $sysDateStr = $latestRoll
            ? \Carbon\Carbon::parse($latestRoll->system_date)->toDateString()
            : \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->toDateString();

        $localNow = \Carbon\Carbon::now('Asia/Ho_Chi_Minh');
        $defaultEndTime = \App\Models\HotelConfig::where('name', 'FrmOOO_DefineLockByTime')->first()?->value ?? '23:59';

        $preparedLocks = [];
        foreach ($rawLocks as $item) {
            $room = Room::where('room_number', $item['room_number'])->first();
            if (!$room) {
                return response()->json(['success' => false, 'message' => "Phòng {$item['room_number']} không tồn tại."], 422);
            }

            $rawStart = $item['start_date'];
            $rawEnd = $item['end_date'];
            $reqStart = \Carbon\Carbon::parse($rawStart);
            $reqStartDateStr = $reqStart->format('Y-m-d');

            $hasNoTime = !str_contains($rawStart, ' ') || str_ends_with($rawStart, ' 00:00:00') || str_ends_with($rawStart, ' 00:00');
            if ($hasNoTime) {
                if ($reqStartDateStr === $sysDateStr) {
                    $item['start_date'] = $reqStartDateStr . ' ' . $localNow->format('H:i:s');
                } elseif ($reqStartDateStr > $sysDateStr) {
                    $item['start_date'] = $reqStartDateStr . ' 00:00:00';
                } else {
                    $item['start_date'] = $reqStartDateStr . ' 00:00:00';
                }
            }

            $hasDefaultEndTime = !str_contains($rawEnd, ' ') || str_ends_with($rawEnd, ' 23:59:00') || str_ends_with($rawEnd, ' 23:59') || str_ends_with($rawEnd, ' ' . $defaultEndTime . ':00');
            if ($hasDefaultEndTime) {
                $reqEnd = \Carbon\Carbon::parse($rawEnd);
                $item['end_date'] = $reqEnd->format('Y-m-d ' . $defaultEndTime . ':59');
            }

            $timeError = $this->validateLockPeriod($item['start_date'], $item['end_date']);
            if ($timeError) {
                return response()->json(['success' => false, 'message' => $timeError], 422);
            }

            $item['room_id'] = $room->id;
            $item['room_class_id'] = $room->room_class_id;
            $preparedLocks[] = $item;
        }

        $allowOverAv = \App\Models\HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->first()?->value ?? '0';

        $bookingBlockedRooms = [];
        $avBlockedRooms = [];
        $avWarningRooms = [];

        // Count how many rooms per class are being locked in this batch request
        $classCountsInBatch = [];
        foreach ($preparedLocks as $pItem) {
            $cId = $pItem['room_class_id'];
            $classCountsInBatch[$cId] = ($classCountsInBatch[$cId] ?? 0) + 1;
        }

        foreach ($preparedLocks as $pItem) {
            if ($this->checkOverlapLocks($pItem['room_number'], $pItem['start_date'], $pItem['end_date'])) {
                return response()->json(['success' => false, 'message' => "Không được phép khóa phòng do phòng {$pItem['room_number']} đã có lịch khóa OOO/OOS khác trùng lặp thời gian này."], 422);
            }

            $booking = $this->checkBookingOverlap($pItem['room_number'], $pItem['start_date'], $pItem['end_date']);
            if ($booking) {
                $bookingBlockedRooms[] = [
                    'room_number' => $pItem['room_number'],
                    'booking_code' => $booking['booking_code'],
                    'start' => \Carbon\Carbon::parse($booking['start_date'])->format('d/m/Y'),
                    'end' => \Carbon\Carbon::parse($booking['end_date'])->format('d/m/Y')
                ];
            }

            $avError = $this->checkAvForRoomClass($pItem['room_class_id'], $pItem['start_date'], $pItem['end_date'], $pItem['room_number'], 1, $preparedLocks);
            if ($avError) {
                if ($allowOverAv === '0') {
                    $avBlockedRooms[] = [
                        'room_number' => $pItem['room_number'],
                        'class_name' => $avError['class_name'],
                        'date' => $avError['date']
                    ];
                } else {
                    $avWarningRooms[] = [
                        'room_number' => $pItem['room_number'],
                        'class_name' => $avError['class_name'],
                        'date' => $avError['date']
                    ];
                }
            }

            // Check unassignable booking availability
            $unassignableViolation = $this->checkUnassignableBookingsAvailability(
                $pItem['room_class_id'],
                $pItem['start_date'],
                $pItem['end_date'],
                array_column($preparedLocks, 'room_number')
            );
            if ($unassignableViolation) {
                $unassignableConfig = \App\Models\HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->first()?->value ?? '0';
                if ($unassignableConfig === '0') {
                    $unassignableBlockedRooms[] = [
                        'room_number' => $pItem['room_number'],
                        'class_name' => $unassignableViolation['class_name'],
                        'booking_code' => $unassignableViolation['booking_code'],
                        'arrival' => $unassignableViolation['arrival'],
                        'departure' => $unassignableViolation['departure'],
                    ];
                } else {
                    $unassignableWarningRooms[] = [
                        'room_number' => $pItem['room_number'],
                        'class_name' => $unassignableViolation['class_name'],
                        'booking_code' => $unassignableViolation['booking_code'],
                        'arrival' => $unassignableViolation['arrival'],
                        'departure' => $unassignableViolation['departure'],
                    ];
                }
            }
        }

        if (!empty($bookingBlockedRooms)) {
            $messages = [];
            foreach ($bookingBlockedRooms as $b) {
                $messages[] = "Không được phép khóa phòng {$b['room_number']} vì trùng lịch với booking {$b['booking_code']} ({$b['start']} ~ {$b['end']}).";
            }
            return response()->json(['success' => false, 'message' => implode(' ', $messages)], 422);
        }

        // Kiểm tra Overbooking TRƯỚC
        if (!empty($avBlockedRooms)) {
            $messages = [];
            foreach ($avBlockedRooms as $av) {
                $messages[] = "Không thể khóa phòng {$av['room_number']} vì loại phòng {$av['class_name']} sẽ bị hết phòng trống (AV < 0) vào ngày {$av['date']}.";
            }
            return response()->json(['success' => false, 'message' => implode(' ', $messages)], 422);
        }

        if (!empty($avWarningRooms) && !filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN)) {
            $messages = [];
            foreach ($avWarningRooms as $av) {
                $messages[] = "Khóa phòng {$av['room_number']} sẽ làm loại phòng {$av['class_name']} bị âm phòng (AV < 0) vào ngày {$av['date']}.";
            }
            return response()->json([
                'success' => false,
                'require_confirm' => true,
                'message' => implode(' ', $messages) . ' Bạn có muốn tiếp tục thao tác khóa phòng?'
            ], 422);
        }

        // Kiểm tra Unassignable SAU
        if (!empty($unassignableBlockedRooms)) {
            $messages = [];
            foreach ($unassignableBlockedRooms as $u) {
                $messages[] = "Không thể khóa phòng {$u['room_number']} vì loại phòng {$u['class_name']} sẽ không đủ phòng trống liên tục để gán cho booking {$u['booking_code']} ({$u['arrival']} ~ {$u['departure']}).";
            }
            return response()->json(['success' => false, 'message' => implode(' ', $messages)], 422);
        }

        if (!empty($unassignableWarningRooms) && !filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN)) {
            $messages = [];
            foreach ($unassignableWarningRooms as $u) {
                $messages[] = "Khóa phòng {$u['room_number']} sẽ làm loại phòng {$u['class_name']} không đủ phòng trống liên tục để gán cho booking {$u['booking_code']} ({$u['arrival']} ~ {$u['departure']}).";
            }
            return response()->json([
                'success' => false,
                'require_confirm' => true,
                'message' => implode(' ', $messages) . ' Bạn có muốn tiếp tục thao tác khóa phòng?'
            ], 422);
        }

        $username = $request->input('username') ?? $request->user()?->username ?? $request->user()?->name ?? 'NB0016';
        $locksCreated = [];

        $latestRoll = \App\Models\SystemDateRoll::latest('id')->first();
        $sysDateStr = $latestRoll
            ? \Carbon\Carbon::parse($latestRoll->system_date)->toDateString()
            : \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->toDateString();

        \Illuminate\Support\Facades\DB::transaction(function () use ($preparedLocks, $username, $sysDateStr, &$locksCreated) {
            foreach ($preparedLocks as $pItem) {
                $startDateStr = \Carbon\Carbon::parse($pItem['start_date'])->toDateString();
                $determinedStatus = ($startDateStr <= $sysDateStr) ? 'Active' : 'New';

                $lock = RoomLock::create([
                    'room_number' => $pItem['room_number'],
                    'start_date' => $pItem['start_date'],
                    'end_date' => $pItem['end_date'],
                    'reason' => $pItem['reason'],
                    'maintenance_percent' => $pItem['maintenance_percent'],
                    'status' => $determinedStatus,
                    'username' => $username,
                    'is_active' => 1,
                    'lock_type' => $pItem['lock_type'],
                ]);

                // Update room status ONLY if status is Active
                if ($determinedStatus === 'Active') {
                    $lockCode = $pItem['lock_type'] === 'OOS' ? 'oos' : 'ooo';
                    $room = Room::where('room_number', $pItem['room_number'])->first();
                    Room::where('room_number', $pItem['room_number'])->update(['room_status_code' => $lockCode]);
                    if ($room) {
                        event(new \App\Events\RoomStatusUpdated($room->id, $lockCode, 'Phòng khóa bảo trì'));
                    }
                }

                $locksCreated[] = $lock;
            }
        });

        return response()->json([
            'success' => true,
            'message' => count($locksCreated) . ' rooms locked successfully.',
            'data' => $locksCreated,
        ]);
    }

    /**
     * Bulk unlock multiple rooms.
     */
    public function bulkUnlock(Request $request)
    {
        $validated = $request->validate([
            'room_ids' => 'nullable|array',
            'room_ids.*' => 'exists:rooms,id',
            'room_numbers' => 'nullable|array',
            'room_numbers.*' => 'exists:rooms,room_number',
            'lock_ids' => 'nullable|array',
            'lock_ids.*' => 'exists:room_locks,id',
        ]);

        $roomNumbers = $validated['room_numbers'] ?? [];
        if (!empty($validated['room_ids'])) {
            $numbers = Room::whereIn('id', $validated['room_ids'])->pluck('room_number')->toArray();
            $roomNumbers = array_unique(array_merge($roomNumbers, $numbers));
        }

        $lockIds = $validated['lock_ids'] ?? [];
        if (!empty($roomNumbers)) {
            $activeRoomLocks = RoomLock::whereIn('room_number', $roomNumbers)
                ->where('is_active', 1)
                ->pluck('id')
                ->toArray();
            $lockIds = array_unique(array_merge($lockIds, $activeRoomLocks));
        }

        if (empty($lockIds)) {
            return response()->json([
                'success' => true,
                'message' => 'Không có phòng nào cần mở khóa.',
            ]);
        }

        $locks = RoomLock::whereIn('id', $lockIds)->get();

        // Check department and role permission for all selected locks
        foreach ($locks as $lock) {
            $deptError = $this->checkUnlockDepartmentPermission($request, $lock);
            if ($deptError) {
                $room = $lock->room;
                return response()->json([
                    'success' => false,
                    'message' => "Không thể mở khóa phòng " . ($room?->room_number ?? '') . ": {$deptError}"
                ], 403);
            }

            $roleError = $this->checkUnlockRolePermission($request, $lock);
            if ($roleError) {
                $room = $lock->room;
                return response()->json([
                    'success' => false,
                    'message' => "Không thể mở khóa phòng " . ($room?->room_number ?? '') . ": {$roleError}"
                ], 403);
            }
        }

        $now = now();
        $localNow = \Carbon\Carbon::now('Asia/Ho_Chi_Minh');
        $unlockUsername = $request->user()?->username ?? $request->user()?->name ?? 'NB0016';
        $affectedRoomNumbers = [];

        $latestRoll = \App\Models\SystemDateRoll::latest('id')->first();
        $sysDateStr = $latestRoll
            ? \Carbon\Carbon::parse($latestRoll->system_date)->toDateString()
            : \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->toDateString();
        $unlockEndDateTime = $sysDateStr . ' ' . $localNow->format('H:i:s');

        foreach ($locks as $lock) {
            $affectedRoomNumbers[] = $lock->room_number;

            $oldValues = $lock->toArray();
            if ($lock->status === 'New') {
                $lock->delete();
            } else {
                $updateData = [
                    'is_active' => 2,
                    'status' => 'Done',
                    'unlock_username' => $unlockUsername,
                    'unlocked_at' => $now,
                ];
                if (\Carbon\Carbon::parse($lock->start_date)->lte(\Carbon\Carbon::parse($unlockEndDateTime))) {
                    $updateData['end_date'] = $unlockEndDateTime;
                }
                $lock->update($updateData);
            }

            \App\Services\ActivityLogService::logUpdate(
                $request,
                $lock,
                $oldValues,
                'reservation',
                'LockRoomPage',
                "Mở khóa phòng {$lock->room_number} (Hành động: Unlock, Giai đoạn: " . ($lock->start_date ? $lock->start_date->format('d/m/Y H:i') : '') . " ~ " . ($lock->end_date ? $lock->end_date->format('d/m/Y H:i') : '') . ")",
                $lock->room_number
            );
        }

        // Check and update room statuses for affected rooms
        $affectedRoomNumbers = array_unique($affectedRoomNumbers);
        foreach ($affectedRoomNumbers as $roomNumber) {
            $hasActiveToday = RoomLock::where('room_number', $roomNumber)
                ->where('is_active', 1)
                ->where('status', 'Active')
                ->whereDate('start_date', '<=', $sysDateStr)
                ->whereDate('end_date', '>=', $sysDateStr)
                ->exists();

            if (!$hasActiveToday) {
                $targetRoom = Room::where('room_number', $roomNumber)->first();
                if ($targetRoom && in_array($targetRoom->room_status_code, ['ooo', 'oos', 'occupied_ooo'])) {
                    $targetRoom->update(['room_status_code' => 'vacant_ready']);
                    event(new \App\Events\RoomStatusUpdated($targetRoom->id, 'vacant_ready', 'Mở khóa phòng'));
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Mở khóa phòng thành công.',
        ]);
    }

    /**
     * Get lock history of a specific room.
     */
    public function history($roomIdOrNumber)
    {
        $room = Room::where('room_number', $roomIdOrNumber)->first();
        if (!$room && is_numeric($roomIdOrNumber)) {
            $room = Room::find($roomIdOrNumber);
        }

        $roomNumber = $room ? $room->room_number : $roomIdOrNumber;

        $history = RoomLock::where('room_number', $roomNumber)
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $history->map(function ($lock) {
            $lockArray = $lock->toArray();
            $lockArray['start_date'] = $lock->start_date ? $lock->start_date->format('Y-m-d H:i:s') : null;
            $lockArray['end_date'] = $lock->end_date ? $lock->end_date->format('Y-m-d H:i:s') : null;
            $lockArray['unlocked_at'] = $lock->unlocked_at ? $lock->unlocked_at->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s') : null;
            $lockArray['created_at'] = $lock->created_at ? $lock->created_at->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s') : null;
            $lockArray['username'] = $lock->username; // Trả về display name từ accessor
            $lockArray['unlock_username'] = $this->resolveUserDisplayName($lock->unlock_username);
            return $lockArray;
        })->toArray();

        // Query deleted future locks from activity logs to show them in history too!
        $deletedLogs = \App\Models\ActivityLog::where('target_type', 'RoomLock')
            ->where('target_label', $roomNumber)
            ->where('action', 'delete')
            ->get();

        foreach ($deletedLogs as $log) {
            $oldValues = $log->old_values;
            if ($oldValues && isset($oldValues['room_number'])) {
                // Parse date strings in local timezone context
                $startStr = $oldValues['start_date'] ?? null;
                $endStr = $oldValues['end_date'] ?? null;
                
                // Reconstruct pseudo-unlocked log representing the deleted future lock
                $lockArray = [
                    'id' => $oldValues['id'] ?? null,
                    'room_number' => $oldValues['room_number'],
                    'start_date' => $startStr ? \Carbon\Carbon::parse($startStr)->format('Y-m-d H:i:s') : null,
                    'end_date' => $endStr ? \Carbon\Carbon::parse($endStr)->format('Y-m-d H:i:s') : null,
                    'reason' => $oldValues['reason'] ?? null,
                    'maintenance_percent' => $oldValues['maintenance_percent'] ?? 0,
                    'status' => $oldValues['status'] ?? 'New',
                    'username' => $this->resolveUserDisplayName($oldValues['username'] ?? 'NB0016'),
                    'lock_type' => $oldValues['lock_type'] ?? 'OOO',
                    'is_active' => 2, // Mark as unlocked for timeline display!
                    'unlock_username' => $this->resolveUserDisplayName($log->user_name ?? 'Admin'),
                    'unlocked_at' => $log->created_at ? $log->created_at->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s') : null,
                    'created_at' => $log->created_at ? $log->created_at->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s') : null,
                ];
                $data[] = $lockArray;
            }
        }

        // Sort descending by created_at
        usort($data, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Display the specified room lock.
     */
    public function show($id)
    {
        $lock = RoomLock::with(['room.roomForm', 'room.roomClass'])->find($id);
        if (!$lock) {
            return response()->json(['message' => 'Room lock not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => $lock,
        ]);
    }

    /**
     * Update the specified room lock.
     */
    public function update(Request $request, $id)
    {
        $lock = RoomLock::find($id);
        if (!$lock) {
            return response()->json(['message' => 'Room lock not found'], 404);
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'reason' => 'nullable|string|max:255',
            'maintenance_percent' => 'nullable|integer|min:0|max:100',
            'status' => 'nullable|string|max:50',
            'username' => 'nullable|string|max:50',
            'lock_type' => 'required|string|in:OOO,OOS',
            'is_active' => 'nullable|integer',
            'force' => 'nullable',
        ], [
            'start_date.required' => 'Ngày bắt đầu là bắt buộc.',
            'start_date.date' => 'Ngày bắt đầu không đúng định dạng ngày giờ.',
            'end_date.required' => 'Ngày mở khóa là bắt buộc.',
            'end_date.date' => 'Ngày mở khóa không đúng định dạng ngày giờ.',
            'lock_type.required' => 'Loại khóa phòng là bắt buộc.',
            'lock_type.in' => 'Loại khóa phòng phải là OOO hoặc OOS.',
        ]);

        $room = Room::where('room_number', $lock->room_number)->firstOrFail();

        // Adjust dates according to start_date being today or in the future
        $rawStart = $request->input('start_date');
        $rawEnd = $request->input('end_date');

        $latestRoll = \App\Models\SystemDateRoll::latest('id')->first();
        $sysDateStr = $latestRoll
            ? \Carbon\Carbon::parse($latestRoll->system_date)->toDateString()
            : \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->toDateString();

        $localNow = \Carbon\Carbon::now('Asia/Ho_Chi_Minh');

        $reqStart = \Carbon\Carbon::parse($validated['start_date']);
        $reqStartDateStr = $reqStart->format('Y-m-d');

        $origLockStart = \Carbon\Carbon::parse($lock->start_date);

        if ($reqStartDateStr === $origLockStart->format('Y-m-d')) {
            if (str_ends_with($rawStart, '00:00:00') || str_ends_with($rawStart, '00:00') || !str_contains($rawStart, ' ')) {
                $validated['start_date'] = $origLockStart->format('Y-m-d H:i:s');
            }
        } else {
            if (str_ends_with($rawStart, '00:00:00') || str_ends_with($rawStart, '00:00') || !str_contains($rawStart, ' ')) {
                if ($reqStartDateStr === $sysDateStr) {
                    $validated['start_date'] = $reqStartDateStr . ' ' . $localNow->format('H:i:s');
                } elseif ($reqStartDateStr > $sysDateStr) {
                    $validated['start_date'] = $reqStartDateStr . ' 00:00:00';
                } else {
                    $validated['start_date'] = $reqStartDateStr . ' 00:00:00';
                }
            }
        }

        $defaultEndTime = \App\Models\HotelConfig::where('name', 'FrmOOO_DefineLockByTime')->first()?->value ?? '23:59';
        if (str_ends_with($rawEnd, '23:59:00') || str_ends_with($rawEnd, '23:59') || str_ends_with($rawEnd, $defaultEndTime . ':00')) {
            $reqEnd = \Carbon\Carbon::parse($validated['end_date']);
            $validated['end_date'] = $reqEnd->format('Y-m-d ' . $defaultEndTime . ':59');
        }

        $lockStart = \Carbon\Carbon::parse($lock->start_date);
        $lockStartDateStr = $lockStart->toDateString();
        $lockEnd = \Carbon\Carbon::parse($lock->end_date);
        $lockEndDateStr = $lockEnd->toDateString();

        // 1. Past/ended lock restriction
        if ($lockEndDateStr < $sysDateStr) {
            return response()->json([
                'success' => false,
                'message' => 'Không được phép chỉnh sửa lịch khóa phòng đã kết thúc trong quá khứ so với ngày hệ thống.'
            ], 422);
        }

        // 2. Section 3: For locks with is_active = 1:
        // If start_date <= system_date: start_date CANNOT be adjusted
        if ($lockStartDateStr <= $sysDateStr) {
            $reqStartDateStr = \Carbon\Carbon::parse($request->input('start_date'))->toDateString();
            if ($reqStartDateStr !== $lockStartDateStr) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không được phép điều chỉnh ngày bắt đầu đối với phòng đang trong giai đoạn khóa (ngày bắt đầu <= ngày hệ thống).'
                ], 422);
            }
            $validated['start_date'] = $lock->start_date instanceof \DateTimeInterface 
                ? $lock->start_date->format('Y-m-d H:i:s') 
                : $lock->start_date;
        } else {
            // Future lock (start_date > sysDateStr): start_date can be changed, but cannot be < sysDateStr
            $reqStartDateStr = \Carbon\Carbon::parse($validated['start_date'])->toDateString();
            if ($reqStartDateStr < $sysDateStr) {
                return response()->json([
                    'success' => false,
                    'message' => "Ngày bắt đầu khóa không được nhỏ hơn Ngày hệ thống ({$sysDateStr})!"
                ], 422);
            }
        }

        // 3. Validate date/time bounds (end must not be before start)
        $start = \Carbon\Carbon::parse($validated['start_date']);
        $end = \Carbon\Carbon::parse($validated['end_date']);
        if ($end->lt($start)) {
            if ($start->isSameDay($end)) {
                return response()->json(['success' => false, 'message' => 'Giờ kết thúc không được nhỏ hơn giờ bắt đầu (trong cùng ngày).'], 422);
            }
            return response()->json(['success' => false, 'message' => 'Ngày mở khóa không được nhỏ hơn ngày bắt đầu.'], 422);
        }

        // 3. Check for overlapping locks
        $hasOverlapLocks = $this->checkOverlapLocks($lock->room_number, $validated['start_date'], $validated['end_date'], $lock->id);
        if ($hasOverlapLocks) {
            return response()->json(['success' => false, 'message' => 'Không được phép khóa phòng do phòng đã có lịch khóa OOO/OOS khác trùng lặp thời gian này.'], 422);
        }

        // 4. Check booking overlap (STRICT BLOCK ALWAYS)
        $booking = $this->checkBookingOverlap($room->room_number, $validated['start_date'], $validated['end_date']);
        if ($booking) {
            $bkStartStr = \Carbon\Carbon::parse($booking['start_date'])->format('d/m/Y');
            $bkEndStr = \Carbon\Carbon::parse($booking['end_date'])->format('d/m/Y');
            return response()->json([
                'success' => false,
                'message' => "Không được phép cập nhật khóa phòng vì trùng lịch với booking {$booking['booking_code']} ({$bkStartStr} ~ {$bkEndStr})."
            ], 422);
        }

        // 5. Check AV capacity (AllowOverRoomTypeRoomKind) - Kiểm tra Overbooking TRƯỚC
        $allowOverAv = \App\Models\HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->first()?->value ?? '0';
        $avError = $this->checkAvForRoomClass($room->room_class_id, $validated['start_date'], $validated['end_date'], $lock->room_number);
        if (!empty($avError)) {
            if ($allowOverAv === '0') {
                return response()->json([
                    'success' => false,
                    'message' => "Không thể cập nhật khóa phòng vì loại phòng {$avError['class_name']} sẽ bị hết phòng trống (AV < 0) vào ngày {$avError['date']}."
                ], 422);
            }

            if (!filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN)) {
                return response()->json([
                    'success' => false,
                    'require_confirm' => true,
                    'message' => "Khóa phòng {$lock->room_number} sẽ làm loại phòng {$avError['class_name']} bị âm phòng (AV < 0) vào ngày {$avError['date']}. Bạn có muốn tiếp tục thao tác khóa phòng?",
                ], 422);
            }
        }

        // 6. Check unassignable booking availability (AllowLockRoomCauseUnassignableRoomBK) - Kiểm tra unassignable SAU
        $unassignableConfig = \App\Models\HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->first()?->value ?? '0';
        $unassignableViolation = $this->checkUnassignableBookingsAvailability(
            $room->room_class_id,
            $validated['start_date'],
            $validated['end_date'],
            [(string)$room->room_number],
            $lock->id
        );

        if (!empty($unassignableViolation)) {
            if ($unassignableConfig === '0') {
                return response()->json([
                    'success' => false,
                    'message' => "Không thể cập nhật khóa phòng vì loại phòng {$unassignableViolation['class_name']} sẽ không đủ phòng trống liên tục để gán cho booking {$unassignableViolation['booking_code']} ({$unassignableViolation['arrival']} ~ {$unassignableViolation['departure']})."
                ], 422);
            }

            if (!filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN)) {
                return response()->json([
                    'success' => false,
                    'require_confirm' => true,
                    'message' => "Cập nhật khóa phòng sẽ làm loại phòng {$unassignableViolation['class_name']} không đủ phòng trống liên tục để gán cho booking {$unassignableViolation['booking_code']} ({$unassignableViolation['arrival']} ~ {$unassignableViolation['departure']}). Bạn có muốn tiếp tục thao tác khóa phòng?",
                ], 422);
            }
        }

        // Remove non-schema fields
        unset($validated['force']);

        $lock->update($validated);

        if ($lock->is_active == 1) {
            Room::where('room_number', $lock->room_number)->update(['room_status_code' => 'ooo']);
        } else {
            // Check if there are other active locks, otherwise restore status to vacant_ready
            $hasActive = RoomLock::where('room_number', $lock->room_number)->where('is_active', 1)->exists();
            if (!$hasActive) {
                Room::where('room_number', $lock->room_number)->update(['room_status_code' => 'vacant_ready']);
            }
        }

        $lock->load(['room.roomForm', 'room.roomClass']);

        return response()->json([
            'success' => true,
            'data' => $lock,
        ]);
    }

    /**
     * Remove the specified room lock.
     */
    public function destroy(Request $request, $id)
    {
        $lock = RoomLock::find($id);
        if (!$lock) {
            return response()->json(['message' => 'Room lock not found'], 404);
        }

        // Check department permission
        $deptError = $this->checkUnlockDepartmentPermission($request, $lock);
        if ($deptError) {
            return response()->json(['success' => false, 'message' => $deptError], 403);
        }

        // Check role permission
        $roleError = $this->checkUnlockRolePermission($request, $lock);
        if ($roleError) {
            return response()->json(['success' => false, 'message' => $roleError], 403);
        }

        $roomNumber = $lock->room_number;
        $unlockUsername = $request->user()?->username ?? $request->user()?->name ?? 'NB0016';
        $oldValues = $lock->toArray();

        $latestRoll = \App\Models\SystemDateRoll::latest('id')->first();
        $sysDateStr = $latestRoll
            ? \Carbon\Carbon::parse($latestRoll->system_date)->toDateString()
            : \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->toDateString();
        $localNow = \Carbon\Carbon::now('Asia/Ho_Chi_Minh');
        $unlockEndDateTime = $sysDateStr . ' ' . $localNow->format('H:i:s');

        if ($lock->status === 'New') {
            $lock->delete();
        } else {
            $updateData = [
                'is_active' => 2,
                'status' => 'Done',
                'unlock_username' => $unlockUsername,
                'unlocked_at' => now(),
            ];
            if (\Carbon\Carbon::parse($lock->start_date)->lte(\Carbon\Carbon::parse($unlockEndDateTime))) {
                $updateData['end_date'] = $unlockEndDateTime;
            }
            $lock->update($updateData);
        }

        \App\Services\ActivityLogService::logUpdate(
            $request,
            $lock,
            $oldValues,
            'reservation',
            'LockRoomPage',
            "Mở khóa phòng {$lock->room_number} (Hành động: Unlock, Giai đoạn: " . ($lock->start_date ? $lock->start_date->format('d/m/Y H:i') : '') . " ~ " . ($lock->end_date ? $lock->end_date->format('d/m/Y H:i') : '') . ")",
            $lock->room_number
        );

        // Check if there are other active locks today
        $latestRoll = \App\Models\SystemDateRoll::latest('id')->first();
        $sysDateStr = $latestRoll
            ? \Carbon\Carbon::parse($latestRoll->system_date)->toDateString()
            : \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->toDateString();

        $hasActiveToday = RoomLock::where('room_number', $roomNumber)
            ->where('is_active', 1)
            ->where('status', 'Active')
            ->whereDate('start_date', '<=', $sysDateStr)
            ->whereDate('end_date', '>=', $sysDateStr)
            ->exists();

        if (!$hasActiveToday) {
            $targetRoom = Room::where('room_number', $roomNumber)->first();
            if ($targetRoom && in_array($targetRoom->room_status_code, ['ooo', 'oos', 'occupied_ooo'])) {
                $targetRoom->update(['room_status_code' => 'vacant_ready']);
                event(new \App\Events\RoomStatusUpdated($targetRoom->id, 'vacant_ready', 'Mở khóa phòng'));
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Room lock deleted successfully',
        ]);
    }

    /**
     * Bulk update multiple room locks in a single atomic transaction.
     * If any room fails validation, rollback all changes.
     */
    public function bulkUpdate(Request $request)
    {
        $rawLocks = $request->input('locks', []);
        if (is_array($rawLocks)) {
            foreach ($rawLocks as $idx => $lk) {
                if (isset($lk['lock_id']) && !isset($lk['id'])) {
                    $rawLocks[$idx]['id'] = $lk['lock_id'];
                }
            }
            $request->merge(['locks' => $rawLocks]);
        }

        $validated = $request->validate([
            'locks' => 'required|array|min:1',
            'locks.*.id' => 'required|integer|exists:room_locks,id',
            'locks.*.start_date' => 'required|date',
            'locks.*.end_date' => 'required|date',
            'locks.*.reason' => 'nullable|string|max:255',
            'locks.*.maintenance_percent' => 'nullable|integer|min:0|max:100',
            'locks.*.lock_type' => 'nullable|string|in:OOO,OOS',
            'force' => 'nullable',
        ]);

        $latestRoll = \App\Models\SystemDateRoll::latest('id')->first();
        $sysDateStr = $latestRoll
            ? \Carbon\Carbon::parse($latestRoll->system_date)->toDateString()
            : \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->toDateString();

        $allowOverAv = \App\Models\HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->first()?->value ?? '0';
        $unassignableConfig = \App\Models\HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->first()?->value ?? '0';
        $force = filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN);
        $defaultEndTime = \App\Models\HotelConfig::where('name', 'FrmOOO_DefineLockByTime')->first()?->value ?? '23:59';

        $items = $validated['locks'];
        $lockIds = array_column($items, 'id');
        $existingLocks = RoomLock::whereIn('id', $lockIds)->with('room')->get()->keyBy('id');

        $prepared = [];
        $batchRoomNumbers = [];

        foreach ($items as $item) {
            $lockId = $item['id'];
            $lock = $existingLocks->get($lockId);
            if (!$lock) {
                return response()->json(['success' => false, 'message' => "Không tìm thấy phòng khóa ID {$lockId}."], 404);
            }

            $room = $lock->room;
            if (!$room) {
                return response()->json(['success' => false, 'message' => "Không tìm thấy phòng cho lịch khóa ID {$lockId}."], 404);
            }

            $rawStart = $item['start_date'];
            $rawEnd = $item['end_date'];

            $lockStart = \Carbon\Carbon::parse($lock->start_date);
            $lockStartDateStr = $lockStart->toDateString();
            $lockEnd = \Carbon\Carbon::parse($lock->end_date);
            $lockEndDateStr = $lockEnd->toDateString();

            if ($lockEndDateStr < $sysDateStr) {
                return response()->json([
                    'success' => false,
                    'message' => "Không được phép chỉnh sửa phòng {$lock->room_number} vì lịch khóa đã kết thúc trong quá khứ so với ngày hệ thống."
                ], 422);
            }

            // Adjust start_date
            if ($lockStartDateStr <= $sysDateStr) {
                $reqStartDateStr = \Carbon\Carbon::parse($item['start_date'])->toDateString();
                if ($reqStartDateStr !== $lockStartDateStr) {
                    return response()->json([
                        'success' => false,
                        'message' => "Phòng {$lock->room_number}: Không được phép điều chỉnh ngày bắt đầu đối với phòng đang trong giai đoạn khóa (ngày bắt đầu <= ngày hệ thống)."
                    ], 422);
                }
                $item['start_date'] = $lock->start_date instanceof \DateTimeInterface 
                    ? $lock->start_date->format('Y-m-d H:i:s') 
                    : $lock->start_date;
            } else {
                $reqStartDateStr = \Carbon\Carbon::parse($item['start_date'])->toDateString();
                if ($reqStartDateStr < $sysDateStr) {
                    return response()->json([
                        'success' => false,
                        'message' => "Phòng {$lock->room_number}: Ngày bắt đầu khóa không được nhỏ hơn Ngày hệ thống ({$sysDateStr})!"
                    ], 422);
                }
                if (!str_contains($rawStart, ' ')) {
                    $item['start_date'] = $reqStartDateStr . ' 00:00:00';
                }
            }

            // Adjust end_date
            $reqEndDate = \Carbon\Carbon::parse($item['end_date']);
            if (!str_contains($rawEnd, ' ') || str_ends_with($rawEnd, ' 23:59') || str_ends_with($rawEnd, ' 23:59:00')) {
                $item['end_date'] = $reqEndDate->format('Y-m-d ' . $defaultEndTime . ':59');
            }

            // Validate start/end order
            $start = \Carbon\Carbon::parse($item['start_date']);
            $end = \Carbon\Carbon::parse($item['end_date']);
            if ($end->lt($start)) {
                if ($start->isSameDay($end)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Phòng {$lock->room_number}: Giờ kết thúc không được nhỏ hơn giờ bắt đầu (trong cùng ngày)."
                    ], 422);
                }
                return response()->json([
                    'success' => false,
                    'message' => "Phòng {$lock->room_number}: Ngày mở khóa không được nhỏ hơn ngày bắt đầu."
                ], 422);
            }

            // Check overlap with other locks
            $hasOverlap = $this->checkOverlapLocks($lock->room_number, $item['start_date'], $item['end_date'], $lock->id);
            if ($hasOverlap) {
                return response()->json([
                    'success' => false,
                    'message' => "Không được phép cập nhật phòng {$lock->room_number} do đã có lịch khóa OOO/OOS khác trùng lặp thời gian này."
                ], 422);
            }

            // Check booking overlap
            $booking = $this->checkBookingOverlap($lock->room_number, $item['start_date'], $item['end_date']);
            if ($booking) {
                $bkStartStr = \Carbon\Carbon::parse($booking['start_date'])->format('d/m/Y');
                $bkEndStr = \Carbon\Carbon::parse($booking['end_date'])->format('d/m/Y');
                return response()->json([
                    'success' => false,
                    'message' => "Không thể cập nhật phòng {$lock->room_number} vì trùng lịch với booking {$booking['booking_code']} ({$bkStartStr} ~ {$bkEndStr})."
                ], 422);
            }

            $prepared[] = [
                'lock' => $lock,
                'room' => $room,
                'data' => $item,
            ];
            $batchRoomNumbers[] = (string)$lock->room_number;
        }

        // Validate AV and Unassignable Bookings for each room (Overbooking TRƯỚC, Unassignable SAU)
        foreach ($prepared as $p) {
            $lock = $p['lock'];
            $room = $p['room'];
            $data = $p['data'];

            // 1. Kiểm tra Overbooking TRƯỚC
            $avError = $this->checkAvForRoomClass($room->room_class_id, $data['start_date'], $data['end_date'], $lock->room_number);
            if (!empty($avError)) {
                if ($allowOverAv === '0') {
                    return response()->json([
                        'success' => false,
                        'message' => "Không thể cập nhật phòng {$lock->room_number} vì loại phòng {$avError['class_name']} sẽ bị hết phòng trống (AV < 0) vào ngày {$avError['date']}."
                    ], 422);
                }

                if (!$force) {
                    return response()->json([
                        'success' => false,
                        'require_confirm' => true,
                        'message' => "Cập nhật phòng {$lock->room_number} sẽ làm loại phòng {$avError['class_name']} bị âm phòng (AV < 0) vào ngày {$avError['date']}. Bạn có muốn tiếp tục?",
                    ], 422);
                }
            }

            // 2. Kiểm tra Unassignable SAU
            $unassignableViolation = $this->checkUnassignableBookingsAvailability(
                $room->room_class_id,
                $data['start_date'],
                $data['end_date'],
                $batchRoomNumbers,
                $lock->id
            );

            if (!empty($unassignableViolation)) {
                if ($unassignableConfig === '0') {
                    return response()->json([
                        'success' => false,
                        'message' => "Không thể cập nhật phòng {$lock->room_number} vì loại phòng {$unassignableViolation['class_name']} sẽ không đủ phòng trống liên tục để gán cho booking {$unassignableViolation['booking_code']} ({$unassignableViolation['arrival']} ~ {$unassignableViolation['departure']})."
                    ], 422);
                }

                if (!$force) {
                    return response()->json([
                        'success' => false,
                        'require_confirm' => true,
                        'message' => "Cập nhật phòng {$lock->room_number} sẽ làm loại phòng {$unassignableViolation['class_name']} không đủ phòng trống liên tục để gán cho booking {$unassignableViolation['booking_code']} ({$unassignableViolation['arrival']} ~ {$unassignableViolation['departure']}). Bạn có muốn tiếp tục?",
                    ], 422);
                }
            }
        }

        // Execute all updates inside an atomic transaction
        \Illuminate\Support\Facades\DB::transaction(function () use ($prepared, $request) {
            foreach ($prepared as $p) {
                $lock = $p['lock'];
                $data = $p['data'];
                $oldValues = $lock->toArray();

                $updateFields = [
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'reason' => $data['reason'] ?? $lock->reason,
                    'maintenance_percent' => isset($data['maintenance_percent']) ? (int)$data['maintenance_percent'] : $lock->maintenance_percent,
                ];
                if (!empty($data['lock_type'])) {
                    $updateFields['lock_type'] = $data['lock_type'];
                }

                $lock->update($updateFields);

                \App\Services\ActivityLogService::logUpdate(
                    $request,
                    $lock,
                    $oldValues,
                    'reservation',
                    'LockRoomPage',
                    "Cập nhật hàng loạt phòng khóa {$lock->room_number}",
                    $lock->room_number
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Đã cập nhật thành công " . count($prepared) . " phòng khóa!",
        ]);
    }

    // Helper functions

    private function resolveUserDisplayName($username)
    {
        if (empty($username)) return '';
        $user = \App\Models\User::where('username', $username)
            ->orWhere('employee_code', $username)
            ->first();
        return $user ? $user->name : $username;
    }

    /**
     * Get mock bookings matching the UI.
     * TODO: LƯU Ý QUAN TRỌNG - Khi phân hệ Đặt phòng (Bookings / Reservations) được hoàn thiện:
     * Thay thế hàm giả lập dữ liệu (Mock) này bằng câu truy vấn SQL thực tế để lấy dữ liệu đặt phòng
     * từ bảng `bookings` trong cơ sở dữ liệu.
     */
    private function getMockBookings()
    {
        return [
            ['room_number' => '401', 'room_type' => 'DLXD', 'start_date' => '2026-06-09', 'end_date' => '2026-06-13', 'booking_code' => 'GAL5333'],
            ['room_number' => '401', 'room_type' => 'DLXD', 'start_date' => '2026-06-18', 'end_date' => '2026-06-23', 'booking_code' => 'GAL5181'],
            ['room_number' => '402', 'room_type' => 'DLXTB', 'start_date' => '2026-06-09', 'end_date' => '2026-06-14', 'booking_code' => 'GAL5436'],
            ['room_number' => '402', 'room_type' => 'DLXTB', 'start_date' => '2026-06-15', 'end_date' => '2026-06-23', 'booking_code' => 'GAL5436'],
            ['room_number' => '402', 'room_type' => 'DLXTB', 'start_date' => '2026-06-26', 'end_date' => '2026-06-29', 'booking_code' => 'GAL4737'],
            ['room_number' => '403', 'room_type' => 'DLXTB', 'start_date' => '2026-06-09', 'end_date' => '2026-06-11', 'booking_code' => 'GAL5407'],
            ['room_number' => '403', 'room_type' => 'DLXTB', 'start_date' => '2026-06-13', 'end_date' => '2026-06-23', 'booking_code' => 'GAL5407'],
            ['room_number' => '404', 'room_type' => 'SUPT', 'start_date' => '2026-06-16', 'end_date' => '2026-06-18', 'booking_code' => 'GAL5424'],
            ['room_number' => '404', 'room_type' => 'SUPT', 'start_date' => '2026-06-18', 'end_date' => '2026-06-21', 'booking_code' => 'GAL4910'],
            ['room_number' => '404', 'room_type' => 'SUPT', 'start_date' => '2026-06-22', 'end_date' => '2026-06-24', 'booking_code' => 'GAL4532'],
            ['room_number' => '404', 'room_type' => 'SUPT', 'start_date' => '2026-06-25', 'end_date' => '2026-06-28', 'booking_code' => 'GAL4988'],
            ['room_number' => '405', 'room_type' => 'FAM', 'start_date' => '2026-06-09', 'end_date' => '2026-06-10', 'booking_code' => 'GAL5408'],
            ['room_number' => '405', 'room_type' => 'FAM', 'start_date' => '2026-06-13', 'end_date' => '2026-06-23', 'booking_code' => 'GAL5408'],
            ['room_number' => '405', 'room_type' => 'FAM', 'start_date' => '2026-06-22', 'end_date' => '2026-06-24', 'booking_code' => 'GAL4532'],
        ];
    }

    /**
     * Validate start and end date/time bounds.
     */
    private function validateLockPeriod($startDateStr, $endDateStr)
    {
        $start = \Carbon\Carbon::parse($startDateStr);
        $end = \Carbon\Carbon::parse($endDateStr);

        $latestRoll = \App\Models\SystemDateRoll::latest('id')->first();
        $sysDateStr = $latestRoll
            ? \Carbon\Carbon::parse($latestRoll->system_date)->toDateString()
            : \Carbon\Carbon::now('Asia/Ho_Chi_Minh')->toDateString();

        if ($start->toDateString() < $sysDateStr) {
            return 'Ngày bắt đầu khóa không được nhỏ hơn Ngày hệ thống (' . $sysDateStr . ').';
        }

        if ($end->lt($start)) {
            if ($start->isSameDay($end)) {
                return 'Giờ kết thúc không được nhỏ hơn giờ bắt đầu (trong cùng ngày).';
            }
            return 'Ngày mở khóa không được nhỏ hơn ngày bắt đầu.';
        }

        return null;
    }

    /**
     * Check if a room lock overlaps with other locks.
     */
    private function checkOverlapLocks($roomNumber, $startDate, $endDate, $excludeLockId = null)
    {
        $query = RoomLock::where('room_number', $roomNumber)
            ->where('is_active', 1)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where('start_date', '<=', $endDate)
                    ->where('end_date', '>=', $startDate);
            });

        if ($excludeLockId) {
            $query->where('id', '!=', $excludeLockId);
        }

        return $query->exists();
    }

    /**
     * Check overlap with real database bookings and mock bookings.
     */
    private function checkBookingOverlap($roomNumber, $startDateStr, $endDateStr)
    {
        $startStr = \Carbon\Carbon::parse($startDateStr)->format('Y-m-d');
        $endStr = \Carbon\Carbon::parse($endDateStr)->format('Y-m-d');

        // Query real database bookings for this room number
        $dbBookings = BookingRoom::with('booking')
            ->where('room_number', (string)$roomNumber)
            ->whereIn('status', [
                BookingRoom::STATUS_BOOKED,
                BookingRoom::STATUS_CHECKED_IN,
            ])
            ->get();

        foreach ($dbBookings as $b) {
            $arrStr = \Carbon\Carbon::parse($b->arrival_date)->format('Y-m-d');
            $depStr = \Carbon\Carbon::parse($b->departure_date)->format('Y-m-d');

            if ($startStr < $depStr && $endStr >= $arrStr) {
                return [
                    'booking_id' => $b->booking_id,
                    'booking_code' => $b->booking?->reservation_code ?? "BK-{$b->booking_id}",
                    'room_number' => (string)$b->room_number,
                    'start_date' => $arrStr,
                    'end_date' => $depStr,
                    'guest_name' => $b->guest_name ?? 'Inhouse Guest',
                ];
            }
        }

        $mockBookings = $this->getMockBookings();
        foreach ($mockBookings as $bk) {
            if ($bk['room_number'] === (string)$roomNumber) {
                $arrStr = \Carbon\Carbon::parse($bk['start_date'])->format('Y-m-d');
                $depStr = \Carbon\Carbon::parse($bk['end_date'])->format('Y-m-d');

                if ($startStr < $depStr && $endStr >= $arrStr) {
                    return $bk;
                }
            }
        }

        return null;
    }

    /**
     * Check AV constraints for room class.
     */
    private function checkAvForRoomClass($roomClassId, $startDateStr, $endDateStr, $excludeRoomNumber = null, int $additionalLocksCount = 1, array $batchLocks = [])
    {
        $start = \Carbon\Carbon::parse($startDateStr)->copy()->startOfDay();
        $end = \Carbon\Carbon::parse($endDateStr)->copy();

        $hasTime00 = (!str_contains($endDateStr, ' ') || str_ends_with($endDateStr, ' 00:00:00') || str_ends_with($endDateStr, ' 00:00'));
        $lastCheckDate = $end->copy()->startOfDay();

        if ($hasTime00 && $lastCheckDate->gt($start)) {
            $lastCheckDate->subDay();
        }

        $totalRooms = Room::where('room_class_id', $roomClassId)->where('is_internal', false)->count();
        if ($totalRooms === 0) {
            return null;
        }

        $roomClass = \App\Models\RoomClass::find($roomClassId);
        $roomClassCode = $roomClass?->code;

        $tempDate = $start->copy();
        while ($tempDate->lte($lastCheckDate)) {
            $dateStr = $tempDate->toDateString();

            $lockedQuery = RoomLock::where('is_active', 1)
                ->where('start_date', '<=', $dateStr . ' 23:59:59')
                ->where('end_date', '>=', $dateStr . ' 00:00:00')
                ->whereHas('room', function ($q) use ($roomClassId) {
                    $q->where('room_class_id', $roomClassId);
                });

            if ($excludeRoomNumber) {
                $lockedQuery->where('room_number', '!=', (string)$excludeRoomNumber);
            }

            $lockedCount = $lockedQuery->count();

            $bookingsCount = BookingRoom::where('room_class_id', $roomClassId)
                ->whereIn('status', [
                    BookingRoom::STATUS_BOOKED,
                    BookingRoom::STATUS_CHECKED_IN,
                ])
                ->whereDate('arrival_date', '<=', $dateStr)
                ->whereDate('departure_date', '>', $dateStr)
                ->whereHas('booking', function ($q) {
                    $q->whereNotIn('status', [\App\Models\Booking::STATUS_DELETED, \App\Models\Booking::STATUS_NO_SHOW])
                      ->where(function ($subQ) {
                          $subQ->whereDoesntHave('registrationStatus')
                               ->orWhereHas('registrationStatus', function ($rQ) {
                                   $rQ->where('is_availability', 1);
                               });
                      });
                })
                ->count();

            $av = $totalRooms - $lockedCount - $bookingsCount;

            $locksCountOnDate = $additionalLocksCount;
            if (!empty($batchLocks)) {
                $locksCountOnDate = 0;
                foreach ($batchLocks as $bLock) {
                    if (($bLock['room_class_id'] ?? null) == $roomClassId) {
                        $bStart = substr($bLock['start_date'], 0, 10);
                        $bEnd = substr($bLock['end_date'], 0, 10);
                        $bEndTime = strlen($bLock['end_date']) > 10 ? substr($bLock['end_date'], 11, 8) : '';

                        $bLastDate = $bEnd;
                        if (($bEndTime === '00:00:00' || $bEndTime === '00:00') && $bEnd > $bStart) {
                            $bLastDate = \Carbon\Carbon::parse($bEnd)->subDay()->toDateString();
                        }

                        if ($bStart <= $dateStr && $bLastDate >= $dateStr) {
                            $locksCountOnDate++;
                        }
                    }
                }
                if ($locksCountOnDate === 0) {
                    $locksCountOnDate = 1;
                }
            }

            if (($av - $locksCountOnDate) < 0) {
                return [
                    'date' => $tempDate->format('d/m/Y'),
                    'av' => $av,
                    'class_name' => $roomClass?->name ?? $roomClassCode
                ];
            }

            $tempDate = $tempDate->addDay();
        }

        return null;
    }

    /**
     * Check if locking the specified rooms causes any unassigned bookings of that class
     * to become unassignable continuously across their stay period.
     *
     * @param int $roomClassId
     * @param string $startDateStr
     * @param string $endDateStr
     * @param array $lockingRoomNumbers Array of room numbers being locked in this action
     * @param int|null $excludeLockId (Optional lock ID being updated)
     * @return array|null Violation info if an unassigned booking cannot be continuously accommodated, or null
     */
    private function checkUnassignableBookingsAvailability($roomClassId, $startDateStr, $endDateStr, array $lockingRoomNumbers, $excludeLockId = null)
    {
        $lockStart = \Carbon\Carbon::parse($startDateStr)->toDateString();
        $lockEndObj = \Carbon\Carbon::parse($endDateStr);
        $lockEnd = ($lockEndObj->format('H:i') > '00:00')
            ? $lockEndObj->copy()->addDay()->toDateString()
            : $lockEndObj->toDateString();

        // 1. Find all active unassigned bookings in this room class that overlap with the lock period
        $unassignedBookings = BookingRoom::with(['booking', 'roomClass'])
            ->where('room_class_id', $roomClassId)
            ->whereIn('status', [
                BookingRoom::STATUS_BOOKED,
                BookingRoom::STATUS_CHECKED_IN,
            ])
            ->where(function ($q) {
                $q->whereNull('room_number')
                  ->orWhere('room_number', '')
                  ->orWhere('room_number', 'like', '0%');
            })
            ->where('arrival_date', '<', $endDateStr)
            ->where('departure_date', '>', $startDateStr)
            ->whereHas('booking', function ($q) {
                $q->whereNotIn('status', [\App\Models\Booking::STATUS_DELETED, \App\Models\Booking::STATUS_NO_SHOW]);
            })
            ->get();

        if ($unassignedBookings->isEmpty()) {
            return null;
        }

        // 2. Get all physical rooms of this room class
        $physicalRooms = Room::where('room_class_id', $roomClassId)
            ->where('is_internal', false)
            ->where('room_number', 'not like', '0%')
            ->get();

        if ($physicalRooms->isEmpty()) {
            return null;
        }

        // 3. Pre-build occupancy intervals for each physical room
        $roomOccupancies = [];
        foreach ($physicalRooms as $pRoom) {
            $pNum = (string)$pRoom->room_number;
            $intervals = [];

            // If this room is being locked in this action
            if (in_array($pNum, $lockingRoomNumbers, true)) {
                $intervals[] = [$lockStart, $lockEnd];
            }

            // Existing active locks on this physical room
            $existingLocks = RoomLock::where('room_number', $pNum)
                ->where('is_active', 1)
                ->when($excludeLockId, fn($q) => $q->where('id', '!=', $excludeLockId))
                ->get(['start_date', 'end_date']);

            foreach ($existingLocks as $l) {
                $lStart = \Carbon\Carbon::parse($l->start_date)->toDateString();
                $lEndObj = \Carbon\Carbon::parse($l->end_date);
                $lEnd = ($lEndObj->format('H:i') > '00:00')
                    ? $lEndObj->copy()->addDay()->toDateString()
                    : $lEndObj->toDateString();
                $intervals[] = [$lStart, $lEnd];
            }

            // Existing assigned bookings on this physical room
            $assignedBookings = BookingRoom::where('room_number', $pNum)
                ->whereIn('status', [
                    BookingRoom::STATUS_BOOKED,
                    BookingRoom::STATUS_CHECKED_IN,
                ])
                ->whereHas('booking', function ($q) {
                    $q->whereNotIn('status', [\App\Models\Booking::STATUS_DELETED, \App\Models\Booking::STATUS_NO_SHOW]);
                })
                ->get(['arrival_date', 'departure_date']);

            foreach ($assignedBookings as $abk) {
                $intervals[] = [
                    \Carbon\Carbon::parse($abk->arrival_date)->toDateString(),
                    \Carbon\Carbon::parse($abk->departure_date)->toDateString(),
                ];
            }

            $roomOccupancies[$pNum] = $intervals;
        }

        // 4. For each unassigned booking, find an available physical room that is continuously free
        // and reserve it in-memory so other unassigned bookings cannot double-book it.
        foreach ($unassignedBookings as $unassigned) {
            $arrDate = \Carbon\Carbon::parse($unassigned->arrival_date)->toDateString();
            $depDate = \Carbon\Carbon::parse($unassigned->departure_date)->toDateString();

            $matchedRoom = null;
            foreach ($roomOccupancies as $pNum => $intervals) {
                $hasConflict = false;
                foreach ($intervals as [$iStart, $iEnd]) {
                    if ($iStart < $depDate && $iEnd > $arrDate) {
                        $hasConflict = true;
                        break;
                    }
                }
                if (!$hasConflict) {
                    $matchedRoom = $pNum;
                    break;
                }
            }

            if ($matchedRoom !== null) {
                // Reserve this physical room for this unassigned booking's stay interval
                $roomOccupancies[$matchedRoom][] = [$arrDate, $depDate];
            } else {
                // No physical room can continuously accommodate this unassigned booking
                $arrFmt = \Carbon\Carbon::parse($unassigned->arrival_date)->format('d/m/Y');
                $depFmt = \Carbon\Carbon::parse($unassigned->departure_date)->format('d/m/Y');
                $bCode = $unassigned->booking?->booking_code ?? $unassigned->booking?->reservation_code ?? "BK-{$unassigned->booking_id}";
                $cName = $unassigned->roomClass?->name ?? $unassigned->roomClass?->code ?? 'này';

                return [
                    'booking_id' => $unassigned->booking_id,
                    'booking_code' => $bCode,
                    'arrival' => $arrFmt,
                    'departure' => $depFmt,
                    'class_name' => $cName,
                    'unassigned_room_id' => $unassigned->id,
                ];
            }
        }

        return null;
    }

    /**
     * Check if user department has permissions to unlock.
     */
    private function checkUnlockDepartmentPermission(Request $request, RoomLock $lock)
    {
        return app(\App\Services\RoomLockPermissionService::class)->checkUnlockDepartmentPermission($request->user(), $lock);
    }

    /**
     * Check if user role has permission to unlock.
     */
    private function checkUnlockRolePermission(Request $request, RoomLock $lock)
    {
        return app(\App\Services\RoomLockPermissionService::class)->checkUnlockRolePermission($request->user(), $lock);
    }
}
