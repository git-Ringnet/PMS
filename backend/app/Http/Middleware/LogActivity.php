<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogActivity
{
    /**
     * URL pattern → [module, component] mapping.
     */
    protected array $routeMap = [
        // Auth
        'login' => ['auth', 'LoginPage'],
        'logout' => ['auth', 'LoginPage'],

        // Reservation
        'rooms' => ['reservation', 'RoomMapPage'],
        'room-locks' => ['reservation', 'LockRoomPage'],

        // System Administration
        'users' => ['system', 'EmployeeTab'],
        'system-branches' => ['system', 'BranchManageTab'],
        'info-business' => ['system', 'CompanyInfoTab'],

        // Config - Hotel
        'hotel-settings' => ['config', 'HotelDefinition'],
        'hotel-services' => ['config', 'HotelDefinition'],
        'hotel-configs' => ['config', 'HotelDefinition'],
        'shifts' => ['config', 'HotelDefinition'],

        // Config - Room
        'room-classes' => ['config', 'RoomDefinition'],
        'room-class-groups' => ['config', 'RoomDefinition'],
        'room-forms' => ['config', 'RoomDefinition'],
        'standard-rates' => ['config', 'RoomDefinition'],

        // Config - Templates
        'templates' => ['config', 'DesignTemplateTab'],

        // Config - Company
        'companies' => ['config', 'CompanySettingsPage'],
        'markets' => ['config', 'SystemDefinition'],
        'customer-sources' => ['config', 'SystemDefinition'],
        'branches' => ['config', 'SystemDefinition'],
        'branches-total' => ['config', 'SystemDefinition'],
        'bookers' => ['config', 'SystemDefinition'],

        // Config - System Definition
        'payment-methods' => ['config', 'SystemDefinition'],
        'currencies' => ['config', 'SystemDefinition'],
        'units-of-measure' => ['config', 'SystemDefinition'],
        'registration-statuses' => ['config', 'SystemDefinition'],
        'room-rate-codes' => ['config', 'RateSetup'],
    ];

    /**
     * HTTP method → action mapping.
     */
    protected array $methodActionMap = [
        'POST' => 'create',
        'PUT' => 'update',
        'PATCH' => 'update',
        'DELETE' => 'delete',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ghi timestamp bắt đầu
        $request->attributes->set('_log_start_time', microtime(true));

        return $next($request);
    }

    /**
     * Terminable middleware - ghi log SAU khi response đã gửi cho client.
     * Không ảnh hưởng performance response.
     */
    public function terminate(Request $request, Response $response): void
    {
        try {
            // Skip GET requests (chỉ log thao tác thay đổi dữ liệu + auth)
            if ($request->isMethod('GET')) {
                return;
            }

            // Skip nếu URL là activity-logs (tránh vòng lặp)
            if (str_contains($request->path(), 'activity-logs')) {
                return;
            }

            // Skip login/logout - đã ghi log riêng trong AuthController
            $path = $request->path();
            if (preg_match('#^api/(login|logout)$#', $path)) {
                return;
            }

            // Skip các module đã có chức năng ghi log thủ công (F&B)
            if (preg_match('#^api/(fnb|fb-)#', $path)) {
                return;
            }

            $user = $request->user();
            $statusCode = $response->getStatusCode();

            // Detect module và component từ URL
            [$module, $component] = $this->detectModuleComponent($path);

            // Detect action từ HTTP method
            $action = $this->methodActionMap[$request->method()] ?? 'unknown';

            // Build mô tả tự động
            $description = $this->buildDescription($action, $component, $request, $response);

            // Nhận diện đối tượng tác động và dữ liệu thay đổi
            $targetType = null;
            $targetId = null;
            $targetLabel = null;
            $oldValues = null;
            $newValues = null;

            if ($statusCode < 400) {
                $modelChange = $request->attributes->get('_last_model_change');
                if ($modelChange) {
                    $targetType = $modelChange['target_type'];
                    $targetId = $modelChange['target_id'];
                    $targetLabel = $modelChange['target_label'];
                    $oldValues = $modelChange['old_values'];
                    $newValues = $modelChange['new_values'];

                    // Làm mô tả chi tiết hơn nếu có nhãn đối tượng
                    if ($targetLabel) {
                        $actionLabels = [
                            'create' => 'Thêm mới',
                            'update' => 'Cập nhật',
                            'delete' => 'Xóa',
                        ];
                        $actionLabel = $actionLabels[$action] ?? $action;
                        $description = "{$actionLabel} {$targetType} '{$targetLabel}' trên {$component}";
                    }
                }
            }

            // Tính thời gian xử lý
            $startTime = $request->attributes->get('_log_start_time');
            $durationMs = $startTime ? (int) ((microtime(true) - $startTime) * 1000) : null;

            ActivityLog::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? '',
                'employee_code' => $user?->employee_code,
                'action' => $action,
                'module' => $module,
                'component' => $component,
                'description' => $description,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'target_label' => $targetLabel,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_method' => $request->method(),
                'request_url' => $request->fullUrl(),
                'response_status' => $statusCode,
                'duration_ms' => $durationMs,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Không để lỗi logging làm crash ứng dụng
            \Log::warning('ActivityLog middleware error: ' . $e->getMessage());
        }
    }

    /**
     * Detect module và component từ URL path.
     */
    protected function detectModuleComponent(string $path): array
    {
        // Remove 'api/' prefix
        $cleanPath = preg_replace('#^api/#', '', $path);

        // Lấy segment đầu tiên của URL (resource name)
        $segments = explode('/', $cleanPath);
        $resource = $segments[0] ?? '';

        if (isset($this->routeMap[$resource])) {
            return $this->routeMap[$resource];
        }

        return ['other', $resource ?: 'Unknown'];
    }

    /**
     * Build mô tả hành động tự động.
     */
    protected function buildDescription(string $action, string $component, Request $request, Response $response): string
    {
        $statusCode = $response->getStatusCode();

        // Nếu response lỗi
        if ($statusCode >= 400) {
            return "Thao tác {$action} trên {$component} thất bại (HTTP {$statusCode})";
        }

        $actionLabels = [
            'create' => 'Tạo mới',
            'update' => 'Cập nhật',
            'delete' => 'Xóa',
            'upload' => 'Upload file',
            'bulk_action' => 'Thao tác hàng loạt',
        ];

        $label = $actionLabels[$action] ?? $action;

        return "{$label} dữ liệu trên {$component}";
    }
}
