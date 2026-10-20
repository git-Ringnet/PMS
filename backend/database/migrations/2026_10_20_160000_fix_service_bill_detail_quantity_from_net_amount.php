<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dòng chi tiết tiền phòng/ăn sáng tạo khi chưa truyền số lượng bị lưu
 * Quantity = Amount / OriginalRate (tiền đã gồm thuế phí chia cho giá chưa
 * thuế), ví dụ 1.13 thay vì 1. Số lượng đúng = tiền chưa thuế / đơn giá.
 * Chỉ sửa dòng RM/BF vì các dòng này luôn dựng từ TaxBreakdownService
 * (OriginalRate = net / số lượng); dòng khác có thể có chiết khấu riêng.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('service_bill_details')
            ->whereIn('ServiceId', ['RM', 'BF'])
            ->where('OriginalRate', '!=', 0)
            ->whereNotNull('OriginalRate')
            ->whereNotNull('OriginalAmount')
            ->whereRaw('ABS(Quantity - ROUND(OriginalAmount / OriginalRate, 2)) > 0.005')
            ->update(['Quantity' => DB::raw('ROUND(OriginalAmount / OriginalRate, 2)')]);
    }

    public function down(): void
    {
        // Dữ liệu cũ sai nên không khôi phục.
    }
};
