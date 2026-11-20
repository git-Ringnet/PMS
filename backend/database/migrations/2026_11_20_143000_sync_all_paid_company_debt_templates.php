<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TEMPLATES = [
        'PAID_COMPANY_DEBTS_REFERENCE',
        'PAID_COMPANY_DEBTS_LEGACY_ARMY_V2',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('templates')
            ->whereIn('report', self::TEMPLATES)
            ->orderBy('id')
            ->get(['id', 'content_json', 'content_html', 'css'])
            ->each(function (object $template): void {
                $content = json_decode((string) $template->content_json, true);
                if (! is_array($content) || ! isset($content['detail']) || ! is_array($content['detail'])) {
                    return;
                }

                foreach ($content['detail'] as $blockIndex => $block) {
                    if (($block['id'] ?? null) !== 'paid_company_debts_table') {
                        continue;
                    }

                    foreach ($block['topHeader'] ?? [] as $headerIndex => $header) {
                        $style = is_array($header['style'] ?? null) ? $header['style'] : [];
                        $style['backgroundColor'] = '#d9dde7';
                        $content['detail'][$blockIndex]['topHeader'][$headerIndex]['style'] = $style;
                    }

                    foreach ($block['customRows'] ?? [] as $rowIndex => $row) {
                        if (($row['id'] ?? null) !== 'paid_debts_grand_total_row') {
                            continue;
                        }

                        foreach ($row['cells'] ?? [] as $cellIndex => $cell) {
                            if (! isset($cell['binding'])) {
                                continue;
                            }

                            $content['detail'][$blockIndex]['customRows'][$rowIndex]['cells'][$cellIndex]['binding'] = match ($cell['binding']) {
                                'aggregate.totals.DebtAmount' => 'totals.DebtAmount',
                                'aggregate.totals.PaidAmount' => 'totals.PaidAmount',
                                'aggregate.totals.RemainingAmount' => 'totals.RemainingAmount',
                                default => $cell['binding'],
                            };
                        }
                    }
                }

                $html = str_replace([
                    '{{aggregate.totals.DebtAmount|number}}',
                    '{{aggregate.totals.PaidAmount|number}}',
                    '{{aggregate.totals.RemainingAmount|number}}',
                ], [
                    '{{totals.DebtAmount|number}}',
                    '{{totals.PaidAmount|number}}',
                    '{{totals.RemainingAmount|number}}',
                ], (string) $template->content_html);
                $html = preg_replace_callback('/<thead\\b[^>]*>.*?<\\/thead>/is', static fn (array $matches): string => str_replace(
                    ['#f1f5f9', '#e2e8f0'],
                    '#d9dde7',
                    $matches[0]
                ), $html) ?? $html;

                $css = (string) $template->css;
                if (str_contains($css, '.paid-company-debts-table thead tr:first-child th {')
                    && ! str_contains($css, ".paid-company-debts-table thead tr:first-child th {\n    background-color: #d9dde7;")) {
                    $css = str_replace(
                        '.paid-company-debts-table thead tr:first-child th {',
                        ".paid-company-debts-table thead tr:first-child th {\n    background-color: #d9dde7;",
                        $css
                    );
                }

                DB::table('templates')->where('id', $template->id)->update([
                    'content_json' => json_encode($content, JSON_UNESCAPED_UNICODE),
                    'content_html' => $html,
                    'css' => $css,
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Preserve user changes to both templates after this targeted correction.
    }
};
