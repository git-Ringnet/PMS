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
                    if (! is_array($contentJson)) {
                        continue;
                    }

                    $header = $contentJson['header'] ?? [];
                    $title = $this->findHeaderTag($header, 'h1')
                        ?? '<h1>'.htmlspecialchars((string) ($template->name ?: $template->report), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</h1>';
                    $period = $this->findHeaderTag($header, 'p')
                        ?? '<p class="period"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>';
                    $prefix = strtolower((string) preg_replace('/_(STANDARD|REFERENCE)$/', '', (string) $template->report));
                    $normalizedHeader = $provider->headerBlocks($prefix, $title, $period);

                    $contentJson['header'] = $normalizedHeader;
                    $contentHtml = $this->replaceHeaderBand(
                        (string) $template->content_html,
                        $provider->compileHeader($normalizedHeader)
                    );

                    DB::table('templates')->where('id', $template->id)->update([
                        'content_json' => json_encode($contentJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'content_html' => $contentHtml,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function findHeaderTag(array $blocks, string $tag): ?string
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (is_string($block['content'] ?? null)
                && preg_match('/<'.preg_quote($tag, '/').'\b[^>]*>.*?<\/'.preg_quote($tag, '/').'>/is', $block['content'], $matches)) {
                return $matches[0];
            }

            foreach ($block['columns'] ?? [] as $column) {
                $found = $this->findHeaderTag($column['blocks'] ?? [], $tag);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function replaceHeaderBand(string $html, string $headerHtml): string
    {
        $start = strpos($html, '<div class="report-header-band">');
        $detail = strpos($html, '<div class="report-detail-band">');

        if ($start === false || $detail === false || $detail <= $start) {
            return $html;
        }

        return substr($html, 0, $start).$headerHtml.substr($html, $detail);
    }

    public function down(): void
    {
        // Header normalization is intentionally not reverted because the
        // previous structures were inconsistent across report templates.
    }
};
