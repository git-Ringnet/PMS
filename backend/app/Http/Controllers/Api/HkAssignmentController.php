<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HkAssignment;
use App\Models\HkAssignmentGroup;
use App\Models\HkAssignmentGroupRoom;
use App\Models\HkAssignmentGroupStaff;
use App\Models\HkStaff;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HkAssignmentController extends Controller
{
    // =========================================================
    // STAFF management
    // =========================================================

    /** GET /api/hk/staff */
    public function staffIndex(Request $request)
    {
        $query = HkStaff::orderBy('sort_order')->orderBy('id');

        if ($request->boolean('show_hidden', false)) {
            // trả tất cả (kể cả ẩn)
        } else {
            $query->where('is_hidden', false);
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    /** POST /api/hk/staff */
    public function staffStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $maxOrder = HkStaff::max('sort_order') ?? 0;
        $staff = HkStaff::create([
            'name'       => $validated['name'],
            'is_active'  => true,
            'is_hidden'  => false,
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json(['success' => true, 'data' => $staff], 201);
    }

    /** PUT /api/hk/staff/{id} — ẩn/hiện hoặc đổi tên */
    public function staffUpdate(Request $request, $id)
    {
        $staff = HkStaff::findOrFail($id);

        $validated = $request->validate([
            'name'      => 'sometimes|string|max:100',
            'is_hidden' => 'sometimes|boolean',
        ]);

        $staff->update($validated);

        return response()->json(['success' => true, 'data' => $staff]);
    }

    /** DELETE /api/hk/staff/{id} — chỉ xóa khi chưa có phân công */
    public function staffDestroy($id)
    {
        $staff = HkStaff::findOrFail($id);

        if ($staff->hasAssignments()) {
            return response()->json([
                'success' => false,
                'message' => 'Nhân viên đã phát sinh dữ liệu phân công, không thể xóa.',
            ], 422);
        }

        $staff->delete();
        return response()->json(['success' => true]);
    }

    // =========================================================
    // ASSIGNMENT (header: date + shift)
    // =========================================================

    /**
     * GET /api/hk/assignments?date=2026-08-12&shift_id=1
     * Trả về toàn bộ phân công của 1 ngày + ca (gồm groups, staff, rooms)
     */
    public function index(Request $request)
    {
        $request->validate([
            'date'     => 'required|date',
            'shift_id' => 'required|integer',
        ]);

        $assignment = HkAssignment::with([
            'shift',
            'groups.staffList.staff',
            'groups.rooms.room.roomClass',
        ])
            ->where('work_date', $request->date)
            ->where('shift_id', $request->shift_id)
            ->first();

        return response()->json([
            'success' => true,
            'data'    => $assignment ? $this->formatAssignment($assignment) : null,
        ]);
    }

    /** POST /api/hk/assignments — tạo assignment header nếu chưa có */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'work_date' => 'required|date',
            'shift_id'  => 'required|integer|exists:shifts,id',
            'notes'     => 'nullable|string',
        ]);

        $assignment = HkAssignment::firstOrCreate(
            ['work_date' => $validated['work_date'], 'shift_id' => $validated['shift_id']],
            ['notes' => $validated['notes'] ?? null, 'created_by' => auth()->id()]
        );

        $assignment->load(['shift', 'groups.staffList.staff', 'groups.rooms.room.roomClass']);

        // Mở lịch phân công (chưa có phòng/nhân viên) không ghi log riêng; log ghi khi tạo nhóm phân công (dòng 72)
        $request->attributes->set('_activity_logged', true);

        return response()->json([
            'success' => true,
            'data'    => $this->formatAssignment($assignment),
        ], 201);
    }

    // =========================================================
    // GROUPS
    // =========================================================

    /**
     * POST /api/hk/assignments/{assignmentId}/groups
     * Tạo nhóm mới + phân công NV + phòng cùng lúc
     */
    public function storeGroup(Request $request, $assignmentId)
    {
        $assignment = HkAssignment::findOrFail($assignmentId);

        $validated = $request->validate([
            'color'    => 'nullable|string|max:30',
            'staff_ids' => 'required|array|min:1',
            'staff_ids.*' => 'integer|exists:hk_staff,id',
            'room_ids' => 'required|array|min:1',
            'room_ids.*' => 'integer|exists:rooms,id',
            'room_snapshots' => 'nullable|array', // ['room_id' => ['room_status_snapshot'=>'...', 'booking_status_snapshot'=>'...']]
        ]);

        $logPrevious = $this->logPreviousGroups($assignment->id, $validated['room_ids']);
        $logPreviousStaff = collect(array_unique($logPrevious))->mapWithKeys(fn ($gid) => [$gid => $this->logStaffNames((int) $gid)])->all();

        DB::transaction(function () use ($assignment, $validated, &$group) {
            // Kiểm tra NV đã ở nhóm khác trong cùng ca chưa
            $existingStaffInCa = HkAssignmentGroupStaff::whereHas('group', function ($q) use ($assignment) {
                $q->where('assignment_id', $assignment->id);
            })->whereIn('staff_id', $validated['staff_ids'])->pluck('staff_id')->toArray();

            if (!empty($existingStaffInCa)) {
                $names = HkStaff::whereIn('id', $existingStaffInCa)->pluck('name')->implode(', ');
                abort(422, "Nhân viên đã được phân công nhóm khác trong ca này: {$names}");
            }

            // Xóa các phòng này khỏi nhóm cũ trong cùng ca (tránh 1 phòng 2 nhóm)
            $existingGroupIds = HkAssignmentGroup::where('assignment_id', $assignment->id)->pluck('id');
            HkAssignmentGroupRoom::whereIn('group_id', $existingGroupIds)
                ->whereIn('room_id', $validated['room_ids'])
                ->delete();

            // Xóa nhóm rỗng
            HkAssignmentGroup::where('assignment_id', $assignment->id)
                ->whereDoesntHave('rooms')
                ->delete();

            $maxOrder = HkAssignmentGroup::where('assignment_id', $assignment->id)->max('sort_order') ?? 0;
            $group = HkAssignmentGroup::create([
                'assignment_id' => $assignment->id,
                'color'         => $validated['color'] ?? '#0ea5e9',
                'sort_order'    => $maxOrder + 1,
            ]);

            // Gán NV
            foreach ($validated['staff_ids'] as $staffId) {
                HkAssignmentGroupStaff::create(['group_id' => $group->id, 'staff_id' => $staffId]);
            }

            // Gán phòng với snapshot
            $snapshots = $validated['room_snapshots'] ?? [];
            foreach ($validated['room_ids'] as $roomId) {
                $snap = $snapshots[$roomId] ?? [];
                HkAssignmentGroupRoom::create([
                    'group_id'               => $group->id,
                    'room_id'                => $roomId,
                    'room_status_snapshot'   => $snap['room_status_snapshot'] ?? null,
                    'booking_status_snapshot'=> $snap['booking_status_snapshot'] ?? null,
                    'assigned_at'            => now(),
                ]);
            }
        });

        // Dòng 72: "* Tạo phân công phòng các phòng: 502, 503, - Nhân Viên: A, B, - Ngày: 04-09-2026, - Ca: Tối"
        $staff = $this->logStaffNames($group->id);
        $this->logMovedRooms($logPrevious, $logPreviousStaff, $staff);
        $rooms = $this->logRoomNumbers(array_diff($validated['room_ids'], array_keys($logPrevious)));
        if ($rooms) {
            $this->logAssign('Add', '* Tạo phân công phòng các phòng: ' . implode(', ', $rooms) . ", - Nhân Viên: {$staff}, " . $this->logContext($assignment), $rooms);
        }

        $group->load(['staffList.staff', 'rooms.room.roomClass']);
        return response()->json(['success' => true, 'data' => $this->formatGroup($group)], 201);
    }

    /**
     * PUT /api/hk/assignments/groups/{groupId}
     * Cập nhật danh sách NV của nhóm
     */
    public function updateGroup(Request $request, $groupId)
    {
        $group = HkAssignmentGroup::with('assignment')->findOrFail($groupId);

        $validated = $request->validate([
            'staff_ids'   => 'required|array',
            'staff_ids.*' => 'integer|exists:hk_staff,id',
        ]);

        $logOldStaff = $this->logStaffNames($group->id);

        DB::transaction(function () use ($group, $validated) {
            // Kiểm tra NV mới thêm đã ở nhóm khác chưa
            $currentStaffIds = $group->staffList()->pluck('staff_id')->toArray();
            $newStaffIds = array_diff($validated['staff_ids'], $currentStaffIds);

            $existingInOtherGroup = HkAssignmentGroupStaff::whereHas('group', function ($q) use ($group) {
                $q->where('assignment_id', $group->assignment_id)->where('id', '!=', $group->id);
            })->whereIn('staff_id', $newStaffIds)->pluck('staff_id')->toArray();

            if (!empty($existingInOtherGroup)) {
                $names = HkStaff::whereIn('id', $existingInOtherGroup)->pluck('name')->implode(', ');
                abort(422, "Nhân viên đã được phân công nhóm khác: {$names}");
            }

            // Sync NV
            $group->staffList()->delete();
            foreach ($validated['staff_ids'] as $staffId) {
                HkAssignmentGroupStaff::create(['group_id' => $group->id, 'staff_id' => $staffId]);
            }
        });

        // Dòng 74: "* Cập nhật phân công phòng: - Nhân Viên: A, B, - Ngày, - Ca\nNhân Viên: A, B -> Nhân Viên: A"
        $logNewStaff = $this->logStaffNames($group->id);
        if ($logNewStaff !== $logOldStaff) {
            $this->logAssign('Modify', "* Cập nhật phân công phòng: - Nhân Viên: {$logOldStaff}, " . $this->logContext($group->assignment)
                . "\nNhân Viên: {$logOldStaff} -> Nhân Viên: {$logNewStaff}");
        }

        $group->load(['staffList.staff', 'rooms.room.roomClass']);
        return response()->json(['success' => true, 'data' => $this->formatGroup($group)]);
    }

    /**
     * DELETE /api/hk/assignments/groups/{groupId}
     */
    public function destroyGroup($groupId)
    {
        $group = HkAssignmentGroup::with('assignment')->findOrFail($groupId);
        $logStaff = $this->logStaffNames($group->id);
        $logRooms = $this->logRoomNumbers(HkAssignmentGroupRoom::where('group_id', $group->id)->pluck('room_id')->all());
        $group->delete(); // cascade xóa staff + rooms của nhóm

        // Dòng 77: "* Xóa lịch phân công các phòng 101, 102, - Nhân viên: X, - Ngày 04-09-2026, - Ca: Tối"
        $this->logAssign('Cancel', '* Xóa lịch phân công các phòng ' . implode(', ', $logRooms) . ", - Nhân viên: {$logStaff}, " . $this->logContext($group->assignment), $logRooms);

        return response()->json(['success' => true]);
    }

    // =========================================================
    // ROOMS in group
    // =========================================================

    /**
     * POST /api/hk/assignments/groups/{groupId}/rooms
     * Thêm phòng vào nhóm (batch)
     */
    public function addRooms(Request $request, $groupId)
    {
        $group = HkAssignmentGroup::with('assignment')->findOrFail($groupId);

        $validated = $request->validate([
            'room_ids'   => 'required|array|min:1',
            'room_ids.*' => 'integer|exists:rooms,id',
            'room_snapshots' => 'nullable|array',
        ]);

        $logPrevious = $this->logPreviousGroups($group->assignment_id, $validated['room_ids'], $group->id);
        $logPreviousStaff = collect(array_unique($logPrevious))->mapWithKeys(fn ($gid) => [$gid => $this->logStaffNames((int) $gid)])->all();
        $logAlready = HkAssignmentGroupRoom::where('group_id', $group->id)->whereIn('room_id', $validated['room_ids'])->pluck('room_id')->all();

        DB::transaction(function () use ($group, $validated) {
            $existingGroupIds = HkAssignmentGroup::where('assignment_id', $group->assignment_id)->pluck('id');
            // Xóa phòng khỏi nhóm cũ trong cùng ca
            HkAssignmentGroupRoom::whereIn('group_id', $existingGroupIds)
                ->whereIn('room_id', $validated['room_ids'])
                ->delete();

            // Xóa nhóm rỗng (trừ nhóm hiện tại)
            HkAssignmentGroup::where('assignment_id', $group->assignment_id)
                ->where('id', '!=', $group->id)
                ->whereDoesntHave('rooms')
                ->delete();

            $snapshots = $validated['room_snapshots'] ?? [];
            foreach ($validated['room_ids'] as $roomId) {
                $snap = $snapshots[$roomId] ?? [];
                HkAssignmentGroupRoom::updateOrCreate(
                    ['group_id' => $group->id, 'room_id' => $roomId],
                    [
                        'room_status_snapshot'    => $snap['room_status_snapshot'] ?? null,
                        'booking_status_snapshot' => $snap['booking_status_snapshot'] ?? null,
                        'assigned_at'             => now(),
                    ]
                );
            }
        });

        // Dòng 73, 76: chuyển phòng từ nhóm khác / thêm phòng mới cho phân công
        $staff = $this->logStaffNames($group->id);
        $this->logMovedRooms($logPrevious, $logPreviousStaff, $staff);
        $added = $this->logRoomNumbers(array_diff($validated['room_ids'], array_keys($logPrevious), $logAlready));
        if ($added) {
            $this->logAssign('Add', '* Thêm phân công phòng ' . implode(', ', $added) . " cho phân công : - Nhân viên: {$staff}, " . $this->logContext($group->assignment), $added);
        }

        $group->load(['staffList.staff', 'rooms.room.roomClass']);
        return response()->json(['success' => true, 'data' => $this->formatGroup($group)]);
    }

    /**
     * DELETE /api/hk/assignments/groups/{groupId}/rooms/{roomId}
     */
    public function removeRoom($groupId, $roomId)
    {
        $deleted = HkAssignmentGroupRoom::where('group_id', $groupId)->where('room_id', $roomId)->delete();

        // Dòng 75: "* Xóa phân công phòng 106 cho phân công - Nhân viên: A, B"
        if ($deleted) {
            $room = $this->logRoomNumbers([(int) $roomId]);
            $this->logAssign('Cancel', '* Xóa phân công phòng ' . implode(', ', $room) . ' cho phân công - Nhân viên: ' . $this->logStaffNames((int) $groupId), $room);
        }

        return response()->json(['success' => true]);
    }

    // =========================================================
    // Lịch sử thao tác (sheet GHI LOG dòng 72-77)
    // =========================================================

    private function logStaffNames(int $groupId): string
    {
        return HkStaff::whereIn('id', HkAssignmentGroupStaff::where('group_id', $groupId)->pluck('staff_id'))->pluck('name')->implode(', ');
    }

    private function logRoomNumbers(array $roomIds): array
    {
        return \App\Models\Room::whereIn('id', $roomIds)->orderBy('room_number')->pluck('room_number')->map(fn ($n) => (string) $n)->all();
    }

    private function logContext(?HkAssignment $assignment): string
    {
        if (!$assignment) {
            return '';
        }
        $shift = \App\Models\Shift::whereKey($assignment->shift_id)->value('name') ?? $assignment->shift_id;

        return '- Ngày: ' . \App\Services\ActivityLogService::d($assignment->work_date) . ", - Ca: {$shift}";
    }

    /** Nhóm cũ (cùng ca) đang giữ các phòng: [room_id => group_id]. */
    private function logPreviousGroups(int $assignmentId, array $roomIds, ?int $exceptGroupId = null): array
    {
        return HkAssignmentGroupRoom::whereIn('group_id', HkAssignmentGroup::where('assignment_id', $assignmentId)->pluck('id'))
            ->whereIn('room_id', $roomIds)
            ->when($exceptGroupId, fn ($q) => $q->where('group_id', '!=', $exceptGroupId))
            ->pluck('group_id', 'room_id')
            ->all();
    }

    private function logAssign(string $action, string $description, array $roomNumbers = []): void
    {
        try {
            \App\Services\ActivityLogService::record($action, $description, [
                'screen' => \App\Support\ActivityScreen::HK_ASSIGN,
                'room_code' => $roomNumbers,
                'target_type' => 'HkAssignmentGroup',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Log HK assignment error: ' . $e->getMessage());
        }
    }

    /** Dòng 73: "* Chuyển phân công cho phòng 106 từ - Nhân Viên: A, B -> Nhân Viên: C, D". */
    private function logMovedRooms(array $previousGroups, array $previousStaff, string $newStaff): void
    {
        foreach ($previousGroups as $roomId => $oldGroupId) {
            $room = $this->logRoomNumbers([$roomId])[0] ?? (string) $roomId;
            $this->logAssign('Modify', "* Chuyển phân công cho phòng {$room} từ - Nhân Viên: " . ($previousStaff[$oldGroupId] ?? '') . " -> Nhân Viên: {$newStaff}", [$room]);
        }
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function formatAssignment(HkAssignment $a): array
    {
        return [
            'id'         => $a->id,
            'work_date'  => $a->work_date?->toDateString(),
            'shift_id'   => $a->shift_id,
            'shift'      => $a->shift ? ['id' => $a->shift->id, 'name' => $a->shift->name] : null,
            'notes'      => $a->notes,
            'groups'     => $a->groups->map(fn($g) => $this->formatGroup($g))->values(),
        ];
    }

    private function formatGroup(HkAssignmentGroup $g): array
    {
        return [
            'id'         => $g->id,
            'assignment_id' => $g->assignment_id,
            'color'      => $g->color,
            'sort_order' => $g->sort_order,
            'staff_list' => $g->staffList->map(fn($s) => [
                'id'       => $s->id,
                'staff_id' => $s->staff_id,
                'name'     => $s->staff?->name,
            ])->values(),
            'rooms'      => $g->rooms->map(fn($r) => [
                'id'                      => $r->id,
                'room_id'                 => $r->room_id,
                'room_number'             => $r->room?->room_number,
                'room_class_name'         => $r->room?->roomClass?->name,
                'room_status_snapshot'    => $r->room_status_snapshot,
                'booking_status_snapshot' => $r->booking_status_snapshot,
                'assigned_at'             => $r->assigned_at?->toDateTimeString(),
            ])->values(),
        ];
    }
}
