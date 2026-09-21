<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    private const CONNECTIONS = [
        'mysql',
        'mysql_hkt1',
        'mysql_hkt2',
        'mysql_hkt3',
        'mysql_hkt4',
    ];

    public function up(): void
    {
        $provider = require database_path('report_templates/deposits_sale_reference.php');
        $definition = $provider->definition();
        $detailMarker = '<div class="report-detail-band">';
        $referenceBlocks = [];
        foreach ($definition['content_json']['header'] ?? [] as $block) {
            if (in_array($block['id'] ?? '', ['deposits_sale_hotel_header', 'deposits_sale_divider'], true)) {
                $referenceBlocks[$block['id']] = $block;
            }
        }
        if (count($referenceBlocks) !== 2) {
            throw new RuntimeException('The DEPOSITS_SALE reference header is missing one or more target blocks.');
        }

        foreach (self::CONNECTIONS as $connection) {
            $db = DB::connection($connection);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }

            $templates = $db->table('templates')
                ->where('report', 'DEPOSITS_SALE_REFERENCE')
                ->get(['id', 'content_json', 'content_html']);

            foreach ($templates as $template) {
                $content = is_array($template->content_json)
                    ? $template->content_json
                    : json_decode((string) $template->content_json, true);
                if (! is_array($content)
                    || ! is_array($content['header'] ?? null)
                    || ! is_array($content['detail'] ?? null)
                    || ! is_array($content['footer'] ?? null)) {
                    Log::warning('Skipped DEPOSITS_SALE header update because the saved Design structure is incomplete.', [
                        'connection' => $connection,
                        'template_id' => $template->id,
                    ]);
                    continue;
                }

                $found = [];
                foreach ($content['header'] as $index => $block) {
                    $blockId = $block['id'] ?? '';
                    if (isset($referenceBlocks[$blockId])) {
                        $content['header'][$index] = $referenceBlocks[$blockId];
                        $found[$blockId] = true;
                    }
                }

                if (count($found) !== count($referenceBlocks)) {
                    Log::warning('Skipped DEPOSITS_SALE header update because expected header block IDs were not found.', [
                        'connection' => $connection,
                        'template_id' => $template->id,
                    ]);
                    continue;
                }

                $compiledHeader = $provider->compileHeader($content['header']);

                $currentHtml = (string) ($template->content_html ?? '');
                $currentHeaderStart = strpos($currentHtml, '<div class="report-header-band">');
                $currentDetailStart = $currentHeaderStart === false
                    ? false
                    : strpos($currentHtml, $detailMarker, $currentHeaderStart);
                if ($currentHeaderStart === false || $currentDetailStart === false) {
                    Log::warning('Skipped DEPOSITS_SALE HTML header update because its saved band markers were not found.', [
                        'connection' => $connection,
                        'template_id' => $template->id,
                    ]);
                    continue;
                }

                $contentHtml = substr_replace(
                    $currentHtml,
                    $compiledHeader,
                    $currentHeaderStart,
                    $currentDetailStart - $currentHeaderStart
                );

                $db->table('templates')->where('id', $template->id)->update([
                    'content_json' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'content_html' => $contentHtml,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep the corrected report header when rolling back unrelated changes.
    }
};
