<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogService
{
    /**
     * Ghi log tổng quát.
     */
    public static function log(array $data): ActivityLog
    {
        return ActivityLog::create(array_merge([
            'created_at' => now(),
        ], $data));
    }

    /**
     * Ghi log đăng nhập thành công hoặc thất bại.
     */
    public static function logLogin(Request $request, ?User $user, bool $success): ActivityLog
    {
        return self::log([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? $request->input('username', 'unknown'),
            'employee_code' => $user?->employee_code,
            'action' => $success ? 'login' : 'login_failed',
            'module' => 'auth',
            'component' => 'LoginPage',
            'description' => $success
                ? "Đăng nhập thành công"
                : "Đăng nhập thất bại (tài khoản: {$request->input('username')})",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_method' => 'POST',
            'request_url' => $request->fullUrl(),
            'response_status' => $success ? 200 : 422,
        ]);
    }

    /**
     * Ghi log đăng xuất.
     */
    public static function logLogout(Request $request, User $user): ActivityLog
    {
        return self::log([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'employee_code' => $user->employee_code,
            'action' => 'logout',
            'module' => 'auth',
            'component' => 'LoginPage',
            'description' => "Đăng xuất khỏi hệ thống",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_method' => 'POST',
            'request_url' => $request->fullUrl(),
            'response_status' => 200,
        ]);
    }

    /**
     * Ghi log tạo mới.
     */
    public static function logCreate(
        Request $request,
        Model $model,
        string $module,
        string $component,
        string $description,
        ?string $targetLabel = null
    ): ActivityLog {
        $user = $request->user();
        return self::log([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? '',
            'employee_code' => $user?->employee_code,
            'action' => 'create',
            'module' => $module,
            'component' => $component,
            'description' => $description,
            'target_type' => class_basename($model),
            'target_id' => $model->id,
            'target_label' => $targetLabel,
            'new_values' => $model->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'response_status' => 201,
        ]);
    }

    /**
     * Ghi log cập nhật.
     */
    public static function logUpdate(
        Request $request,
        Model $model,
        array $oldValues,
        string $module,
        string $component,
        string $description,
        ?string $targetLabel = null
    ): ActivityLog {
        $user = $request->user();
        $newValues = $model->fresh()?->toArray() ?? $model->toArray();
        $diff = self::diffValues($oldValues, $newValues);

        return self::log([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? '',
            'employee_code' => $user?->employee_code,
            'action' => 'update',
            'module' => $module,
            'component' => $component,
            'description' => $description,
            'target_type' => class_basename($model),
            'target_id' => $model->id,
            'target_label' => $targetLabel,
            'old_values' => $diff['old'],
            'new_values' => $diff['new'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'response_status' => 200,
        ]);
    }

    /**
     * Ghi log xóa.
     */
    public static function logDelete(
        Request $request,
        Model $model,
        string $module,
        string $component,
        string $description,
        ?string $targetLabel = null
    ): ActivityLog {
        $user = $request->user();
        return self::log([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? '',
            'employee_code' => $user?->employee_code,
            'action' => 'delete',
            'module' => $module,
            'component' => $component,
            'description' => $description,
            'target_type' => class_basename($model),
            'target_id' => $model->id,
            'target_label' => $targetLabel,
            'old_values' => $model->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'response_status' => 200,
        ]);
    }

    /**
     * Tính diff giữa old và new values - chỉ giữ các field thực sự thay đổi.
     */
    public static function diffValues(array $old, array $new): array
    {
        $changedOld = [];
        $changedNew = [];

        // Bỏ qua các field hệ thống không cần so sánh
        $skipFields = ['updated_at', 'created_at', 'remember_token', 'password'];

        foreach ($new as $key => $newVal) {
            if (in_array($key, $skipFields)) continue;

            $oldVal = $old[$key] ?? null;

            // So sánh giá trị (ép kiểu string để tránh lỗi type mismatch)
            if ((string) $oldVal !== (string) $newVal) {
                $changedOld[$key] = $oldVal;
                $changedNew[$key] = $newVal;
            }
        }

        return ['old' => $changedOld, 'new' => $changedNew];
    }
}
