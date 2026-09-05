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

        DB::table('templates')
            ->where(function ($query) {
                $query->where('content_html', 'like', '%report-header-band%')
                    ->orWhere('content_html', 'like', '%report-detail-band%')
                    ->orWhere('content_html', 'like', '%pms-template-block-%');
            })
            ->orderBy('id')
            ->chunkById(100, function ($templates) {
                foreach ($templates as $template) {
                    $contentJson = json_decode((string) $template->content_json, true);
                    if (is_array($contentJson)) {
                        $contentJson = $this->normalizeBlockStyles($contentJson);
                    }

                    $html = str_replace('white-space: pre-wrap', 'white-space: normal', (string) $template->content_html);
                    $css = (string) $template->css;
                    $tableReset = '.report-header-band table,.report-detail-band table,.report-footer-band table{margin-top:0;margin-bottom:0}';
                    if (str_contains($html, 'report-header-band') && ! str_contains($css, $tableReset)) {
                        $css .= $tableReset;
                    }

                    DB::table('templates')->where('id', $template->id)->update([
                        'content_json' => is_array($contentJson) ? json_encode($contentJson, JSON_UNESCAPED_UNICODE) : $template->content_json,
                        'content_html' => $html,
                        'css' => $css,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function normalizeBlockStyles(array $value): array
    {
        foreach ($value as $key => $item) {
            if ($key === 'style' && is_array($item) && ($item['whiteSpace'] ?? null) === 'pre-wrap') {
                $value[$key]['whiteSpace'] = 'normal';
                continue;
            }

            if (is_array($item)) {
                $value[$key] = $this->normalizeBlockStyles($item);
            }
        }

        return $value;
    }

    public function down(): void
    {
        // Normalized layout values are intentionally not reverted because the
        // previous values were renderer-generated whitespace rather than user data.
    }
};
