<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Nội dung cập nhật hệ thống: admin nhập, mọi user xem ở icon chuông.
 */
class SystemUpdateController extends Controller
{
    private const LIST_LIMIT = 50;

    /** GET /system-updates */
    public function index()
    {
        $userId = Auth::id();
        $readIds = DB::table('system_update_reads')->where('user_id', $userId)->pluck('system_update_id')->all();

        $items = SystemUpdate::orderByDesc('update_date')->orderByDesc('id')->limit(self::LIST_LIMIT)->get()
            ->map(fn (SystemUpdate $update) => [
                'id' => $update->id,
                'updateDate' => $update->update_date->format('d/m/Y'),
                'content' => $update->content,
                'createdBy' => $update->created_by,
                'createdAt' => $update->created_at?->format('d/m/Y H:i'),
                'isRead' => in_array($update->id, $readIds, true),
            ]);

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'unread' => $items->where('isRead', false)->count(),
                'can_manage' => (bool) Auth::user()?->isAdmin(),
            ],
        ]);
    }

    /**
     * POST /system-updates/read  { ids?: int[] }
     * Có ids thì chỉ đánh dấu các nội dung đó, bỏ trống thì đánh dấu tất cả.
     */
    public function markRead(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'integer',
        ]);

        $userId = Auth::id();
        $existing = DB::table('system_update_reads')->where('user_id', $userId)->pluck('system_update_id')->all();
        $query = SystemUpdate::whereNotIn('id', $existing);
        if (isset($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        }

        $rows = $query->pluck('id')
            ->map(fn ($id) => ['system_update_id' => $id, 'user_id' => $userId, 'read_at' => now()])
            ->all();
        if ($rows) {
            DB::table('system_update_reads')->insert($rows);
        }

        return response()->json(['success' => true]);
    }

    /** POST /system-updates  (chỉ admin) */
    public function store(Request $request)
    {
        if (!Auth::user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Chỉ admin mới được nhập nội dung cập nhật.'], 403);
        }

        $data = $request->validate([
            'content'     => 'required|string|max:5000',
            'update_date' => 'nullable|date',
        ]);

        $update = SystemUpdate::create([
            'content' => trim($data['content']),
            'update_date' => $data['update_date'] ?? now()->timezone('Asia/Ho_Chi_Minh')->toDateString(),
            'created_by' => Auth::user()?->username,
        ]);

        return response()->json(['success' => true, 'data' => $update], 201);
    }

    /** DELETE /system-updates/{id}  (chỉ admin) */
    public function destroy($id)
    {
        if (!Auth::user()?->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Chỉ admin mới được xóa nội dung cập nhật.'], 403);
        }

        SystemUpdate::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }
}
