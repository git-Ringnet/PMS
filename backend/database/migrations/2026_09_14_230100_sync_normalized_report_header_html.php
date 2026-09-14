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

        $provider = require database_path('report_templates/minibar_invoices_by_product_reference.php');

        DB::table('templates')
            ->where(function ($query) {
                $query->where('report', 'like', '%_STANDARD')
                    ->orWhere('report', 'like', '%_REFERENCE');
            })
            ->orderBy('id')
            ->chunkById(100, function ($templates) use ($provider) {
                foreach ($templates as $template) {
                    $contentJson = json_decode((string) $template->content_json, true);
                    $header = is_array($contentJson) ? ($contentJson['header'] ?? []) : [];
                    if (! is_array($header) || $header === []) {
                        continue;
                    }

                    $normalizedHtml = $provider->compileHeader($header);
                    $contentHtml = $this->replaceHeader($template->content_html, $normalizedHtml);
                    if ($contentHtml === (string) $template->content_html) {
                        continue;
                    }

                    DB::table('templates')->where('id', $template->id)->update([
                        'content_html' => $contentHtml,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function replaceHeader(?string $html, string $normalizedHtml): string
    {
        $html = (string) $html;
        $start = strpos($html, '<div class="report-header-band">');
        if ($start === false) {
            $start = strpos($html, '<div class="report-header">');
        }
        if ($start === false) {
            return $html;
        }

        $boundaries = array_filter([
            strpos($html, '<table', $start),
            strpos($html, '<div class="report-detail-band">', $start),
            strpos($html, '<div class="report-footer-band">', $start),
        ], static fn ($position) => $position !== false && $position > $start);
        if ($boundaries === []) {
            return $html;
        }

        $boundary = min($boundaries);
        $headerFragment = substr($html, $start, $boundary - $start);
        $closingTag = strrpos($headerFragment, '</div>');
        if ($closingTag === false) {
            return $html;
        }

        $end = $start + $closingTag + strlen('</div>');
        return substr($html, 0, $start).$normalizedHtml.substr($html, $end);
    }

    public function down(): void
    {
        // The previous HTML wrappers were inconsistent; normalization is not reverted.
    }
};
