<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Định dạng nội dung Lịch sử thao tác: nhãn tiếng Việt cho từng trường và giá trị dễ đọc
 * (đổi id danh mục sang tên, ngày, tiền, có/không). Dùng chung cho lúc ghi log và lúc hiển thị.
 */
class ActivityLogFormatter
{
    public const FIELD_LABELS = [
        'status' => 'Trạng thái',
        'registration_status_id' => 'Trạng thái ĐK',
        'arrival_date' => 'Ngày đến',
        'departure_date' => 'Ngày đi',
        'arrival_time' => 'Giờ đến',
        'departure_time' => 'Giờ đi',
        'num_of_days' => 'Số đêm',
        'booking_name' => 'Tên khách/đoàn',
        'booking_id' => 'Mã đăng ký',
        'booking_room_id' => 'Mã phòng thuê',
        'contact_name' => 'Người liên hệ',
        'contact_phone' => 'SĐT liên hệ',
        'contact_email' => 'Email liên hệ',
        'total_amount' => 'Tổng tiền',
        'deposit_amount' => 'Tiền cọc',
        'payment_value' => 'Giá trị thanh toán',
        'note' => 'Ghi chú',
        'special_requests' => 'Yêu cầu đặc biệt',
        'company_id' => 'Công ty',
        'market_id' => 'Thị trường',
        'customer_source_id' => 'Nguồn khách',
        'booker_id' => 'Người đặt phòng',
        'sales_person_id' => 'Người bán',
        'user_id' => 'Người dùng',
        'is_git' => 'Khách đoàn (GIT)',
        'breakfast_included' => 'Ăn sáng',
        'breakfast' => 'Ăn sáng',
        'is_day_use' => 'Ở trong ngày',
        'color' => 'Màu hiển thị',
        'room_number' => 'Số phòng',
        'price' => 'Đơn giá',
        'rate' => 'Giá phòng',
        'rate_code' => 'Mã giá phòng',
        'base_price' => 'Giá gốc',
        'adults' => 'Người lớn',
        'children' => 'Trẻ em',
        'children_qty' => 'Trẻ em',
        'extra_bed_qty' => 'Số lượng giường phụ',
        'extra_bed_rate' => 'Giá giường phụ',
        'room_class_id' => 'Loại phòng',
        'original_room_class_id' => 'Loại phòng gốc',
        'room_form_id' => 'Dạng phòng',
        'room_class_group_id' => 'Nhóm hạng phòng',
        'config_key' => 'Tên cấu hình',
        'config_value' => 'Giá trị',
        'value' => 'Giá trị',
        'is_visible' => 'Hiển thị',
        'description' => 'Mô tả',
        'amount' => 'Số tiền',
        'payment_method_id' => 'Hình thức thanh toán',
        'name' => 'Tên',
        'trading_name' => 'Tên giao dịch',
        'phone' => 'SĐT',
        'email' => 'Email',
        'code' => 'Mã',
        'quantity' => 'Số lượng',
        'unit_price' => 'Đơn giá',
        'subtotal' => 'Thành tiền',
        'discount' => 'Giảm giá',
        'discount_type' => 'Loại giảm giá',
        'discount_value' => 'Mức giảm giá',
        'tax_code' => 'Mã số thuế',
        'address' => 'Địa chỉ',
        'sync_acc' => 'Đồng bộ ACC',
        'max_debt' => 'Công nợ tối đa',
        'bank_account' => 'Tài khoản ngân hàng',
        'gender' => 'Giới tính',
        'birthday' => 'Ngày sinh',
        'dob' => 'Ngày sinh',
        'nationality' => 'Quốc tịch',
        'nationality_code' => 'Quốc tịch',
        'passport_no' => 'Số hộ chiếu',
        'passport_number' => 'Số hộ chiếu',
        'id_card' => 'Số CCCD',
        'id_number' => 'Số giấy tờ',
        'id_type' => 'Loại giấy tờ',
        'document_number' => 'Số giấy tờ',
        'document_type' => 'Loại giấy tờ',
        'full_name' => 'Họ và tên',
        'title' => 'Danh xưng',
        'service_name' => 'Tên dịch vụ',
        'service_code' => 'Mã dịch vụ',
        'service_date' => 'Ngày dịch vụ',
        'folio_id' => 'Mã Folio',
        'folio_no' => 'Số Folio',
        'table_number' => 'Số bàn',
        'capacity' => 'Sức chứa',
        'location_id' => 'Khu vực',
        'warehouse_id' => 'Kho',
        'warehouse_name' => 'Tên kho',
        'unit_id' => 'Đơn vị tính',
        'unit' => 'Đơn vị tính',
        'category_id' => 'Danh mục',
        'item_code' => 'Mã hàng',
        'stock_quantity' => 'Số lượng tồn',
        'min_quantity' => 'Tồn tối thiểu',
        'max_quantity' => 'Tồn tối đa',
        'party_name' => 'Tên tiệc',
        'party_date' => 'Ngày tổ chức',
        'guest_count' => 'Số khách',
        'promo_code' => 'Mã khuyến mãi',
        'discount_percent' => 'Phần trăm giảm',
        'start_date' => 'Ngày bắt đầu',
        'end_date' => 'Ngày kết thúc',
        'from_date' => 'Từ ngày',
        'to_date' => 'Đến ngày',
        'is_active' => 'Kích hoạt',
        'role_id' => 'Vai trò',
        'department_id' => 'Phòng ban',
        'branch_id' => 'Chi nhánh',
        'username' => 'Tên đăng nhập',
        'employee_code' => 'Mã nhân viên',
        'standard_rate' => 'Giá tiêu chuẩn',
        'max_adults' => 'Người lớn tối đa',
        'max_children' => 'Trẻ em tối đa',
        'lock_type' => 'Loại khóa',
        'reason' => 'Lý do',
        'is_do_not_move' => 'Không di chuyển',
        'no_post' => 'Chặn ghi nợ (No Post)',
        'is_primary' => 'Khách chính',
        'room_status_code' => 'Tình trạng buồng',
        // Checklist TC_HT_06 dòng 33/36: các trường của màn Hệ thống (phân quyền, cơ cấu tổ chức, nhân viên)
        'system_branch_id' => 'Chi nhánh',
        'primary_branch_id' => 'Chi nhánh chính',
        'role_id' => 'Vai trò',
        'permission_id' => 'Quyền',
        'organization_department_id' => 'Bộ phận',
        'position_id' => 'Vị trí công việc',
        'application_code' => 'Ứng dụng',
        'allow_historical_date_actions' => 'Cho thao tác ngày cũ',
        'must_change_password' => 'Bắt đổi mật khẩu',
        'level' => 'Cấp',
        'department_scope' => 'Phạm vi bộ phận',
        'screen_name' => 'Tên màn hình',
        'screen_code' => 'Mã màn hình',
        'path' => 'Đường dẫn',
        'module' => 'Phân hệ',
        'image' => 'Ảnh',
    ];

    /** Trường id danh mục => [bảng, cột tên hiển thị, connection hệ thống?] */
    private const LOOKUPS = [
        'market_id' => ['markets', 'name', false],
        'customer_source_id' => ['customer_sources', 'name', false],
        'company_id' => ['companies', 'name', false],
        'booker_id' => ['bookers', 'name', false],
        'room_class_id' => ['room_classes', 'name', false],
        'original_room_class_id' => ['room_classes', 'name', false],
        'room_form_id' => ['room_forms', 'name', false],
        'registration_status_id' => ['registration_statuses', 'name', false],
        'payment_method_id' => ['payment_methods', 'name', false],
        'sales_person_id' => ['users', 'name', true],
        'user_id' => ['users', 'name', true],
        // Checklist TC_HT_06 dòng 33/36: id của màn Hệ thống hiện theo tên thay vì số
        'system_branch_id' => ['system_branches', 'name', true],
        'primary_branch_id' => ['system_branches', 'name', true],
        'role_id' => ['roles', 'name', true],
        'permission_id' => ['permissions', 'name', true],
        'organization_department_id' => ['organization_departments', 'name', true],
        'position_id' => ['positions', 'name', true],
    ];

    private const MONEY_FIELDS = [
        'total_amount', 'deposit_amount', 'payment_value', 'price', 'rate', 'base_price', 'amount',
        'extra_bed_rate', 'subtotal', 'unit_price', 'max_debt', 'standard_rate',
    ];

    private const BOOLEAN_FIELDS = [
        'is_git', 'breakfast', 'breakfast_included', 'is_day_use', 'is_visible', 'is_active', 'sync_acc',
        'is_do_not_move', 'no_post', 'is_primary', 'allow_historical_date_actions', 'must_change_password',
    ];

    /** Trường kỹ thuật không hiện trong bảng chi tiết thay đổi (dòng 36). */
    private const TECHNICAL_FIELDS = [
        'id', 'created_at', 'updated_at', 'deleted_at', 'remember_token', 'password', 'sort_order', 'order_index',
        'created_by', 'updated_by', 'created_by_user_id', 'updated_by_user_id', 'batch', 'log_batch', 'log_version',
        'guard_name', 'email_verified_at',
    ];

    private static array $nameCache = [];

    public static function label(string $key): string
    {
        return self::FIELD_LABELS[$key] ?? $key;
    }

    /** Giá trị dễ đọc: id danh mục -> tên, ngày dd/mm/yyyy, tiền có dấu phẩy, có/không. */
    public static function value(string $key, mixed $val): string
    {
        if ($val === null || $val === '') {
            return '(trống)';
        }

        if (isset(self::LOOKUPS[$key]) && is_scalar($val) && !is_bool($val)) {
            $name = self::lookupName($key, $val);
            return $name !== null ? $name : '#' . $val;
        }

        if (is_bool($val) || (in_array($key, self::BOOLEAN_FIELDS, true) && in_array((string) $val, ['0', '1'], true))) {
            return filter_var($val, FILTER_VALIDATE_BOOLEAN) ? 'Có' : 'Không';
        }

        if ((str_contains($key, 'date') || $key === 'dob' || $key === 'birthday') && is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}/', $val)) {
            try {
                return Carbon::parse($val)->format('d/m/Y');
            } catch (\Throwable $e) {
            }
        }

        if (in_array($key, self::MONEY_FIELDS, true) && is_numeric($val)) {
            return number_format((float) $val, 0, '.', ',');
        }

        if (is_array($val)) {
            foreach (['name', 'code', 'title'] as $field) {
                if (isset($val[$field]) && is_scalar($val[$field])) {
                    return (string) $val[$field];
                }
            }
            return isset($val['id']) && is_scalar($val['id']) ? '#' . $val['id'] : '(dữ liệu)';
        }

        return is_scalar($val) ? (string) $val : '(dữ liệu)';
    }

    public static function isLookupField(string $key): bool
    {
        return isset(self::LOOKUPS[$key]);
    }

    /** Tên của bản ghi danh mục, null nếu không tìm thấy. */
    public static function lookupName(string $key, mixed $id): ?string
    {
        [$table, $column, $system] = self::LOOKUPS[$key];
        $connection = $system ? config('database_domains.system_connection', 'mysql_system') : DB::getDefaultConnection();
        $cacheKey = "{$connection}.{$table}.{$id}";

        if (!array_key_exists($cacheKey, self::$nameCache)) {
            try {
                $name = DB::connection($connection)->table($table)->where('id', $id)->value($column);
                self::$nameCache[$cacheKey] = $name !== null ? (string) $name : null;
            } catch (\Throwable $e) {
                self::$nameCache[$cacheKey] = null;
            }
        }

        return self::$nameCache[$cacheKey];
    }

    /** Ngày dd/mm/yyyy, rỗng nếu không có. */
    public static function date(mixed $val): string
    {
        if (empty($val) || !is_scalar($val)) {
            return '';
        }
        try {
            return Carbon::parse($val)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $val;
        }
    }

    public static function money(mixed $val): string
    {
        return is_numeric($val) ? number_format((float) $val, 0, '.', ',') : '';
    }

    /** Các dòng "• Nhãn: cũ ➜ mới" cho những trường có thay đổi. */
    public static function diffLines(?array $old, ?array $new, array $skip = []): array
    {
        $skip = array_merge(['updated_at', 'created_at', 'id', 'deleted_at', 'remember_token', 'password'], $skip);
        $lines = [];
        foreach ($new ?? [] as $key => $newVal) {
            if (in_array($key, $skip, true)) {
                continue;
            }
            $oldStr = self::value($key, $old[$key] ?? null);
            $newStr = self::value($key, $newVal);
            if ($oldStr !== $newStr) {
                $lines[] = '• ' . self::label($key) . ": {$oldStr} ➜ {$newStr}";
            }
        }

        return $lines;
    }

    /** Các dòng "• Nhãn: giá trị" cho bản ghi mới tạo. */
    public static function creationLines(?array $new, array $skip = []): array
    {
        $skip = array_merge([
            'updated_at', 'created_at', 'id', 'deleted_at', 'remember_token', 'password', 'branch_id',
            'created_by_user_id', 'updated_by_user_id', 'created_by', 'updated_by',
        ], $skip);
        $lines = [];
        foreach ($new ?? [] as $key => $val) {
            if (in_array($key, $skip, true) || $val === null || $val === '') {
                continue;
            }
            $lines[] = '• ' . self::label($key) . ': ' . self::value($key, $val);
        }

        return $lines;
    }

    /**
     * Checklist TC_HT_06 dòng 36: bảng chi tiết thay đổi dễ đọc cho popup Lịch sử thao tác.
     * Mỗi dòng {field, label, before, after}; chỉ các trường thực sự thay đổi (thêm mới: chỉ có "sau",
     * xóa: chỉ có "trước"), bỏ trường kỹ thuật, giá trị đã đổi sang tên / ngày / tiền / có-không.
     *
     * @param  array<string,string>  $labels  nhãn ưu tiên (bảng nhãn của màn hình hiển thị)
     * @return array<int, array{field:string,label:string,before:string,after:string}>
     */
    public static function changeRows(?array $old, ?array $new, array $labels = []): array
    {
        $old = is_array($old) ? $old : [];
        $new = is_array($new) ? $new : [];
        $rows = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $key) {
            $key = (string) $key;
            if (in_array($key, self::TECHNICAL_FIELDS, true) || str_starts_with($key, '_')) {
                continue;
            }
            $hasOld = array_key_exists($key, $old);
            $hasNew = array_key_exists($key, $new);
            $before = $hasOld ? self::value($key, $old[$key]) : '';
            $after = $hasNew ? self::value($key, $new[$key]) : '';
            if ($hasOld && $hasNew && $before === $after) {
                continue;
            }
            if (($hasOld && !$hasNew && $before === '(trống)') || (!$hasOld && $hasNew && $after === '(trống)')) {
                continue;
            }
            $rows[] = [
                'field' => $key,
                'label' => $labels[$key] ?? (self::FIELD_LABELS[$key] ?? self::humanize($key)),
                'before' => $before,
                'after' => $after,
            ];
        }

        return $rows;
    }

    /** Nhãn tạm cho trường chưa có trong bảng nhãn: bỏ hậu tố _id, đổi gạch dưới thành khoảng trắng. */
    private static function humanize(string $key): string
    {
        return ucfirst(str_replace('_', ' ', preg_replace('/_id$/', '', $key)));
    }

    public static function flushCache(): void
    {
        self::$nameCache = [];
    }
}
