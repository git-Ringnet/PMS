<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Task 228 (tracker dòng 228, note 18/11): mô tả chuyển cọc phải theo dạng "nội dung cọc (nơi đi=>nơi đến)".
 *
 * Code mới đã tạo dòng nhận cọc đúng dạng đó; migration này đổi các dòng nhận cọc cũ
 * "[TRANSFER IN BK_7=>BK_11] Deposit (Cash)" thành "Deposit (Cash) (BK_7=>BK_11)".
 * Dòng đã xóa "[TRANSFER IN …] … Correct: lý do" thành "… (X=>Y) Correct: lý do".
 * Chỉ sửa cột description; chạy lại không đổi thêm (dòng đã đổi không còn bắt đầu bằng "[TRANSFER IN").
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('payments')
            ->where('description', 'like', '%TRANSFER IN %')
            ->get(['id', 'description']);

        foreach ($rows as $row) {
            $description = (string) $row->description;
            if (! preg_match('/^\[TRANSFER IN\s+([^\]=]+?)\s*=>\s*([^\]]+?)\s*\]\s*(.*)$/su', $description, $m)) {
                continue;
            }

            [, $from, $to, $body] = $m;
            $route = ' ('.$from.'=>'.$to.')';
            $body = rtrim($body);

            // Giữ phần " Correct: lý do" của dòng đã xóa ở cuối mô tả.
            $position = mb_strpos($body, ' Correct: ');
            $normalized = $position === false
                ? $body.$route
                : mb_substr($body, 0, $position).$route.mb_substr($body, $position);

            DB::table('payments')->where('id', $row->id)->update(['description' => mb_substr($normalized, 0, 255)]);
        }
    }

    public function down(): void
    {
        // Không khôi phục tiền tố "[TRANSFER IN …]".
    }
};
