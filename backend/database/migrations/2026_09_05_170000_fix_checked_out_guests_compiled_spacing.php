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

        $template = DB::table('templates')->where('report', 'CHECKED_OUT_GUESTS_STANDARD')->first();
        if (! $template) {
            return;
        }

        $blocks = json_decode((string) $template->content_json, true);
        if (is_array($blocks)) {
            $blocks = $this->normalizeWhiteSpace($blocks);
        }

        $css = (string) $template->css;
        $tableReset = '.report-header-band table,.report-detail-band table,.report-footer-band table{margin-top:0;margin-bottom:0}';
        if (! str_contains($css, $tableReset)) {
            $css .= $tableReset;
        }

        DB::table('templates')->where('id', $template->id)->update([
            'content_json' => is_array($blocks) ? json_encode($blocks, JSON_UNESCAPED_UNICODE) : $template->content_json,
            'content_html' => str_replace('white-space: pre-wrap', 'white-space: normal', (string) $template->content_html),
            'css' => $css,
            'version' => '1.8',
            'updated_at' => now(),
        ]);
    }

    private function normalizeWhiteSpace(array $value): array
    {
        foreach ($value as $key => $item) {
            if ($key === 'style' && is_array($item)) {
                $value[$key]['whiteSpace'] = 'normal';
                continue;
            }

            if (is_array($item)) {
                $value[$key] = $this->normalizeWhiteSpace($item);
            }
        }

        return $value;
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('templates')->where('report', 'CHECKED_OUT_GUESTS_STANDARD')->update([
            'version' => '1.7',
            'updated_at' => now(),
        ]);
    }
};
