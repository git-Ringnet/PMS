<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
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

        // Lọc theo action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
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

        // Lọc theo mã đăng ký
        if ($request->filled('registration_code')) {
            $regCode = $request->registration_code;
            $query->where(function ($q) use ($regCode) {
                $q->where('target_label', 'like', "%{$regCode}%")
                  ->orWhere('description', 'like', "%{$regCode}%");
            });
        }

        // Lọc theo mã phòng
        if ($request->filled('room_code')) {
            $roomCode = $request->room_code;
            $query->where(function ($q) use ($roomCode) {
                $q->where('target_label', 'like', "%{$roomCode}%")
                  ->orWhere('description', 'like', "%{$roomCode}%");
            });
        }

        // Lọc theo khoảng thời gian (theo múi giờ Asia/Ho_Chi_Minh)
        if ($request->filled('date_from')) {
            $dateFrom = \Carbon\Carbon::createFromFormat('Y-m-d', $request->date_from, 'Asia/Ho_Chi_Minh')->startOfDay()->timezone('UTC');
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($request->filled('date_to')) {
            $dateTo = \Carbon\Carbon::createFromFormat('Y-m-d', $request->date_to, 'Asia/Ho_Chi_Minh')->endOfDay()->timezone('UTC');
            $query->where('created_at', '<=', $dateTo);
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
        $allowedSort = ['id', 'created_at', 'user_name', 'action', 'module', 'component'];
        if (in_array($sortBy, $allowedSort)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        // Phân trang
        $perPage = min((int) $request->input('per_page', 30), 100);
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
        $today = now()->timezone('Asia/Ho_Chi_Minh')->startOfDay()->timezone('UTC');

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
}
