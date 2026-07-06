<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomLock;
use App\Models\Room;
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

        // Adjust dates according to start_date being today or in the future
        $rawStart = $request->input('start_date');
        $rawEnd = $request->input('end_date');

        $localNow = \Carbon\Carbon::now('Asia/Ho_Chi_Minh');
        $localTodayStr = $localNow->format('Y-m-d');

        $reqStart = \Carbon\Carbon::parse($validated['start_date']);
        $reqStartDateStr = $reqStart->format('Y-m-d');

        $hasNoTime = !str_contains($rawStart, ' ') || str_ends_with($rawStart, ' 00:00:00') || str_ends_with($rawStart, ' 00:00');
        if ($hasNoTime) {
            if ($reqStartDateStr === $localTodayStr) {
                // Start date is today, use current time
                $validated['start_date'] = $localNow->format('Y-m-d H:i:s');
            } elseif ($reqStartDateStr > $localTodayStr) {
                // Start date is in the future, start at 00:00:00
                $validated['start_date'] = $reqStart->format('Y-m-d 00:00:00');
            } else {
                // Start date is in the past, start at 00:00:00
                $validated['start_date'] = $reqStart->format('Y-m-d 00:00:00');
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

        // 3 & 4. Check booking overlap and AV capacity
        $booking = $this->checkBookingOverlap($room->room_number, $validated['start_date'], $validated['end_date']);
        $avError = $this->checkAvForRoomClass($room->room_class_id, $validated['start_date'], $validated['end_date'], $room->room_number);

        $hasBookingOverlap = !empty($booking);
        $hasAvOverlap = !empty($avError);

        if (($hasBookingOverlap || $hasAvOverlap) && !filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN)) {
            $allowLockConfig = \App\Models\HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->first()?->value ?? '0';
            $allowOverAv = \App\Models\HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->first()?->value ?? '0';

            // Check if any strict block is triggered
            $bookingBlocked = $hasBookingOverlap && ($allowLockConfig === '0');
            $avBlocked = $hasAvOverlap && ($allowOverAv === '0');

            if ($bookingBlocked || $avBlocked) {
                $messages = [];
                if ($bookingBlocked) {
                    $bkEndStr = \Carbon\Carbon::parse($booking['end_date'])->format('d/m/Y');
                    $bkStartStr = \Carbon\Carbon::parse($booking['start_date'])->format('d/m/Y');
                    $messages[] = "Không được phép khóa phòng vì trùng lịch với booking {$booking['booking_code']} ({$bkStartStr} ~ {$bkEndStr}).";
                }
                if ($avBlocked) {
                    $messages[] = "Không thể khóa phòng vì loại phòng {$avError['class_name']} sẽ bị hết phòng trống (AV <= 0) vào ngày {$avError['date']}.";
                }
                return response()->json([
                    'success' => false,
                    'message' => implode(' ', $messages)
                ], 422);
            }

            // Warnings requiring confirmation
            $warnings = [];
            if ($hasBookingOverlap) {
                $bkEndStr = \Carbon\Carbon::parse($booking['end_date'])->format('d/m/Y');
                $bkStartStr = \Carbon\Carbon::parse($booking['start_date'])->format('d/m/Y');
                $warnings[] = "Phòng này trùng lịch đặt trước của booking {$booking['booking_code']} ({$bkStartStr} ~ {$bkEndStr}).";
            }
            if ($hasAvOverlap) {
                $warnings[] = "Loại phòng {$avError['class_name']} sẽ bị âm phòng vào ngày {$avError['date']}.";
            }

            $message = implode(' ', $warnings) . " Bạn có chắc chắn vẫn muốn khóa phòng không?";
            if ($hasAvOverlap && !$hasBookingOverlap) {
                $message = "Phòng bị âm. Bạn có muốn tiếp tục thao tác?";
            }

            return response()->json([
                'success' => false,
                'require_confirm' => true,
                'message' => $message,
                'booking_code' => $booking['booking_code'] ?? null
            ], 422);
        }

        $validated['room_number'] = $room->room_number;
        $validated['is_active'] = 1;
        if (!isset($validated['username'])) {
            $validated['username'] = $request->user()?->username ?? $request->user()?->name ?? 'NB0016';
        }
        if (!isset($validated['status'])) {
            $validated['status'] = 'New';
        }

        // Remove non-schema field
        unset($validated['force']);

        $lock = RoomLock::create($validated);

        // Update room status to maintenance
        Room::where('room_number', $room->room_number)->update(['status' => 'maintenance']);

        $lock->load(['room.roomForm', 'room.roomClass']);

        return response()->json([
            'success' => true,
            'data' => $lock,
        ], 201);
    }

    /**
     * Bulk lock multiple rooms.
     */
    public function bulkLock(Request $request)
    {
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

        // Resolve room numbers to rooms and IDs
        $roomIds = [];
        $rooms = [];
        foreach ($validated['room_numbers'] as $roomNumber) {
            $room = Room::where('room_number', $roomNumber)->firstOrFail();
            $roomIds[] = $room->id;
            $rooms[$room->id] = $room;
        }

        // Adjust dates according to start_date being today or in the future
        $rawStart = $request->input('start_date');
        $rawEnd = $request->input('end_date');

        $localNow = \Carbon\Carbon::now('Asia/Ho_Chi_Minh');
        $localTodayStr = $localNow->format('Y-m-d');

        $reqStart = \Carbon\Carbon::parse($validated['start_date']);
        $reqStartDateStr = $reqStart->format('Y-m-d');

        $hasNoTime = !str_contains($rawStart, ' ') || str_ends_with($rawStart, ' 00:00:00') || str_ends_with($rawStart, ' 00:00');
        if ($hasNoTime) {
            if ($reqStartDateStr === $localTodayStr) {
                // Start date is today, use current time
                $validated['start_date'] = $localNow->format('Y-m-d H:i:s');
            } elseif ($reqStartDateStr > $localTodayStr) {
                // Start date is in the future, start at 00:00:00
                $validated['start_date'] = $reqStart->format('Y-m-d 00:00:00');
            } else {
                // Start date is in the past, start at 00:00:00
                $validated['start_date'] = $reqStart->format('Y-m-d 00:00:00');
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

        // 2. Pre-check overlap locks, bookings, and AV for ALL selected rooms
        $allowLockConfig = \App\Models\HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->first()?->value ?? '0';
        $allowOverAv = \App\Models\HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->first()?->value ?? '0';

        $bookingBlockedRooms = [];
        $avBlockedRooms = [];
        $bookingWarningRooms = [];
        $avWarningRooms = [];

        foreach ($roomIds as $roomId) {
            $room = $rooms[$roomId];

            // Check overlap locks (always strict block)
            if ($this->checkOverlapLocks($room->room_number, $validated['start_date'], $validated['end_date'])) {
                return response()->json(['success' => false, 'message' => "Không được phép khóa phòng do phòng {$room->room_number} đã có lịch khóa OOO/OOS khác trùng lặp thời gian này."], 422);
            }

            // Check booking overlap
            $booking = $this->checkBookingOverlap($room->room_number, $validated['start_date'], $validated['end_date']);
            if ($booking) {
                if ($allowLockConfig === '0') {
                    $bookingBlockedRooms[] = [
                        'room_number' => $room->room_number,
                        'booking_code' => $booking['booking_code'],
                        'start' => \Carbon\Carbon::parse($booking['start_date'])->format('d/m/Y'),
                        'end' => \Carbon\Carbon::parse($booking['end_date'])->format('d/m/Y')
                    ];
                } else {
                    $bookingWarningRooms[] = [
                        'room_number' => $room->room_number,
                        'booking_code' => $booking['booking_code'],
                        'start' => \Carbon\Carbon::parse($booking['start_date'])->format('d/m/Y'),
                        'end' => \Carbon\Carbon::parse($booking['end_date'])->format('d/m/Y')
                    ];
                }
            }

            // Check AV
            $avError = $this->checkAvForRoomClass($room->room_class_id, $validated['start_date'], $validated['end_date'], $room->room_number);
            if ($avError) {
                if ($allowOverAv === '0') {
                    $avBlockedRooms[] = [
                        'room_number' => $room->room_number,
                        'class_name' => $avError['class_name'],
                        'date' => $avError['date']
                    ];
                } else {
                    $avWarningRooms[] = [
                        'room_number' => $room->room_number,
                        'class_name' => $avError['class_name'],
                        'date' => $avError['date']
                    ];
                }
            }
        }

        // If any strict blocks
        if ((!empty($bookingBlockedRooms) || !empty($avBlockedRooms)) && !filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN)) {
            $messages = [];
            foreach ($bookingBlockedRooms as $b) {
                $messages[] = "Không được phép khóa phòng {$b['room_number']} vì trùng lịch với booking {$b['booking_code']} ({$b['start']} ~ {$b['end']}).";
            }
            foreach ($avBlockedRooms as $av) {
                $messages[] = "Không thể khóa phòng {$av['room_number']} vì loại phòng {$av['class_name']} sẽ bị hết phòng trống (AV <= 0) vào ngày {$av['date']}.";
            }
            return response()->json([
                'success' => false,
                'message' => implode(' ', $messages)
            ], 422);
        }

        // If warnings
        if ((!empty($bookingWarningRooms) || !empty($avWarningRooms)) && !filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN)) {
            $warnings = [];
            foreach ($bookingWarningRooms as $b) {
                $warnings[] = "Phòng {$b['room_number']} trùng lịch đặt trước của booking {$b['booking_code']} ({$b['start']} ~ {$b['end']}).";
            }
            foreach ($avWarningRooms as $av) {
                $warnings[] = "Phòng {$av['room_number']} thuộc loại phòng {$av['class_name']} sẽ bị âm phòng vào ngày {$av['date']}.";
            }

            $message = implode(' ', $warnings) . " Bạn có chắc chắn vẫn muốn khóa các phòng đã chọn?";
            if (!empty($avWarningRooms) && empty($bookingWarningRooms)) {
                $message = "Có phòng bị âm. Bạn có muốn tiếp tục thao tác?";
            }

            return response()->json([
                'success' => false,
                'require_confirm' => true,
                'message' => $message
            ], 422);
        }

        $locksCreated = [];
        $username = $validated['username'] ?? $request->user()?->username ?? $request->user()?->name ?? 'NB0016';
        $status = $validated['status'] ?? 'New';
        $mPercent = $validated['maintenance_percent'] ?? 0;

        foreach ($roomIds as $roomId) {
            $room = $rooms[$roomId];
            $lock = RoomLock::create([
                'room_number' => $room->room_number,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'reason' => $validated['reason'],
                'maintenance_percent' => $mPercent,
                'status' => $status,
                'username' => $username,
                'lock_type' => $validated['lock_type'],
                'is_active' => 1,
            ]);

            // Update room status to maintenance
            Room::where('room_number', $room->room_number)->update(['status' => 'maintenance']);

            $locksCreated[] = $lock;
        }

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

        foreach ($locks as $lock) {
            $affectedRoomNumbers[] = $lock->room_number;

            $lockStartLocal = \Carbon\Carbon::parse($lock->start_date->format('Y-m-d H:i:s'), 'Asia/Ho_Chi_Minh');

            // If it is a future lock, delete it
            if ($lockStartLocal->gt($localNow)) {
                \App\Services\ActivityLogService::logDelete(
                    $request,
                    $lock,
                    'reservation',
                    'LockRoomPage',
                    "Hủy kế hoạch khóa phòng tương lai của phòng {$lock->room_number} (Hành động: Unlock, Giai đoạn: " . $lock->start_date->format('d/m/Y') . " ~ " . $lock->end_date->format('d/m/Y') . ")",
                    $lock->room_number
                );
                $lock->delete();
            } else {
                // Else, mark it as unlocked (is_active = 2)
                $oldValues = $lock->toArray();
                $lock->update([
                    'is_active' => 2,
                    'unlock_username' => $unlockUsername,
                    'unlocked_at' => $now
                ]);

                \App\Services\ActivityLogService::logUpdate(
                    $request,
                    $lock,
                    $oldValues,
                    'reservation',
                    'LockRoomPage',
                    "Mở khóa phòng {$lock->room_number} (Hành động: Unlock, Giai đoạn: " . $lock->start_date->format('d/m/Y H:i') . " ~ " . $lock->end_date->format('d/m/Y H:i') . ")",
                    $lock->room_number
                );
            }
        }

        // Check and update room statuses for affected rooms
        $affectedRoomNumbers = array_unique($affectedRoomNumbers);
        foreach ($affectedRoomNumbers as $roomNumber) {
            $hasActive = RoomLock::where('room_number', $roomNumber)->where('is_active', 1)->exists();
            if (!$hasActive) {
                Room::where('room_number', $roomNumber)->update(['status' => 'available']);
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

        $localNow = \Carbon\Carbon::now('Asia/Ho_Chi_Minh');
        $localTodayStr = $localNow->format('Y-m-d');

        $reqStart = \Carbon\Carbon::parse($validated['start_date']);
        $reqStartDateStr = $reqStart->format('Y-m-d');

        $origLockStart = \Carbon\Carbon::parse($lock->start_date);

        if ($reqStartDateStr === $origLockStart->format('Y-m-d')) {
            if (str_ends_with($rawStart, '00:00:00') || str_ends_with($rawStart, '00:00')) {
                $validated['start_date'] = $origLockStart->format('Y-m-d H:i:s');
            }
        } else {
            if (str_ends_with($rawStart, '00:00:00') || str_ends_with($rawStart, '00:00')) {
                if ($reqStartDateStr === $localTodayStr) {
                    $validated['start_date'] = $localNow->format('Y-m-d H:i:s');
                } elseif ($reqStartDateStr > $localTodayStr) {
                    $validated['start_date'] = $reqStart->format('Y-m-d 00:00:00');
                } else {
                    $validated['start_date'] = $reqStart->format('Y-m-d 00:00:00');
                }
            }
        }

        $defaultEndTime = \App\Models\HotelConfig::where('name', 'FrmOOO_DefineLockByTime')->first()?->value ?? '23:59';
        if (str_ends_with($rawEnd, '23:59:00') || str_ends_with($rawEnd, '23:59') || str_ends_with($rawEnd, $defaultEndTime . ':00')) {
            $reqEnd = \Carbon\Carbon::parse($validated['end_date']);
            $validated['end_date'] = $reqEnd->format('Y-m-d ' . $defaultEndTime . ':59');
        }

        // 1. Validate date/time bounds
        $timeError = $this->validateLockPeriod($validated['start_date'], $validated['end_date']);
        if ($timeError) {
            return response()->json(['success' => false, 'message' => $timeError], 422);
        }

        // 2. Active lock edit restriction:
        // Lock has started (start_date <= now) and not yet finished (end_date >= now)
        $now = now();
        $lockStart = \Carbon\Carbon::parse($lock->start_date);
        $lockEnd = \Carbon\Carbon::parse($lock->end_date);
        $reqStart = \Carbon\Carbon::parse($validated['start_date']);

        $isCurrentlyLocked = $lockStart->lte($now) && $lockEnd->gte($now);
        if ($isCurrentlyLocked && !$lockStart->eq($reqStart)) {
            return response()->json([
                'success' => false,
                'message' => 'Không được phép điều chỉnh ngày bắt đầu đối với phòng đang trong giai đoạn khóa.'
            ], 422);
        }

        // 3. Check for overlapping locks
        $hasOverlapLocks = $this->checkOverlapLocks($lock->room_number, $validated['start_date'], $validated['end_date'], $lock->id);
        if ($hasOverlapLocks) {
            return response()->json(['success' => false, 'message' => 'Không được phép khóa phòng do phòng đã có lịch khóa OOO/OOS khác trùng lặp thời gian này.'], 422);
        }

        // 4. Check booking overlap and AV capacity
        $booking = $this->checkBookingOverlap($room->room_number, $validated['start_date'], $validated['end_date']);
        $avError = $this->checkAvForRoomClass($room->room_class_id, $validated['start_date'], $validated['end_date'], $lock->room_number);

        $hasBookingOverlap = !empty($booking);
        $hasAvOverlap = !empty($avError);

        if (($hasBookingOverlap || $hasAvOverlap) && !filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN)) {
            $allowLockConfig = \App\Models\HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->first()?->value ?? '0';
            $allowOverAv = \App\Models\HotelConfig::where('name', 'AllowOverRoomTypeRoomKind')->first()?->value ?? '0';

            // Check if any strict block is triggered
            $bookingBlocked = $hasBookingOverlap && ($allowLockConfig === '0');
            $avBlocked = $hasAvOverlap && ($allowOverAv === '0');

            if ($bookingBlocked || $avBlocked) {
                $messages = [];
                if ($bookingBlocked) {
                    $bkEndStr = \Carbon\Carbon::parse($booking['end_date'])->format('d/m/Y');
                    $bkStartStr = \Carbon\Carbon::parse($booking['start_date'])->format('d/m/Y');
                    $messages[] = "Không được phép cập nhật khóa phòng vì trùng lịch với booking {$booking['booking_code']} ({$bkStartStr} ~ {$bkEndStr}).";
                }
                if ($avBlocked) {
                    $messages[] = "Không thể cập nhật khóa phòng vì loại phòng {$avError['class_name']} sẽ bị hết phòng trống (AV <= 0) vào ngày {$avError['date']}.";
                }
                return response()->json([
                    'success' => false,
                    'message' => implode(' ', $messages)
                ], 422);
            }

            // Warnings requiring confirmation
            $warnings = [];
            if ($hasBookingOverlap) {
                $bkEndStr = \Carbon\Carbon::parse($booking['end_date'])->format('d/m/Y');
                $bkStartStr = \Carbon\Carbon::parse($booking['start_date'])->format('d/m/Y');
                $warnings[] = "Phòng này trùng lịch đặt trước của booking {$booking['booking_code']} ({$bkStartStr} ~ {$bkEndStr}).";
            }
            if ($hasAvOverlap) {
                $warnings[] = "Loại phòng {$avError['class_name']} sẽ bị âm phòng vào ngày {$avError['date']}.";
            }

            $message = implode(' ', $warnings) . " Bạn có chắc chắn vẫn muốn cập nhật lịch khóa phòng không?";
            if ($hasAvOverlap && !$hasBookingOverlap) {
                $message = "Phòng bị âm. Bạn có muốn tiếp tục thao tác?";
            }

            return response()->json([
                'success' => false,
                'require_confirm' => true,
                'message' => $message,
                'booking_code' => $booking['booking_code'] ?? null
            ], 422);
        }

        // Remove non-schema fields
        unset($validated['force']);

        $lock->update($validated);

        if ($lock->is_active == 1) {
            Room::where('room_number', $lock->room_number)->update(['status' => 'maintenance']);
        } else {
            // Check if there are other active locks, otherwise restore status to available
            $hasActive = RoomLock::where('room_number', $lock->room_number)->where('is_active', 1)->exists();
            if (!$hasActive) {
                Room::where('room_number', $lock->room_number)->update(['status' => 'available']);
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
        \App\Services\ActivityLogService::logDelete(
            $request,
            $lock,
            'reservation',
            'LockRoomPage',
            "Xóa lịch khóa phòng {$lock->room_number} (Hành động: Delete, Giai đoạn: " . $lock->start_date->format('d/m/Y H:i') . " ~ " . $lock->end_date->format('d/m/Y H:i') . ")",
            $lock->room_number
        );
        $lock->delete();

        // Check if there are other active locks
        $hasActive = RoomLock::where('room_number', $roomNumber)->where('is_active', 1)->exists();
        if (!$hasActive) {
            Room::where('room_number', $roomNumber)->update(['status' => 'available']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Room lock deleted successfully',
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
     * Check overlap with mock bookings.
     */
    private function checkBookingOverlap($roomNumber, $startDateStr, $endDateStr)
    {
        $start = \Carbon\Carbon::parse($startDateStr)->copy()->startOfDay();
        $end = \Carbon\Carbon::parse($endDateStr)->copy()->endOfDay();

        $bookings = $this->getMockBookings();
        foreach ($bookings as $bk) {
            if ($bk['room_number'] === $roomNumber) {
                $bkStart = \Carbon\Carbon::parse($bk['start_date'])->startOfDay();
                $bkEnd = \Carbon\Carbon::parse($bk['end_date'])->endOfDay();

                if ($start->lte($bkEnd) && $end->gte($bkStart)) {
                    return $bk;
                }
            }
        }
        return null;
    }

    /**
     * Check AV constraints for room class.
     */
    private function checkAvForRoomClass($roomClassId, $startDateStr, $endDateStr, $excludeRoomNumber = null)
    {
        $start = \Carbon\Carbon::parse($startDateStr)->copy()->startOfDay();
        $end = \Carbon\Carbon::parse($endDateStr)->copy()->endOfDay();

        $totalRooms = Room::where('room_class_id', $roomClassId)->count();
        if ($totalRooms === 0) {
            return null;
        }

        $roomClass = \App\Models\RoomClass::find($roomClassId);
        $roomClassCode = $roomClass?->code;

        $tempDate = $start->copy();
        while ($tempDate->lte($end)) {
            $dateStr = $tempDate->toDateString();

            $lockedQuery = RoomLock::where('is_active', 1)
                ->where('start_date', '<=', $dateStr . ' 23:59:59')
                ->where('end_date', '>=', $dateStr . ' 00:00:00')
                ->whereHas('room', function ($q) use ($roomClassId) {
                    $q->where('room_class_id', $roomClassId);
                });

            if ($excludeRoomNumber) {
                $lockedQuery->where('room_number', '!=', $excludeRoomNumber);
            }

            $lockedCount = $lockedQuery->count();

            $bookingsCount = 0;
            $mockBookings = $this->getMockBookings();
            foreach ($mockBookings as $bk) {
                if ($bk['room_type'] === $roomClassCode) {
                    $bkStart = \Carbon\Carbon::parse($bk['start_date'])->startOfDay();
                    $bkEnd = \Carbon\Carbon::parse($bk['end_date'])->endOfDay();
                    if ($tempDate->gte($bkStart) && $tempDate->lte($bkEnd)) {
                        $bookingsCount++;
                    }
                }
            }

            $av = $totalRooms - $lockedCount - $bookingsCount;
            if (($av - 1) < 0) {
                return [
                    'date' => $tempDate->format('d/m/Y'),
                    'av' => $av,
                    'class_name' => $roomClass?->name ?? $roomClassCode
                ];
            }

            $tempDate->addDay();
        }

        return null;
    }

    /**
     * Check if user department has permissions to unlock.
     */
    private function checkUnlockDepartmentPermission(Request $request, RoomLock $lock)
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        $checkOoo = \App\Models\HotelConfig::where('name', 'OOOCheckDepartment')->first()?->value ?? '0';
        $checkOos = \App\Models\HotelConfig::where('name', 'OOSCheckDepartment')->first()?->value ?? '0';

        $isOoo = strtoupper($lock->lock_type) === 'OOO';
        $isOos = strtoupper($lock->lock_type) === 'OOS';

        if (($isOoo && $checkOoo === '1') || ($isOos && $checkOos === '1')) {
            $locker = \App\Models\User::where('username', $lock->username)
                ->orWhere('name', $lock->username)
                ->first();

            if ($locker && !empty($locker->department) && $user->department !== $locker->department) {
                return "Bạn không thuộc bộ phận đã thực hiện khóa phòng này (Bộ phận: {$locker->department}), không thể mở khóa.";
            }
        }

        return null;
    }

    /**
     * Check if user job title / role has permission to unlock.
     */
    private function checkUnlockRolePermission(Request $request, RoomLock $lock)
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        $isOoo = strtoupper($lock->lock_type) === 'OOO';
        $isOos = strtoupper($lock->lock_type) === 'OOS';

        $configName = $isOoo ? 'OOORoleUserUnlock' : 'OOSRoleUserUnlock';
        $allowedRolesStr = \App\Models\HotelConfig::where('name', $configName)->first()?->value;

        if (empty($allowedRolesStr)) {
            return null; // No restriction if empty
        }

        $allowedRoles = array_map('trim', explode(',', $allowedRolesStr));
        if (empty($allowedRoles)) {
            return null;
        }

        $userJobTitle = strtolower($user->job_title ?? '');
        $userJobCode = strtolower($user->job_title_code ?? '');
        $userDeptCode = strtolower($user->department_code ?? '');
        $username = strtolower($user->username ?? '');

        $matched = false;
        foreach ($allowedRoles as $role) {
            $roleLower = strtolower($role);

            // Direct matches
            if ($roleLower === $username || $roleLower === $userJobCode || $roleLower === $userDeptCode) {
                $matched = true;
                break;
            }

            // Check if role name matches or is contained in job_title (e.g. "Admin" in "Administrator" or "Tổng giám đốc")
            if (str_contains($userJobTitle, $roleLower)) {
                $matched = true;
                break;
            }

            // Check abbreviations / common mappings
            if ($roleLower === 'admin' && (str_contains($userJobTitle, 'quản trị') || str_contains($userJobTitle, 'tổng giám đốc') || $username === 'admin' || $username === 'testuser')) {
                $matched = true;
                break;
            }
            if ($roleLower === 'fom' && (str_contains($userJobTitle, 'lễ tân') || $userDeptCode === 'fo' || str_contains($userJobTitle, 'trưởng bộ phận'))) {
                $matched = true;
                break;
            }
            if ($roleLower === 'hkm' && (str_contains($userJobTitle, 'buồng') || str_contains($userJobTitle, 'hk') || $userDeptCode === 'hk' || str_contains($userJobTitle, 'trưởng hk'))) {
                $matched = true;
                break;
            }
            if ($roleLower === 'sales' && (str_contains($userJobTitle, 'sales') || str_contains($userJobTitle, 'kinh doanh') || $userDeptCode === 'sales')) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            $typeName = $isOoo ? 'OOO' : 'OOS';
            return "Tài khoản của bạn không có vai trò được phép mở khóa phòng {$typeName} (Quyền yêu cầu: {$allowedRolesStr}).";
        }

        return null;
    }
}
