<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TEMPLATE = 'PAID_COMPANY_DEBTS_LEGACY_ARMY_V2';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $template = DB::table('templates')->where('report', self::TEMPLATE)->first(['id', 'content_json', 'content_html', 'css']);
        if (! $template) {
            return;
        }

        $updates = [
            'content_json' => $this->patchedContentJson((string) $template->content_json),
            'content_html' => $this->patchedContentHtml((string) $template->content_html),
            'css' => $this->patchedCss((string) $template->css),
            'updated_at' => now(),
        ];

        DB::table('templates')->where('id', $template->id)->update($updates);
    }

    public function down(): void
    {
        // Deliberately preserve report designs edited by users after this targeted correction.
    }

    private function patchedContentJson(string $contentJson): string
    {
        $content = json_decode($contentJson, true);
        if (! is_array($content)) {
            return $contentJson;
        }

        if (! isset($content['detail']) || ! is_array($content['detail'])) {
            return $contentJson;
        }

        foreach ($content['detail'] as $blockIndex => $block) {
            if (($block['id'] ?? null) !== 'paid_company_debts_table') {
                continue;
            }

            foreach ($content['detail'][$blockIndex]['topHeader'] ?? [] as $headerIndex => $header) {
                $style = is_array($header['style'] ?? null) ? $header['style'] : [];
                $style['backgroundColor'] = '#d9dde7';
                $content['detail'][$blockIndex]['topHeader'][$headerIndex]['style'] = $style;
            }

            foreach ($content['detail'][$blockIndex]['customRows'] ?? [] as $customRowIndex => $customRow) {
                if (($customRow['id'] ?? null) !== 'paid_debts_grand_total_row') {
                    continue;
                }

                foreach ($customRow['cells'] ?? [] as $cellIndex => $cell) {
                    if (! isset($cell['binding'])) {
                        continue;
                    }

                    $content['detail'][$blockIndex]['customRows'][$customRowIndex]['cells'][$cellIndex]['binding'] = match ($cell['binding']) {
                        'aggregate.totals.DebtAmount' => 'totals.DebtAmount',
                        'aggregate.totals.PaidAmount' => 'totals.PaidAmount',
                        'aggregate.totals.RemainingAmount' => 'totals.RemainingAmount',
                        default => $cell['binding'],
                    };
                }
            }
        }

        return json_encode($content, JSON_UNESCAPED_UNICODE) ?: $contentJson;
    }

    private function patchedContentHtml(string $contentHtml): string
    {
        $contentHtml = str_replace([
            '{{aggregate.totals.DebtAmount|number}}',
            '{{aggregate.totals.PaidAmount|number}}',
            '{{aggregate.totals.RemainingAmount|number}}',
        ], [
            '{{totals.DebtAmount|number}}',
            '{{totals.PaidAmount|number}}',
            '{{totals.RemainingAmount|number}}',
        ], $contentHtml);

        return preg_replace_callback('/<thead\\b[^>]*>.*?<\\/thead>/is', static fn (array $matches): string => str_replace(
            ['#f1f5f9', '#e2e8f0'],
            '#d9dde7',
            $matches[0]
        ), $contentHtml) ?? $contentHtml;
    }

    private function patchedCss(string $css): string
    {
        if (str_contains($css, '.paid-company-debts-table thead tr:first-child th {')
            && ! str_contains($css, ".paid-company-debts-table thead tr:first-child th {\n    background-color: #d9dde7;")) {
            return str_replace(
                '.paid-company-debts-table thead tr:first-child th {',
                ".paid-company-debts-table thead tr:first-child th {\n    background-color: #d9dde7;",
                $css
            );
        }

        return $css;
    }
};
