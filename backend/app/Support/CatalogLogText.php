<?php

namespace App\Support;

use App\Services\ActivityLogService as Log;
use Illuminate\Support\Facades\DB;

/**
 * Nội dung lịch sử thao tác cho các danh mục (sheet GHI LOG dòng 64-66, 78-84, 90-93):
 * Công ty, Thị trường, Nguồn khách, Chi nhánh, Người đặt phòng, nhân viên HK, Lost & Found, Kho,
 * Nhóm sản phẩm, Sản phẩm. Khi sửa chỉ ghi các trường thay đổi.
 */
class CatalogLogText
{
    /** Màn hình mặc định khi frontend không gửi tab. */
    protected const SCREENS = [
        'Company' => ActivityScreen::COMPANY,
        'Market' => ActivityScreen::COMPANY,
        'CustomerSource' => ActivityScreen::COMPANY,
        'Branch' => ActivityScreen::COMPANY,
        'Booker' => ActivityScreen::COMPANY,
        'HkStaff' => ActivityScreen::HK_ASSIGN,
        'LostAndFoundItem' => ActivityScreen::LOST_FOUND,
        'Warehouse' => ActivityScreen::INVENTORY,
        'ProductCategory' => ActivityScreen::MENU,
        'Product' => ActivityScreen::MENU,
    ];

    public static function supports(?string $type): bool
    {
        return $type !== null && isset(self::SCREENS[$type]);
    }

    public static function screen(string $type): string
    {
        return self::SCREENS[$type];
    }

    /**
     * @param  string  $action  create | update | delete
     */
    public static function describe(string $type, string $action, ?array $old, ?array $new): ?string
    {
        $old ??= [];
        $new ??= [];

        return match ($type) {
            'Company' => self::company($action, $old, $new),
            'Market' => self::simpleCatalog('thị trường', $action, $old, $new),
            'CustomerSource' => self::simpleCatalog('nguồn khách', $action, $old, $new),
            'Branch' => self::simpleCatalog('chi nhánh', $action, $old, $new),
            'Booker' => self::booker($action, $old, $new),
            'HkStaff' => self::hkStaff($action, $old, $new),
            'LostAndFoundItem' => self::lostFound($action, $old, $new),
            'Warehouse' => self::warehouse($action, $old, $new),
            'ProductCategory' => self::productCategory($action, $old, $new),
            'Product' => self::product($action, $old, $new),
            default => null,
        };
    }

    // ---------------------------------------------------------------- Công ty (dòng 64, 65)

    protected const COMPANY_FIELDS = [
        'name' => 'Công Ty',
        'trading_name' => 'Tên Giao Dịch',
        'address' => 'Địa Chỉ',
        'phone' => 'Điện Thoại',
        'email' => 'Email',
        'tax_code' => 'Mã số thuế',
        'customer_source_id' => 'SourceCode',
        'market_id' => 'MarketSegment',
        'branch_id' => 'Branch',
        'booker_id' => 'Người đặt phòng',
        'rate_code' => 'Mã giá',
        'max_debt' => 'ReCredit Limit',
        'bank_account' => 'Tài khoản ngân hàng',
        'sync_acc' => 'Thanh toán công nợ',
        'is_active' => 'Is Use',
    ];

    protected static function company(string $action, array $old, array $new): ?string
    {
        $attrs = $new + $old;
        $id = $attrs['code'] ?? '';

        if ($action === 'create') {
            $parts = ["ID: {$id}"];
            foreach (self::COMPANY_FIELDS as $field => $label) {
                $parts[] = "{$label}: " . self::companyValue($field, $new[$field] ?? null);
            }

            return '* Thêm Công Ty : ' . implode(' , ', $parts);
        }
        if ($action === 'delete') {
            return "* Xóa Công Ty : ID: {$id} , Công Ty: " . ($old['name'] ?? '');
        }

        $changes = self::changes(self::COMPANY_FIELDS + ['code' => 'ID'], $old, $new, [self::class, 'companyValue'], ' ➜ ');

        return $changes ? "* Cập Nhật Công Ty:- ID: {$id} , " . implode(' , ', $changes) : null;
    }

    public static function companyValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return in_array($field, ['sync_acc', 'is_active'], true) ? 'Không' : '';
        }

        return match ($field) {
            'customer_source_id' => (string) DB::table('customer_sources')->where('id', $value)->value('name'),
            'market_id' => (string) DB::table('markets')->where('id', $value)->value('name'),
            'branch_id' => (string) DB::table('branches')->where('id', $value)->value('name'),
            'booker_id' => (string) DB::table('bookers')->where('id', $value)->value('name'),
            'sync_acc', 'is_active' => $value ? 'Có' : 'Không',
            'max_debt' => Log::money($value),
            default => is_scalar($value) ? trim((string) $value) : '',
        };
    }

    // ---------------------------------------------------------------- Thị trường / Nguồn khách / Chi nhánh (dòng 66)

    protected static function simpleCatalog(string $noun, string $action, array $old, array $new): ?string
    {
        $attrs = $new + $old;
        $id = $attrs['code'] ?? ($attrs['id'] ?? '');

        if ($action === 'create') {
            return "* Thêm mới {$noun}: ID: {$id}, - Tên: " . ($new['name'] ?? '');
        }
        if ($action === 'delete') {
            return "* Xóa {$noun}: - ID: {$id}, - Tên: " . ($old['name'] ?? '');
        }

        $changes = self::changes(['code' => 'ID', 'name' => 'Tên'], $old, $new, null, ' ➜ ');

        return $changes ? "* Cập nhật {$noun}: " . ($old['code'] ?? $id) . "\n" . implode("\n", $changes) : null;
    }

    // ---------------------------------------------------------------- Người đặt phòng (dòng 66)

    protected const BOOKER_FIELDS = ['name' => 'Tên', 'email' => 'Email', 'phone' => 'Số điện thoại', 'address' => 'Địa chỉ', 'notes' => 'Ghi chú'];

    protected static function booker(string $action, array $old, array $new): ?string
    {
        $attrs = $new + $old;
        $full = function (array $values) use ($attrs): string {
            $parts = ['- ID: ' . ($attrs['id'] ?? '') . '.'];
            foreach (self::BOOKER_FIELDS as $field => $label) {
                $parts[] = "- {$label}: " . trim((string) ($values[$field] ?? ''));
            }

            return implode(', ', $parts);
        };

        if ($action === 'create') {
            return '* Thêm mới Người đặt phòng: ' . $full($new);
        }
        if ($action === 'delete') {
            return '* Xóa Người đặt phòng: ' . $full($old);
        }

        $changes = self::changes(self::BOOKER_FIELDS, $old, $new, null, '➜ ');

        return $changes ? '* Cập nhật người đặt phòng: ID: ' . ($attrs['id'] ?? '') . "\n" . implode("\n", $changes) : null;
    }

    // ---------------------------------------------------------------- Nhân viên buồng phòng (dòng 78-80)

    protected static function hkStaff(string $action, array $old, array $new): ?string
    {
        $name = $new['name'] ?? $old['name'] ?? '';
        if ($action === 'create') {
            return "* Thêm nhân viên mới:  - Tên: {$name}";
        }
        if ($action === 'delete') {
            return "* Xóa nhân viên: - Tên: {$name}";
        }

        $lines = [];
        if (array_key_exists('is_hidden', $new) && (bool) $new['is_hidden'] !== (bool) ($old['is_hidden'] ?? false)) {
            $lines[] = ($new['is_hidden'] ? '* Ẩn nhân viên: - Tên: ' : '* Bỏ ẩn nhân viên: - Tên: ') . $name;
        }
        if (array_key_exists('name', $new) && ($old['name'] ?? null) !== null && $old['name'] !== $new['name']) {
            $lines[] = "* Cập nhật nhân viên: - Tên: {$old['name']} -> {$new['name']}";
        }

        return $lines ? implode("\n", $lines) : null;
    }

    // ---------------------------------------------------------------- Lost & Found (dòng 81-83)

    protected const LOST_FOUND_FIELDS = [
        'status' => 'Lost/Found',
        'date_reported' => 'Ngày báo cáo',
        'guest_info' => 'Tên khách',
        'item_found' => 'Mặt hàng',
        'date_found' => 'Ngày tìm thấy',
        'who_found' => 'Người tìm thấy',
        'received' => 'Người quản lý',
        'where_found' => 'Địa điểm tìm thấy',
        'storage_location' => 'Kho lưu trữ',
        'remarks' => 'Ghi chú',
        'date_handling' => 'Ngày nhận',
        'method_handling' => 'Phương thức xử lý',
        'delieved_handling' => 'Người giao',
        'received_handling' => 'Người nhận',
    ];

    /**
     * Ô "Thông tin khách" nhập chung "Tên, số phòng, điện thoại...": tách số phòng có thật trong danh mục phòng.
     *
     * @return array{0:string,1:?string} [tên khách còn lại, số phòng]
     */
    public static function splitGuestRoom(?string $guestInfo): array
    {
        $text = trim((string) $guestInfo);
        if ($text === '') {
            return ['', null];
        }
        $room = null;
        $rest = [];
        foreach (preg_split('/\s*[,;|]\s*|\s+-\s+/u', $text) as $part) {
            $token = trim(preg_replace('/^(?:P\.?|phòng)\s*/iu', '', trim($part)));
            if ($room === null && $token !== '' && DB::table('rooms')->where('room_number', $token)->exists()) {
                $room = $token;
                continue;
            }
            if (trim($part) !== '') {
                $rest[] = trim($part);
            }
        }

        return [implode(', ', $rest), $room];
    }

    protected static function lostFound(string $action, array $old, array $new): ?string
    {
        $attrs = $new + $old;
        $id = $attrs['log_no'] ?? ($attrs['id'] ?? '');
        $value = fn (string $field, $v) => str_starts_with($field, 'date_') ? Log::d($v) : trim((string) ($v ?? ''));

        if ($action === 'create') {
            $parts = ["- ID: {$id}"];
            foreach (self::LOST_FOUND_FIELDS as $field => $label) {
                if ($field === 'guest_info') {
                    // Dòng 81: "- Tên khách: Lê Hồng Khánh Nguyên, - Phòng 202"
                    [$guest, $room] = self::splitGuestRoom($new['guest_info'] ?? null);
                    $parts[] = "- Tên khách: {$guest}";
                    $parts[] = '- Phòng ' . ($room ?? '');
                    continue;
                }
                $parts[] = "- {$label}: " . $value($field, $new[$field] ?? null);
            }

            return '* Thêm mới Lost & Found: ' . implode(', ', $parts);
        }
        if ($action === 'delete') {
            return "* Xóa Lost & Found: - ID: {$id}";
        }

        $changes = [];
        foreach (self::LOST_FOUND_FIELDS as $field => $label) {
            if (!array_key_exists($field, $new)) {
                continue;
            }
            $o = $value($field, $old[$field] ?? null);
            $n = $value($field, $new[$field]);
            if ($o !== $n) {
                $changes[] = "- {$label}: {$o} -> {$n}";
            }
        }

        return $changes ? "* Cập nhật Lost & Found: - ID: {$id}, " . implode(', ', $changes) : null;
    }

    // ---------------------------------------------------------------- Kho (dòng 84)

    public static function outletName(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $codes = is_array($value) ? $value : (str_starts_with((string) $value, '[') ? (json_decode((string) $value, true) ?: []) : [(string) $value]);
        $names = DB::table('housekeeping_outlets')->whereIn('code', $codes)->pluck('name', 'code');

        return collect($codes)->map(fn ($code) => isset($names[$code]) ? "{$code} - {$names[$code]}" : (string) $code)->implode(', ');
    }

    protected static function warehouse(string $action, array $old, array $new): ?string
    {
        $name = $new['name'] ?? $old['name'] ?? '';
        if ($action === 'create') {
            return "* Thêm mới kho: - Tên kho: {$name}, - Outlet: " . self::outletName($new['outlet_id'] ?? null);
        }
        if ($action === 'delete') {
            return "* Xóa kho: - Tên kho: {$name}";
        }

        $changes = self::changes(['name' => 'Tên kho', 'outlet_id' => 'Outlet', 'is_active' => 'Đang sử dụng'], $old, $new, function ($field, $v) {
            return match ($field) {
                'outlet_id' => self::outletName($v),
                'is_active' => $v ? 'Có' : 'Không',
                default => trim((string) ($v ?? '')),
            };
        });

        return $changes ? "* Cập nhật kho: {$name}, " . implode(', ', $changes) : null;
    }

    // ---------------------------------------------------------------- Nhóm sản phẩm / Sản phẩm (dòng 90-93)

    protected static function productCategory(string $action, array $old, array $new): ?string
    {
        $attrs = $new + $old;
        $outlet = self::outletLabel($attrs['outlet'] ?? null);
        if ($action === 'create') {
            return "* Thêm mới nhóm sản phẩm: - Outlet: {$outlet} , - Tên nhóm: " . ($new['name'] ?? '');
        }
        if ($action === 'delete') {
            return "* Xóa nhóm sản phẩm: - Outlet: {$outlet} , - Tên nhóm: " . ($old['name'] ?? '');
        }

        $changes = self::changes(['outlet' => 'Outlet', 'name' => 'Tên nhóm'], $old, $new, fn ($f, $v) => $f === 'outlet' ? self::outletLabel($v) : trim((string) ($v ?? '')));

        return $changes ? "* Cập nhật nhóm sản phẩm: - Outlet: {$outlet}, " . implode(', ', $changes) : null;
    }

    protected static function outletLabel(mixed $code): string
    {
        if ($code === null || $code === '') {
            return '';
        }

        return (string) (DB::table('housekeeping_outlets')->where('code', $code)->value('name') ?? $code);
    }

    protected const PRODUCT_FIELDS = [
        'name' => 'Tên SP',
        'inventory_id' => 'ĐVT',
        'track_stock' => 'Quản lý tồn kho',
        'price' => 'Giá bán',
        'note' => 'Mô tả',
        'is_active' => 'Đang sử dung',
        'is_in_stock' => 'Mở bán',
        'flexible_price' => 'Giá bán linh động',
    ];

    public static function productValue(string $field, mixed $value): string
    {
        return match ($field) {
            'inventory_id' => $value ? (string) DB::table('inventories')->where('id', $value)->value('name') : '',
            'track_stock' => $value ? 'Có theo dõi' : 'Không theo dõi',
            'price' => Log::money($value),
            'is_active', 'is_in_stock', 'flexible_price' => $value ? 'Có' : 'Không',
            default => trim((string) ($value ?? '')),
        };
    }

    protected static function product(string $action, array $old, array $new): ?string
    {
        $attrs = $new + $old;
        $categoryId = $attrs['product_category_id'] ?? null;
        $category = $categoryId ? DB::table('product_categories')->where('id', $categoryId)->first(['name', 'outlet']) : null;
        $head = '- Outlet: ' . self::outletLabel($category?->outlet) . ' , - Nhóm SP: ' . ($category?->name ?? '') . ' , - Mã SP: ' . ($attrs['id'] ?? '');

        if ($action === 'create') {
            $parts = [];
            foreach (self::PRODUCT_FIELDS as $field => $label) {
                $parts[] = "- {$label}: " . self::productValue($field, $new[$field] ?? null);
            }

            return "* Thêm mới sản phẩm: {$head} , " . implode(', ', $parts);
        }
        if ($action === 'delete') {
            return "* Xóa sản phẩm: {$head} , - Tên SP: " . ($old['name'] ?? '');
        }

        $changes = self::changes(self::PRODUCT_FIELDS + ['product_category_id' => 'Nhóm SP'], $old, $new, function ($field, $v) {
            return $field === 'product_category_id'
                ? (string) DB::table('product_categories')->where('id', $v)->value('name')
                : self::productValue($field, $v);
        });

        return $changes ? "* Cập nhật sản phẩm: {$head}, - Tên SP: " . ($attrs['name'] ?? '') . ' , ' . implode(', ', $changes) : null;
    }

    // ---------------------------------------------------------------- tiện ích

    /** "- Nhãn: cũ -> mới" cho các trường có trong $new và thực sự đổi giá trị. */
    protected static function changes(array $fields, array $old, array $new, ?callable $format = null, string $arrow = ' -> '): array
    {
        $format ??= fn ($field, $v) => is_scalar($v) ? trim((string) $v) : '';
        $lines = [];
        foreach ($fields as $field => $label) {
            if (!array_key_exists($field, $new)) {
                continue;
            }
            $o = $format($field, $old[$field] ?? null);
            $n = $format($field, $new[$field]);
            if ($o !== $n) {
                $lines[] = "- {$label}: {$o}{$arrow}{$n}";
            }
        }

        return $lines;
    }
}
