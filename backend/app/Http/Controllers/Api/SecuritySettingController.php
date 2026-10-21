<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecurityAllowedIp;
use App\Models\SecurityExemptUser;
use App\Models\SecuritySetting;
use App\Models\SecurityTimeWindow;
use App\Models\User;
use App\Services\AccessRestrictionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Cấu hình Bảo mật nâng cao (dùng chung toàn hệ thống, chỉ super admin được thay đổi).
 */
class SecuritySettingController extends Controller
{
    private function deny()
    {
        if (!Auth::user()?->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Chỉ quản trị hệ thống mới được cấu hình bảo mật nâng cao.'], 403);
        }

        return null;
    }

    private function snapshot(Request $request): array
    {
        $settings = SecuritySetting::current();
        $exemptIds = SecurityExemptUser::pluck('user_id');

        return [
            'settings' => [
                'autoLogoutEnabled' => $settings->auto_logout_enabled,
                'autoLogoutHours' => $settings->auto_logout_hours,
                'ipRestrictionEnabled' => $settings->ip_restriction_enabled,
                'timeRestrictionEnabled' => $settings->time_restriction_enabled,
            ],
            'ips' => SecurityAllowedIp::orderBy('id')->get(['id', 'name', 'ip']),
            'timeWindows' => SecurityTimeWindow::orderBy('id')->get()->map(fn ($w) => [
                'id' => $w->id,
                'days' => $w->dayNumbers(),
                'startTime' => $w->start_time,
                'endTime' => $w->end_time,
            ]),
            'exemptUsers' => User::whereIn('id', $exemptIds)->orderBy('name')
                ->get(['id', 'employee_code', 'name', 'email', 'job_title', 'department']),
            'myIp' => (string) $request->ip(),
        ];
    }

    /** GET /security-settings */
    public function show(Request $request)
    {
        if ($response = $this->deny()) {
            return $response;
        }

        return response()->json(['success' => true, 'data' => $this->snapshot($request)]);
    }

    /** GET /security-settings/my-ip */
    public function myIp(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['ip' => (string) $request->ip()]]);
    }

    /** PUT /security-settings */
    public function update(Request $request)
    {
        if ($response = $this->deny()) {
            return $response;
        }

        $data = $request->validate([
            'auto_logout_enabled'      => 'sometimes|boolean',
            'auto_logout_hours'        => 'sometimes|integer|min:1|max:720',
            'ip_restriction_enabled'   => 'sometimes|boolean',
            'time_restriction_enabled' => 'sometimes|boolean',
        ]);

        SecuritySetting::current()->update($data);

        return response()->json(['success' => true, 'data' => $this->snapshot($request)]);
    }

    private function validateIp(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'ip'   => 'required|string|max:64',
        ]);
        $data['ip'] = trim($data['ip']);
        if (!AccessRestrictionService::isValidRule($data['ip'])) {
            abort(response()->json(['success' => false, 'message' => 'Địa chỉ IP không hợp lệ (nhập IP đơn hoặc dải CIDR, ví dụ 192.168.1.0/24).'], 422));
        }

        return $data;
    }

    /** POST /security-settings/ips */
    public function storeIp(Request $request)
    {
        if ($response = $this->deny()) {
            return $response;
        }

        SecurityAllowedIp::create($this->validateIp($request));

        return response()->json(['success' => true, 'data' => $this->snapshot($request)], 201);
    }

    /** PUT /security-settings/ips/{id} */
    public function updateIp(Request $request, $id)
    {
        if ($response = $this->deny()) {
            return $response;
        }

        SecurityAllowedIp::findOrFail($id)->update($this->validateIp($request));

        return response()->json(['success' => true, 'data' => $this->snapshot($request)]);
    }

    /** DELETE /security-settings/ips/{id} */
    public function destroyIp(Request $request, $id)
    {
        if ($response = $this->deny()) {
            return $response;
        }

        $settings = SecuritySetting::current();
        if ($settings->ip_restriction_enabled && SecurityAllowedIp::count() <= 1) {
            return response()->json(['success' => false, 'message' => 'Không thể xóa địa chỉ IP cuối cùng khi đang bật giới hạn theo IP. Hãy tắt giới hạn trước.'], 422);
        }
        SecurityAllowedIp::findOrFail($id)->delete();

        return response()->json(['success' => true, 'data' => $this->snapshot($request)]);
    }

    private function validateWindow(Request $request): array
    {
        $data = $request->validate([
            'days'       => 'required|array|min:1',
            'days.*'     => 'integer|between:1,7',
            'start_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'end_time'   => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ]);
        if ($data['end_time'] < $data['start_time']) {
            abort(response()->json(['success' => false, 'message' => 'Giờ kết thúc phải lớn hơn hoặc bằng giờ bắt đầu.'], 422));
        }

        return [
            'days' => implode(',', collect($data['days'])->unique()->sort()->values()->all()),
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
        ];
    }

    /** POST /security-settings/time-windows */
    public function storeWindow(Request $request)
    {
        if ($response = $this->deny()) {
            return $response;
        }

        SecurityTimeWindow::create($this->validateWindow($request));

        return response()->json(['success' => true, 'data' => $this->snapshot($request)], 201);
    }

    /** PUT /security-settings/time-windows/{id} */
    public function updateWindow(Request $request, $id)
    {
        if ($response = $this->deny()) {
            return $response;
        }

        SecurityTimeWindow::findOrFail($id)->update($this->validateWindow($request));

        return response()->json(['success' => true, 'data' => $this->snapshot($request)]);
    }

    /** DELETE /security-settings/time-windows/{id} */
    public function destroyWindow(Request $request, $id)
    {
        if ($response = $this->deny()) {
            return $response;
        }

        $settings = SecuritySetting::current();
        if ($settings->time_restriction_enabled && SecurityTimeWindow::count() <= 1) {
            return response()->json(['success' => false, 'message' => 'Không thể xóa khoảng thời gian cuối cùng khi đang bật giới hạn theo thời gian. Hãy tắt giới hạn trước.'], 422);
        }
        SecurityTimeWindow::findOrFail($id)->delete();

        return response()->json(['success' => true, 'data' => $this->snapshot($request)]);
    }

    /** POST /security-settings/exempt-users  { user_ids: [] } */
    public function addExemptUsers(Request $request)
    {
        if ($response = $this->deny()) {
            return $response;
        }

        $data = $request->validate([
            'user_ids'   => 'required|array|min:1',
            'user_ids.*' => 'integer',
        ]);

        $validIds = User::whereIn('id', $data['user_ids'])->pluck('id');
        foreach ($validIds as $userId) {
            SecurityExemptUser::firstOrCreate(['user_id' => $userId]);
        }

        return response()->json(['success' => true, 'data' => $this->snapshot($request)]);
    }

    /** DELETE /security-settings/exempt-users/{userId} */
    public function removeExemptUser(Request $request, $userId)
    {
        if ($response = $this->deny()) {
            return $response;
        }

        SecurityExemptUser::where('user_id', $userId)->delete();

        return response()->json(['success' => true, 'data' => $this->snapshot($request)]);
    }
}
