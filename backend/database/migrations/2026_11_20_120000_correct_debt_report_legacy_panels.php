<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $now = now();
        DB::table('report_definitions')->where('code', 'COMPANY_DEBT')->update([
            'parameter_ui_schema' => json_encode($this->companyUi(), JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);
        DB::table('report_definitions')->where('code', 'PAID_COMPANY_DEBTS')->update([
            'parameter_ui_schema' => json_encode($this->paidUi(), JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $now = now();
        DB::table('report_definitions')->where('code', 'COMPANY_DEBT')->update([
            'parameter_ui_schema' => json_encode($this->previousCompanyUi(), JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);
        DB::table('report_definitions')->where('code', 'PAID_COMPANY_DEBTS')->update([
            'parameter_ui_schema' => json_encode($this->previousPaidUi(), JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);
    }

    private function companyUi(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_company_id', 'label' => 'Công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_debt_status', 'label' => 'Xem theo', 'control' => 'select', 'default' => 'all', 'required' => true, 'options' => [['value' => 'all', 'label' => 'Tất cả'], ['value' => 'unpaid', 'label' => 'Chưa thanh toán'], ['value' => 'paid', 'label' => 'Đã thanh toán']]],
            ['name' => 'p_all', 'label' => 'Xem tất cả', 'control' => 'checkbox', 'default' => false, 'required' => false],
            ['name' => 'p_group_by_date', 'label' => 'Nhóm theo ngày', 'control' => 'hidden', 'default' => true, 'required' => false],
            ['name' => 'p_show_settlements', 'label' => 'Hiển thị chi tiết giải trừ', 'control' => 'hidden', 'default' => true, 'required' => false],
        ];
    }

    private function paidUi(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_view_type', 'label' => 'Xem theo', 'control' => 'select', 'default' => 1, 'required' => true, 'options' => [['value' => 0, 'label' => 'Xem theo ngày công nợ'], ['value' => 1, 'label' => 'Xem theo ngày trả công nợ']]],
            ['name' => 'p_company_id', 'label' => 'Công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_group_by_company', 'label' => 'Nhóm theo công ty', 'control' => 'hidden', 'default' => 0, 'required' => false],
            ['name' => 'p_is_fb', 'label' => 'Loại dịch vụ', 'control' => 'hidden', 'default' => 0, 'required' => true],
        ];
    }

    private function previousCompanyUi(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Từ ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_company_id', 'label' => 'Công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_debt_status', 'label' => 'Xem theo', 'control' => 'select', 'default' => 'all', 'required' => true, 'options' => [['value' => 'all', 'label' => 'Tất cả'], ['value' => 'unpaid', 'label' => 'Chưa thanh toán'], ['value' => 'paid', 'label' => 'Đã thanh toán']]],
            ['name' => 'p_all', 'label' => 'Xem tất cả', 'control' => 'checkbox', 'default' => false, 'required' => false],
            ['name' => 'p_group_by_date', 'label' => 'Nhóm theo ngày', 'control' => 'checkbox', 'default' => true, 'required' => false],
            ['name' => 'p_show_settlements', 'label' => 'Hiển thị chi tiết giải trừ', 'control' => 'checkbox', 'default' => false, 'required' => false],
        ];
    }

    private function previousPaidUi(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Từ ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_view_type', 'label' => 'Xem theo', 'control' => 'radio', 'default' => 0, 'required' => true, 'options' => [['value' => 0, 'label' => 'Ngày công nợ'], ['value' => 1, 'label' => 'Ngày thanh toán']]],
            ['name' => 'p_company_id', 'label' => 'Công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_group_by_company', 'label' => 'Nhóm theo công ty', 'control' => 'checkbox', 'default' => false, 'required' => false],
            ['name' => 'p_is_fb', 'label' => 'Loại dịch vụ', 'control' => 'radio', 'default' => 0, 'required' => true, 'options' => [['value' => 0, 'label' => 'FO - Lễ tân'], ['value' => 1, 'label' => 'F&B']]],
        ];
    }
};
