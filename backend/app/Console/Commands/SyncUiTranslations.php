<?php

namespace App\Console\Commands;

use App\Services\TranslationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nạp danh sách chữ giao diện (quét bằng frontend/scripts/extract-i18n.mjs) và bản dịch mặc định
 * vào Cấu hình hệ thống › Ngôn ngữ. Chạy lại bao nhiêu lần cũng được:
 * - Chỉ thêm chữ / vị trí mới, cập nhật bản dịch mặc định (default_value).
 * - Không đụng tới bản dịch khách sạn đã tự sửa (value).
 */
class SyncUiTranslations extends Command
{
    protected $signature = 'i18n:sync
                            {--dir= : Thư mục chứa ui-strings.json và <mã ngôn ngữ>.json}
                            {--missing= : Chỉ liệt kê các chữ chưa có bản dịch cho ngôn ngữ này (vd: en), không ghi DB}';

    protected $description = 'Nạp chữ giao diện và bản dịch mặc định vào bảng dịch đa ngôn ngữ';

    public function handle(): int
    {
        $dir = $this->option('dir') ?: database_path('data/i18n');
        $stringsFile = $dir . DIRECTORY_SEPARATOR . 'ui-strings.json';
        if (! is_file($stringsFile)) {
            $this->error("Không thấy file {$stringsFile}. Chạy `node scripts/extract-i18n.mjs` ở frontend trước.");

            return self::FAILURE;
        }

        $catalogItems = $this->catalogItems();
        $items = array_merge(
            json_decode(file_get_contents($stringsFile), true)['items'] ?? [],
            $this->reportCatalogItems(),
            $this->templateItems(),
            $catalogItems
        );

        if ($lang = $this->option('missing')) {
            return $this->listMissing($items, $catalogItems, $dir, (string) $lang);
        }

        $connection = config('database_domains.system_connection', 'mysql_system');
        $db = DB::connection($connection);
        $now = now();

        // 1. Dòng dịch
        $existing = $db->table('translation_entries')->pluck('id', 'source_hash')->all();
        $newRows = [];
        foreach ($items as $item) {
            $source = TranslationService::normalize($item['source'] ?? '');
            if ($source === '') {
                continue;
            }
            $hash = TranslationService::hash($source);
            if (! isset($existing[$hash]) && ! isset($newRows[$hash])) {
                $newRows[$hash] = [
                    'source_hash' => $hash,
                    'source' => $source,
                    'is_pattern' => TranslationService::isPattern($source),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        foreach (array_chunk(array_values($newRows), 500) as $chunk) {
            $db->table('translation_entries')->insert($chunk);
        }
        $ids = $db->table('translation_entries')->pluck('id', 'source_hash')->all();
        $this->info('Dòng dịch: thêm mới ' . count($newRows) . ', tổng ' . count($ids));

        // 2. Vị trí (theo thứ tự trong file để cây vị trí sắp ổn định)
        $locations = [];
        foreach ($items as $index => $item) {
            $id = $ids[TranslationService::hash(TranslationService::normalize($item['source'] ?? ''))] ?? null;
            foreach (($item['locations'] ?? []) as $location) {
                if (! $id || empty($location['area'])) {
                    continue;
                }
                $area = mb_substr($location['area'], 0, 255);
                $locations["{$id}|{$area}"] = [
                    'entry_id' => $id,
                    'area' => $area,
                    'route' => $location['route'] ?? null,
                    'sort_order' => 100000 + $index,
                    'last_seen_at' => $now,
                ];
            }
        }
        foreach (array_chunk(array_values($locations), 500) as $chunk) {
            $db->table('translation_locations')->upsert($chunk, ['entry_id', 'area'], ['route']);
        }
        $this->info('Vị trí: ' . count($locations));

        // 3. Bản dịch mặc định cho từng ngôn ngữ có file <mã>.json ({"chữ gốc": "bản dịch"})
        $languages = $db->table('languages')->pluck('code')->all();
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') as $file) {
            $lang = basename($file, '.json');
            if ($lang === 'ui-strings' || ! in_array($lang, $languages, true)) {
                continue;
            }
            $map = json_decode(file_get_contents($file), true) ?: [];
            if ($lang === 'en') {
                // Tên tiếng Anh có sẵn trong bảng danh mục dùng khi file chưa có bản dịch
                foreach ($catalogItems as $item) {
                    if (! empty($item['en']) && ! isset($map[$item['source']])) {
                        $map[$item['source']] = $item['en'];
                    }
                }
            }
            $rows = [];
            foreach ($map as $source => $text) {
                $id = $ids[TranslationService::hash(TranslationService::normalize((string) $source))] ?? null;
                if (! $id || ! is_string($text) || trim($text) === '') {
                    continue;
                }
                $rows[] = [
                    'entry_id' => $id,
                    'lang_code' => $lang,
                    'default_value' => trim($text),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                $db->table('translation_values')->upsert($chunk, ['entry_id', 'lang_code'], ['default_value', 'updated_at']);
            }
            $this->info("Bản dịch mặc định [{$lang}]: " . count($rows));
        }

        return self::SUCCESS;
    }

    /** In ra các chữ chưa có bản dịch (dùng để bổ sung file <lang>.json). */
    private function listMissing(array $items, array $catalogItems, string $dir, string $lang): int
    {
        $file = $dir . DIRECTORY_SEPARATOR . $lang . '.json';
        $map = is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
        $missing = [];
        foreach ($items as $item) {
            $source = TranslationService::normalize($item['source'] ?? '');
            if ($source === '' || isset($map[$source]) || ! empty($item['en'])) {
                continue;
            }
            $missing[$source] = $item['locations'][0]['area'] ?? '';
        }
        foreach ($missing as $source => $area) {
            $this->line("{$source}\t{$area}");
        }
        $this->info(count($missing) . ' chữ chưa có bản dịch [' . $lang . ']');

        return self::SUCCESS;
    }

    /**
     * Chữ cố định trong mẫu in / báo cáo (bảng templates) và nhãn ô lọc của Danh mục báo cáo.
     * Phần {{...}} là dữ liệu nên bỏ qua; CSS trong <style> cũng bỏ qua.
     */
    private function templateItems(): array
    {
        $items = [];
        try {
            $templates = DB::table('templates')->get(['name', 'content_html']);
        } catch (\Throwable $e) {
            $this->warn('Bỏ qua mẫu in: ' . $e->getMessage());
            $templates = collect();
        }
        foreach ($templates as $template) {
            $html = preg_replace('/<(style|script)\b[^>]*>.*?<\/\1>/is', '', (string) $template->content_html) ?? '';
            preg_match_all('/>([^<>]+)</u', $html, $matches);
            foreach ($matches[1] as $chunk) {
                foreach (preg_split('/\{\{[^}]*\}\}/', html_entity_decode($chunk, ENT_QUOTES | ENT_HTML5, 'UTF-8')) as $segment) {
                    $text = TranslationService::normalize($segment);
                    if ($this->isVietnameseLabel($text)) {
                        $items[] = ['source' => $text, 'locations' => [['area' => 'Mẫu in & Báo cáo › ' . $template->name, 'route' => '/config']]];
                    }
                }
            }
        }

        try {
            $schemas = DB::table('report_definitions')->get(['name', 'parameter_ui_schema']);
        } catch (\Throwable $e) {
            $schemas = collect();
        }
        foreach ($schemas as $report) {
            foreach ((json_decode((string) $report->parameter_ui_schema, true) ?: []) as $parameter) {
                $labels = [$parameter['label'] ?? null];
                foreach (($parameter['options'] ?? []) as $option) {
                    $labels[] = is_array($option) ? ($option['label'] ?? null) : null;
                }
                foreach (array_filter($labels) as $label) {
                    $text = TranslationService::normalize((string) $label);
                    if ($this->isVietnameseLabel($text)) {
                        $items[] = ['source' => $text, 'locations' => [['area' => 'Menu báo cáo › Ô lọc báo cáo', 'route' => '/reports']]];
                    }
                }
            }
        }
        $this->info('Mẫu in & ô lọc báo cáo: ' . count($items) . ' chữ');

        return $items;
    }

    private function isVietnameseLabel(string $text): bool
    {
        return $text !== '' && mb_strlen($text) <= 1000
            && preg_match('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/iu', $text) === 1;
    }

    /**
     * Danh mục trong DB chi nhánh có hiện trên giao diện và đã có sẵn cột tên tiếng Anh.
     *
     * @return array<int, array{source: string, en: ?string, locations: array}>
     */
    private function catalogItems(): array
    {
        $catalogs = [
            ['room_statuses', 'name_vi', 'name_en', 'Danh mục › Tình trạng phòng'],
            ['booking_statuses', 'name', 'name_en', 'Danh mục › Trạng thái đặt phòng'],
            ['registration_statuses', 'name', 'english', 'Danh mục › Tình trạng đăng ký'],
            ['nationalities', 'nationality_name', 'nationality_name_en', 'Danh mục › Quốc tịch'],
        ];

        $items = [];
        foreach ($catalogs as [$table, $viColumn, $enColumn, $area]) {
            try {
                $rows = DB::table($table)->get([$viColumn, $enColumn]);
            } catch (\Throwable $e) {
                $this->warn("Bỏ qua {$table}: " . $e->getMessage());

                continue;
            }
            foreach ($rows as $row) {
                $source = trim((string) $row->{$viColumn});
                $en = trim((string) $row->{$enColumn});
                if ($source === '' || $source === $en) {
                    continue;
                }
                $items[] = ['source' => $source, 'en' => $en ?: null, 'locations' => [['area' => $area, 'route' => null]]];
            }
        }
        $this->info('Danh mục có sẵn tiếng Anh: ' . count($items) . ' chữ');

        return $items;
    }

    /**
     * Tên và nhóm báo cáo trong Danh mục báo cáo nằm trong DB chi nhánh (không có trong code),
     * nhưng hiện trên menu Báo cáo nên cũng cần dịch.
     */
    private function reportCatalogItems(): array
    {
        try {
            $rows = DB::table('report_definitions')->get(['name', 'group']);
        } catch (\Throwable $e) {
            $this->warn('Bỏ qua Danh mục báo cáo: ' . $e->getMessage());

            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            foreach (['group' => $row->group, 'name' => $row->name] as $field => $text) {
                if (! $text) {
                    continue;
                }
                $area = $field === 'group' ? 'Menu báo cáo' : 'Menu báo cáo › ' . ($row->group ?: 'Báo cáo khác');
                $items[] = ['source' => $text, 'locations' => [['area' => $area, 'route' => '/reports']]];
            }
        }
        $this->info('Danh mục báo cáo: ' . count($items) . ' chữ');

        return $items;
    }
}
