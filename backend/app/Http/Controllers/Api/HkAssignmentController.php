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

        $group->load(['staffList.staff', 'rooms.room.roomClass']);
        return response()->json(['success' => true, 'data' => $this->formatGroup($group)]);
    }

    /**
     * DELETE /api/hk/assignments/groups/{groupId}
     */
    public function destroyGroup($groupId)
    {
        $group = HkAssignmentGroup::findOrFail($groupId);
        $group->delete(); // cascade xóa staff + rooms của nhóm
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

        $group->load(['staffList.staff', 'rooms.room.roomClass']);
        return response()->json(['success' => true, 'data' => $this->formatGroup($group)]);
    }

    /**
     * DELETE /api/hk/assignments/groups/{groupId}/rooms/{roomId}
     */
    public function removeRoom($groupId, $roomId)
    {
        HkAssignmentGroupRoom::where('group_id', $groupId)->where('room_id', $roomId)->delete();
        return response()->json(['success' => true]);
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
