<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Protect catalogue identities and historical rows, including cancelled/soft-deleted data. */
class ConfigurationReferenceGuard
{
    public function assertUnused(Model $model): void
    {
        $references = $this->references($model);
        if ($references) {
            $labels = [
                'booking_rooms' => 'chi tiết phòng của booking', 'bookings' => 'booking',
                'booking_room_services' => 'dịch vụ booking', 'service_bills' => 'hóa đơn dịch vụ',
                'service_bill_details' => 'chi tiết hóa đơn dịch vụ', 'room_night_bills' => 'tiền phòng đã ghi nhận',
                'rooms' => 'danh sách phòng', 'room_locks' => 'lịch sử khóa phòng',
                'standard_rates' => 'giá phòng chuẩn', 'payments' => 'thanh toán',
                'payment_debt_settlements' => 'thanh toán công nợ', 'hotel_services' => 'dịch vụ khách sạn',
                'housekeeping_outlets' => 'outlet buồng phòng', 'outlets' => 'điểm bán hàng',
                'housekeeping_service_bills' => 'hóa đơn buồng phòng',
                'housekeeping_service_bill_details' => 'chi tiết hóa đơn buồng phòng',
                'product_categories' => 'nhóm menu buồng phòng', 'products' => 'sản phẩm buồng phòng',
                'room_rate_codes' => 'mã giá phòng', 'sales_invoices' => 'hóa đơn bán hàng',
                'bank_accounts' => 'tài khoản ngân hàng', 'hotel_settings' => 'thông tin khách sạn',
                'hk_assignments' => 'phân công buồng phòng', 'late_checkins' => 'nhận phòng sớm/trả phòng trễ',
                'noshow_logs' => 'lịch sử no-show', 'night_audit_runs' => 'kiểm toán đêm',
                'system_date_rolls' => 'chuyển ngày hệ thống',
                'fb_products' => 'sản phẩm nhà hàng', 'fb_product_outlets' => 'menu điểm bán',
                'fb_orders' => 'đơn hàng nhà hàng', 'fb_parties' => 'đặt tiệc',
                'fb_printers' => 'máy in điểm bán', 'fb_promotions' => 'khuyến mãi', 'companies' => 'công ty',
            ];
            $used = array_unique(array_map(fn ($reference) => $labels[explode('.', $reference)[0]] ?? 'dữ liệu liên quan', $references));
            throw ValidationException::withMessages([
                'references' => 'Không thể thực hiện thao tác vì cấu hình đã được sử dụng trong: '.implode(', ', $used).'.',
            ]);
        }
    }

    public function assertIdentityUnchanged(Model $model, array $data, array $fields, bool $always = false): void
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $data) && (string) $data[$field] !== (string) $model->getRawOriginal($field)) {
                if ($always || $this->references($model)) {
                    throw ValidationException::withMessages([$field => 'Không được thay đổi mã cấu hình đã tạo hoặc đã phát sinh dữ liệu.']);
                }
            }
        }
    }

    public function references(Model $model): array
    {
        $id = $model->getKey();
        $code = $model->getRawOriginal('code');
        $number = $model->getRawOriginal('room_number');
        $name = $model->getRawOriginal('name');
        $ma = $model->getRawOriginal('Ma');
        $references = match ($model->getTable()) {
            'hotel_services' => [
                ['service_bills', 'ServiceId', [$code]],
                ['service_bill_details', 'ServiceId', [$code]],
                ['booking_room_services', 'service_code', [$code]],
                ['housekeeping_outlets', 'service_code', [$code]],
                ['outlets', 'service_code', [$code]],
            ],
            'rooms' => [
                ['booking_rooms', 'room_number', [$number]],
                ['room_locks', 'room_number', [$number]],
                ['room_night_bills', 'room', [$number]],
                ['housekeeping_service_bills', 'RoomNo', [$number]],
                ['rooms', 'connecting_room', [$number]],
                ['rooms', 'linked_room', [$number]],
            ],
            'room_classes' => [
                ['rooms', 'room_class_id', [$id]],
                ['standard_rates', 'room_class_id', [$id]],
                ['booking_rooms', 'room_class_id', [$id]],
                ['booking_rooms', 'upgrade_room_class_id', [$id]],
                ['room_night_bills', 'room_type_id', [$id]],
            ],
            'room_forms' => [
                ['rooms', 'room_form_id', [$id]],
                ['standard_rates', 'room_form_id', [$id]],
                ['booking_rooms', 'RoomKind', [$id]],
                ['room_night_bills', 'room_kind_id', [$id]],
            ],
            'payment_methods' => [
                ['payments', 'payment_method_id', [$code]],
                ['payment_debt_settlements', 'payment_method_id', [$code]],
                ['bookings', 'payment_method_id', [$id]],
            ],
            'units_of_measure' => [
                ['hotel_services', 'unit', [$code, $name, $model->getRawOriginal('symbol')]],
                ['booking_room_services', 'unit', [$code, $name, $model->getRawOriginal('symbol')]],
                ['fb_products', 'unit_id', [$id]],
            ],
            'registration_statuses' => [
                // Since the September cutover, the live column contains the business code.
                ['bookings', 'registration_status_id', [$model->getRawOriginal('booking_status_id')]],
                ['bookings', 'registration_status_pk_before_codes', [$id]],
            ],
            'housekeeping_outlets' => [
                ['product_categories', 'outlet', [$code, $name]],
                ['service_bills', 'Outlet', [$code, $name]],
                ['housekeeping_service_bills', 'Outlet', [$code, $name]],
            ],
            'products' => [
                ['housekeeping_service_bill_details', 'MaProduct', [$id]],
            ],
            'product_categories' => [
                ['products', 'product_category_id', [$id]],
                ['housekeeping_service_bill_details', 'ProductGroupId', [$id]],
            ],
            'outlets' => [
                ['fb_product_outlets', 'outlet_id', [$id]],
                ['fb_orders', 'outlet_code', [$code]],
                ['fb_parties', 'outlet', [$code]],
                ['service_bills', 'Outlet', [$code]],
                ['payments', 'outlet', [$code]],
                ['fb_printers', 'outlet_id', [$id]],
                ['fb_promotions', 'outlet_id', [$id]],
            ],
            'room_rate_codes' => [
                ['booking_rooms', 'rate_code', [$ma]],
                ['room_night_bills', 'rate_code', [$ma]],
                ['companies', 'rate_code', [$ma]],
            ],
            'currencies' => [
                ['payments', 'currency', [$code]],
                ['payment_debt_settlements', 'currency', [$code]],
                ['service_bills', 'Currency', [$code]],
                ['service_bills', 'Currency0', [$code]],
                ['service_bills', 'Currency1', [$code]],
                ['service_bills', 'Currency2', [$code]],
                ['service_bill_details', 'Currency', [$code]],
                ['housekeeping_service_bills', 'Currency', [$code]],
                ['sales_invoices', 'currency', [$code]],
                ['room_rate_codes', 'Currency', [$code]],
                ['products', 'currency', [$code]],
                ['bank_accounts', 'currency_code', [$code]],
                ['hotel_settings', 'currency', [$code]],
            ],
            // Shift columns store either the shift name or its id, so both are treated as in use.
            'shifts' => [
                ['hk_assignments', 'shift_id', [$id]],
                ['payments', 'shift', [$name, (string) $id]],
                ['late_checkins', 'shift', [$name, (string) $id]],
                ['noshow_logs', 'shift', [$name, (string) $id]],
                ['night_audit_runs', 'shift', [$name, (string) $id]],
                ['system_date_rolls', 'shift', [$name, (string) $id]],
                ['housekeeping_service_bills', 'BillShift', [$id]],
            ],
            default => [],
        };

        $connection = $model->getConnection();
        $schema = $connection->getSchemaBuilder();
        $used = [];
        foreach ($references as [$table, $column, $values]) {
            $values = array_values(array_filter($values, fn ($value) => $value !== null && $value !== ''));
            // Some references were added in later migrations; inspect the same branch connection.
            if ($values && $schema->hasColumn($table, $column)
                && $connection->table($table)->whereIn($column, $values)->exists()) {
                $used[] = $table.'.'.$column;
            }
        }

        // The original class/form is a composite snapshot, not a foreign key.
        if (in_array($model->getTable(), ['room_classes', 'room_forms'], true)
            && $schema->hasColumn('booking_rooms', 'original_room_class_id')) {
            $query = $connection->table('booking_rooms');
            $usedOriginal = $model->getTable() === 'room_classes'
                ? $query->where(fn ($q) => $q->where('original_room_class_id', (string) $id)->orWhere('original_room_class_id', 'like', $id.'-%'))->exists()
                : $query->where('original_room_class_id', 'like', '%-'.$id)->exists();
            if ($usedOriginal) {
                $used[] = 'booking_rooms.original_room_class_id';
            }
        }

        return array_values(array_unique($used));
    }
}
