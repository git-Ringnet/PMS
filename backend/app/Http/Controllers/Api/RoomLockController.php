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
            'room_id' => 'required|exists:rooms,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'reason' => 'nullable|string|max:255',
            'maintenance_percent' => 'nullable|integer|min:0|max:100',
            'status' => 'nullable|string|max:50',
            'username' => 'nullable|string|max:50',
            'lock_type' => 'required|string|in:OOO,OOS',
            'force' => 'nullable',
        ], [
            'room_id.required' => 'Mã phòng là bắt buộc.',
            'room_id.exists' => 'Phòng không tồn tại.',
            'start_date.required' => 'Ngày bắt đầu là bắt buộc.',
            'start_date.date' => 'Ngày bắt đầu không đúng định dạng ngày giờ.',
            'end_date.required' => 'Ngày kết thúc là bắt buộc.',
            'end_date.date' => 'Ngày kết thúc không đúng định dạng ngày giờ.',
            'lock_type.required' => 'Loại khóa phòng là bắt buộc.',
            'lock_type.in' => 'Loại khóa phòng phải là OOO hoặc OOS.',
        ]);

        $room = Room::findOrFail($validated['room_id']);

        // 1. Validate date/time bounds
        $timeError = $this->validateLockPeriod($validated['start_date'], $validated['end_date']);
        if ($timeError) {
            return response()->json(['success' => false, 'message' => $timeError], 422);
        }

        // 2. Check for overlapping OOO/OOS locks
        $hasOverlapLocks = $this->checkOverlapLocks($validated['room_id'], $validated['start_date'], $validated['end_date']);
        if ($hasOverlapLocks) {
            return response()->json(['success' => false, 'message' => 'Không được phép khóa phòng do phòng đã có lịch khóa OOO/OOS khác trùng lặp thời gian này.'], 422);
        }

        // 3. Check booking overlap
        $booking = $this->checkBookingOverlap($room->room_number, $validated['start_date'], $validated['end_date']);
        if ($booking && !filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN)) {
            $allowLockConfig = \App\Models\HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->first()?->value ?? '0';
            $bkEndStr = \Carbon\Carbon::parse($booking['end_date'])->format('d/m/Y');
            $bkStartStr = \Carbon\Carbon::parse($booking['start_date'])->format('d/m/Y');

            if ($allowLockConfig === '0') {
                return response()->json([
                    'success' => false,
                    'message' => "Không được phép khóa phòng vì trùng lịch với booking {$booking['booking_code']} ({$bkStartStr} ~ {$bkEndStr})."
                ], 422);
            } else {
                return response()->json([
                    'success' => false,
                    'require_confirm' => true,
                    'message' => "Phòng này trùng lịch đặt trước của booking {$booking['booking_code']} ({$bkStartStr} ~ {$bkEndStr}). Bạn có chắc chắn vẫn muốn khóa phòng không?",
                    'booking_code' => $booking['booking_code']
                ], 422);
            }
        }

        // 4. Check AV capacity
        $allowOverAv = \App\Models\HotelConfig::where('name', 'AllowInputOverAV')->first()?->value ?? '0';
        if ($allowOverAv === '0') {
            $avError = $this->checkAvForRoomClass($room->room_class_id, $validated['start_date'], $validated['end_date']);
            if ($avError) {
                return response()->json([
                    'success' => false,
                    'message' => "Không thể khóa phòng vì loại phòng {$avError['class_name']} sẽ bị hết phòng trống (AV <= 0) vào ngày {$avError['date']}."
                ], 422);
            }
        }

        // Deactivate previous active locks for this room
        RoomLock::where('room_id', $validated['room_id'])->where('is_active', true)->update(['is_active' => false]);

        $validated['is_active'] = true;
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
        Room::where('id', $validated['room_id'])->update(['status' => 'maintenance']);

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
            'room_ids' => 'required|array',
            'room_ids.*' => 'exists:rooms,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'reason' => 'nullable|string|max:255',
            'maintenance_percent' => 'nullable|integer|min:0|max:100',
            'status' => 'nullable|string|max:50',
            'username' => 'nullable|string|max:50',
            'lock_type' => 'required|string|in:OOO,OOS',
            'force' => 'nullable',
        ], [
            'room_ids.required' => 'Danh sách phòng là bắt buộc.',
            'room_ids.array' => 'Danh sách phòng phải là một mảng.',
            'room_ids.*.exists' => 'Một trong các phòng đã chọn không tồn tại.',
            'start_date.required' => 'Ngày bắt đầu là bắt buộc.',
            'start_date.date' => 'Ngày bắt đầu không đúng định dạng ngày giờ.',
            'end_date.required' => 'Ngày kết thúc là bắt buộc.',
            'end_date.date' => 'Ngày kết thúc không đúng định dạng ngày giờ.',
            'lock_type.required' => 'Loại khóa phòng là bắt buộc.',
            'lock_type.in' => 'Loại khóa phòng phải là OOO hoặc OOS.',
        ]);

        // 1. Validate date/time bounds
        $timeError = $this->validateLockPeriod($validated['start_date'], $validated['end_date']);
        if ($timeError) {
            return response()->json(['success' => false, 'message' => $timeError], 422);
        }

        // 2. Pre-check overlap locks, bookings, and AV for ALL selected rooms
        $allowLockConfig = \App\Models\HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->first()?->value ?? '0';
        $allowOverAv = \App\Models\HotelConfig::where('name', 'AllowInputOverAV')->first()?->value ?? '0';

        foreach ($validated['room_ids'] as $roomId) {
            $room = Room::findOrFail($roomId);

            // Check overlap locks
            if ($this->checkOverlapLocks($roomId, $validated['start_date'], $validated['end_date'])) {
                return response()->json(['success' => false, 'message' => "Phòng {$room->room_number} đã có lịch khóa OOO/OOS khác trùng lặp thời gian này."], 422);
            }

            // Check booking overlap
            $booking = $this->checkBookingOverlap($room->room_number, $validated['start_date'], $validated['end_date']);
            if ($booking && !filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN)) {
                $bkEndStr = \Carbon\Carbon::parse($booking['end_date'])->format('d/m/Y');
                $bkStartStr = \Carbon\Carbon::parse($booking['start_date'])->format('d/m/Y');

                if ($allowLockConfig === '0') {
                    return response()->json([
                        'success' => false,
                        'message' => "Không được phép khóa phòng {$room->room_number} vì trùng lịch với booking {$booking['booking_code']} ({$bkStartStr} ~ {$bkEndStr})."
                    ], 422);
                } else {
                    return response()->json([
                        'success' => false,
                        'require_confirm' => true,
                        'message' => "Phòng {$room->room_number} trùng lịch đặt trước của booking {$booking['booking_code']} ({$bkStartStr} ~ {$bkEndStr}). Bạn có chắc chắn vẫn muốn khóa phòng không?",
                        'booking_code' => $booking['booking_code']
                    ], 422);
                }
            }

            // Check AV
            if ($allowOverAv === '0') {
                $avError = $this->checkAvForRoomClass($room->room_class_id, $validated['start_date'], $validated['end_date']);
                if ($avError) {
                    return response()->json([
                        'success' => false,
                        'message' => "Không thể khóa phòng {$room->room_number} vì loại phòng {$avError['class_name']} sẽ bị hết phòng trống (AV <= 0) vào ngày {$avError['date']}."
                    ], 422);
                }
            }
        }

        $locksCreated = [];
        $username = $validated['username'] ?? $request->user()?->username ?? $request->user()?->name ?? 'NB0016';
        $status = $validated['status'] ?? 'New';
        $mPercent = $validated['maintenance_percent'] ?? 0;

        foreach ($validated['room_ids'] as $roomId) {
            // Deactivate previous active locks for this room
            RoomLock::where('room_id', $roomId)->where('is_active', true)->update(['is_active' => false]);

            $lock = RoomLock::create([
                'room_id' => $roomId,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'reason' => $validated['reason'],
                'maintenance_percent' => $mPercent,
                'status' => $status,
                'username' => $username,
                'lock_type' => $validated['lock_type'],
                'is_active' => true,
            ]);

            // Update room status to maintenance
            Room::where('id', $roomId)->update(['status' => 'maintenance']);

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
            'room_ids' => 'required|array',
            'room_ids.*' => 'exists:rooms,id',
        ]);

        // Check department permission for all active locks on these rooms
        foreach ($validated['room_ids'] as $roomId) {
            $locks = RoomLock::where('room_id', $roomId)->where('is_active', true)->get();
            foreach ($locks as $lock) {
                $deptError = $this->checkUnlockDepartmentPermission($request, $lock);
                if ($deptError) {
                    $room = Room::find($roomId);
                    return response()->json([
                        'success' => false,
                        'message' => "Không thể mở khóa phòng {$room->room_number}: {$deptError}"
                    ], 403);
                }
            }
        }

        foreach ($validated['room_ids'] as $roomId) {
            // Deactivate active locks
            RoomLock::where('room_id', $roomId)->where('is_active', true)->update(['is_active' => false]);

            // Update room status to available
            Room::where('id', $roomId)->update(['status' => 'available']);
        }

        return response()->json([
            'success' => true,
            'message' => count($validated['room_ids']) . ' rooms unlocked successfully.',
        ]);
    }

    /**
     * Get lock history of a specific room.
     */
    public function history($roomId)
    {
        $history = RoomLock::where('room_id', $roomId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $history,
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
            'is_active' => 'nullable|boolean',
            'force' => 'nullable',
        ], [
            'start_date.required' => 'Ngày bắt đầu là bắt buộc.',
            'start_date.date' => 'Ngày bắt đầu không đúng định dạng ngày giờ.',
            'end_date.required' => 'Ngày kết thúc là bắt buộc.',
            'end_date.date' => 'Ngày kết thúc không đúng định dạng ngày giờ.',
            'lock_type.required' => 'Loại khóa phòng là bắt buộc.',
            'lock_type.in' => 'Loại khóa phòng phải là OOO hoặc OOS.',
        ]);

        $room = Room::findOrFail($lock->room_id);

        // 1. Validate date/time bounds
        $timeError = $this->validateLockPeriod($validated['start_date'], $validated['end_date']);
        if ($timeError) {
            return response()->json(['success' => false, 'message' => $timeError], 422);
        }

        // 2. Active lock edit restriction:
        // Lock has started (start_date <= now)
        $lockStart = \Carbon\Carbon::parse($lock->start_date);
        $reqStart = \Carbon\Carbon::parse($validated['start_date']);
        if ($lockStart->lte(now()) && !$lockStart->eq($reqStart)) {
            return response()->json([
                'success' => false,
                'message' => 'Không được phép điều chỉnh ngày bắt đầu đối với phòng đang trong giai đoạn khóa.'
            ], 422);
        }

        // 3. Check for overlapping locks
        $hasOverlapLocks = $this->checkOverlapLocks($lock->room_id, $validated['start_date'], $validated['end_date'], $lock->id);
        if ($hasOverlapLocks) {
            return response()->json(['success' => false, 'message' => 'Không được phép khóa phòng do phòng đã có lịch khóa OOO/OOS khác trùng lặp thời gian này.'], 422);
        }

        // 4. Check booking overlap
        $booking = $this->checkBookingOverlap($room->room_number, $validated['start_date'], $validated['end_date']);
        if ($booking && !filter_var($request->input('force'), FILTER_VALIDATE_BOOLEAN)) {
            $allowLockConfig = \App\Models\HotelConfig::where('name', 'AllowLockRoomCauseUnassignableRoomBK')->first()?->value ?? '0';
            $bkEndStr = \Carbon\Carbon::parse($booking['end_date'])->format('d/m/Y');
            $bkStartStr = \Carbon\Carbon::parse($booking['start_date'])->format('d/m/Y');

            if ($allowLockConfig === '0') {
                return response()->json([
                    'success' => false,
                    'message' => "Không được phép cập nhật khóa phòng vì trùng lịch với booking {$booking['booking_code']} ({$bkStartStr} ~ {$bkEndStr})."
                ], 422);
            } else {
                return response()->json([
                    'success' => false,
                    'require_confirm' => true,
                    'message' => "Phòng này trùng lịch đặt trước của booking {$booking['booking_code']} ({$bkStartStr} ~ {$bkEndStr}). Bạn có chắc chắn vẫn muốn cập nhật lịch khóa phòng không?",
                    'booking_code' => $booking['booking_code']
                ], 422);
            }
        }

        // 5. Check AV capacity
        $allowOverAv = \App\Models\HotelConfig::where('name', 'AllowInputOverAV')->first()?->value ?? '0';
        if ($allowOverAv === '0') {
            $avError = $this->checkAvForRoomClass($room->room_class_id, $validated['start_date'], $validated['end_date'], $lock->room_id);
            if ($avError) {
                return response()->json([
                    'success' => false,
                    'message' => "Không thể cập nhật khóa phòng vì loại phòng {$avError['class_name']} sẽ bị hết phòng trống (AV <= 0) vào ngày {$avError['date']}."
                ], 422);
            }
        }

        // Remove non-schema fields
        unset($validated['force']);

        $lock->update($validated);

        if ($lock->is_active) {
            Room::where('id', $lock->room_id)->update(['status' => 'maintenance']);
        } else {
            // Check if there are other active locks, otherwise restore status to available
            $hasActive = RoomLock::where('room_id', $lock->room_id)->where('is_active', true)->exists();
            if (!$hasActive) {
                Room::where('id', $lock->room_id)->update(['status' => 'available']);
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

        $roomId = $lock->room_id;
        $lock->delete();

        // Check if there are other active locks
        $hasActive = RoomLock::where('room_id', $roomId)->where('is_active', true)->exists();
        if (!$hasActive) {
            Room::where('id', $roomId)->update(['status' => 'available']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Room lock deleted successfully',
        ]);
    }

    // Helper functions

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
            return 'Ngày kết thúc không được nhỏ hơn ngày bắt đầu.';
        }

        return null;
    }

    /**
     * Check if a room lock overlaps with other locks.
     */
    private function checkOverlapLocks($roomId, $startDate, $endDate, $excludeLockId = null)
    {
        $query = RoomLock::where('room_id', $roomId)
            ->where('is_active', true)
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
    private function checkAvForRoomClass($roomClassId, $startDateStr, $endDateStr, $excludeRoomId = null)
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
            
            $lockedQuery = RoomLock::where('is_active', true)
                ->where('start_date', '<=', $dateStr . ' 23:59:59')
                ->where('end_date', '>=', $dateStr . ' 00:00:00')
                ->whereHas('room', function($q) use ($roomClassId) {
                    $q->where('room_class_id', $roomClassId);
                });
                
            if ($excludeRoomId) {
                $lockedQuery->where('room_id', '!=', $excludeRoomId);
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
}
