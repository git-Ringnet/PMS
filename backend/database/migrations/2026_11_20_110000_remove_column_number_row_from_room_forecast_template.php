<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TC_BC_BP_02 (BP dòng 163 / Lễ tân dòng 352): báo cáo dự đoán bán phòng bỏ dòng đầu tiên
 * chỉ có số thứ tự cột, vì số cột đã nằm trong tên cột "(1)...(n)".
 *
 * Chỉ gỡ đúng dòng đó khỏi mẫu đã lưu (không ghi đè các chỉnh sửa khác của người dùng).
 */
return new class extends Migration
{
    private const TEMPLATE = 'ROOM_FORECAST_REFERENCE';

    public function up(): void
    {
        $db = DB::connection();
        $template = $db->table('templates')->where('report', self::TEMPLATE)->first();
        if (! $template) {
            return;
        }

        $html = (string) $template->content_html;
        $html = preg_replace('~<tr\s+class="index-row">.*?</tr>~is', '', $html) ?? $html;
        $css = preg_replace('~^\.room-forecast-table \.index-row th \{[^\n]*\}\R?~m', '', (string) $template->css) ?? (string) $template->css;

        $json = json_decode((string) $template->content_json, true);
        if (is_array($json)) {
            foreach (['header', 'detail', 'footer'] as $section) {
                foreach ($json[$section] ?? [] as $i => $block) {
                    if (($block['type'] ?? '') === 'table' && ($block['tableClassName'] ?? '') === 'room-forecast-table') {
                        unset($json[$section][$i]['topHeader']);
                    }
                }
            }
        }

        $db->table('templates')->where('id', $template->id)->update([
            'content_html' => $html,
            'css' => $css,
            'content_json' => is_array($json) ? json_encode($json, JSON_UNESCAPED_UNICODE) : $template->content_json,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Không khôi phục dòng số cột.
    }
};
