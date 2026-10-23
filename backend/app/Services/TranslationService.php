<?php

namespace App\Services;

use App\Models\Language;
use App\Models\TranslationEntry;
use Illuminate\Support\Facades\DB;

/**
 * Từ điển đa ngôn ngữ dùng chung: chữ gốc tiếng Việt → bản dịch theo từng ngôn ngữ.
 * Dùng cho API giao diện và dịch nhãn cố định trên mẫu in / báo cáo.
 */
class TranslationService
{
    /** @var array<string, array{entries: array<string, string>, patterns: array<int, array>}> */
    private array $memo = [];

    public function enabled(): bool
    {
        return (bool) config('i18n.enabled', true);
    }

    public function sourceLanguage(): string
    {
        return (string) config('i18n.source_language', 'vi');
    }

    /** Chuẩn hóa chữ gốc: bỏ khoảng trắng đầu/cuối, gộp khoảng trắng liên tiếp. */
    public static function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    public static function hash(string $normalized): string
    {
        return sha1($normalized);
    }

    public static function isPattern(string $normalized): bool
    {
        return (bool) preg_match('/\{[a-zA-Z0-9_]+\}/', $normalized);
    }

    /** Tìm hoặc tạo dòng dịch theo chữ gốc. */
    public function findOrCreateEntry(string $source): ?TranslationEntry
    {
        $normalized = self::normalize($source);
        if ($normalized === '' || mb_strlen($normalized) > 1000) {
            return null;
        }

        return TranslationEntry::firstOrCreate(
            ['source_hash' => self::hash($normalized)],
            ['source' => $normalized, 'is_pattern' => self::isPattern($normalized)]
        );
    }

    /**
     * Phiên bản dữ liệu dịch, đổi mỗi khi có thêm / sửa / xóa bản dịch hoặc ngôn ngữ.
     * Tính trực tiếp trên DB hệ thống để mọi chi nhánh thấy cùng một phiên bản.
     */
    public function version(): string
    {
        $values = DB::connection($this->connection())->table('translation_values')
            ->selectRaw('COUNT(*) as c, MAX(updated_at) as m')->first();
        $languages = DB::connection($this->connection())->table('languages')
            ->selectRaw('COUNT(*) as c, MAX(updated_at) as m')->first();

        return substr(md5(json_encode([$values, $languages])), 0, 12);
    }

    /** @return array<int, array{code: string, name: string, flag: ?string, is_default: bool}> */
    public function activeLanguages(): array
    {
        return Language::where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->get(['code', 'name', 'flag', 'is_default'])
            ->map(fn (Language $l) => [
                'code' => $l->code,
                'name' => $l->name,
                'flag' => $l->flag,
                'is_default' => $l->is_default,
            ])
            ->all();
    }

    /**
     * Từ điển cho một ngôn ngữ: [chữ gốc => bản dịch].
     * Với ngôn ngữ gốc (vi) chỉ trả các chữ khách sạn đã sửa lại.
     *
     * @return array{entries: array<string, string>, patterns: array<string, string>}
     */
    public function dictionary(string $lang): array
    {
        $rows = DB::connection($this->connection())->table('translation_values as v')
            ->join('translation_entries as e', 'e.id', '=', 'v.entry_id')
            ->where('v.lang_code', $lang)
            ->get(['e.source', 'e.is_pattern', 'v.value', 'v.default_value']);

        $isSource = $lang === $this->sourceLanguage();
        $entries = [];
        $patterns = [];

        foreach ($rows as $row) {
            $text = $row->value;
            if (($text === null || $text === '') && ! $isSource) {
                $text = $row->default_value;
            }
            if ($text === null || $text === '' || $text === $row->source) {
                continue;
            }
            if ($row->is_pattern) {
                // Mẫu chỉ toàn {biến} sẽ khớp mọi đoạn chữ → bỏ qua
                if (preg_match('/\p{L}/u', preg_replace('/\{[a-zA-Z0-9_]+\}/', '', $row->source) ?? '')) {
                    $patterns[$row->source] = $text;
                }
            } else {
                $entries[$row->source] = $text;
            }
        }

        return ['entries' => $entries, 'patterns' => $patterns];
    }

    /** Dịch một đoạn chữ thuần (không chứa HTML). Trả về null nếu không có bản dịch. */
    public function translateText(string $text, string $lang): ?string
    {
        $dict = $this->compiled($lang);
        if ($dict['entries'] === [] && $dict['patterns'] === []) {
            return null;
        }

        if (! preg_match('/^(\s*)(.*?)(\s*)$/su', $text, $m)) {
            return null;
        }
        $core = self::normalize($m[2]);
        if ($core === '' || ! preg_match('/\p{L}/u', $core)) {
            return null;
        }

        if (isset($dict['entries'][$core])) {
            return $m[1] . $dict['entries'][$core] . $m[3];
        }

        // Nhãn kèm dấu ở cuối ("Ngày đến:", "Họ tên *") dùng chung bản dịch với nhãn không dấu
        if (preg_match('/^(.*?\p{L}.*?)(\s*[:：*]+)$/u', $core, $suffix) && isset($dict['entries'][$suffix[1]])) {
            return $m[1] . $dict['entries'][$suffix[1]] . $suffix[2] . $m[3];
        }

        foreach ($dict['patterns'] as $pattern) {
            if (preg_match($pattern['regex'], $core, $found) && $this->capturesFit($pattern, $found)) {
                $out = $pattern['value'];
                foreach ($pattern['names'] as $i => $name) {
                    // Giá trị biến cũng là chữ đã có bản dịch (vd tên trạng thái) thì dịch luôn
                    $value = $found[$i + 1];
                    $out = str_replace('{' . $name . '}', $dict['entries'][trim($value)] ?? $value, $out);
                }

                return $m[1] . $out . $m[3];
            }
        }

        return null;
    }

    /**
     * Dịch các nhãn cố định trong HTML mẫu in TRƯỚC khi thay dữ liệu {{...}},
     * nên dữ liệu thật (tên khách, số tiền...) không bao giờ bị dịch.
     */
    public function translateTemplateHtml(string $html, ?string $lang): string
    {
        if (! $this->enabled() || ! $lang || $html === '') {
            return $html;
        }
        $dict = $this->compiled($lang);
        if ($dict['entries'] === [] && $dict['patterns'] === []) {
            return $html;
        }

        // Không đụng vào nội dung <style>/<script>
        $parts = preg_split('/(<(?:style|script)\b[^>]*>.*?<\/(?:style|script)>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                continue;
            }
            $parts[$index] = preg_replace_callback('/>([^<>]+)</u', function (array $match) use ($lang): string {
                return '>' . $this->translateTextChunk($match[1], $lang) . '<';
            }, $part) ?? $part;
        }

        return implode('', $parts);
    }

    /** Dịch phần chữ nằm giữa các placeholder {{...}} của một đoạn text trong mẫu in. */
    private function translateTextChunk(string $chunk, string $lang): string
    {
        $segments = preg_split('/(\{\{[^}]*\}\})/', $chunk, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($segments as $i => $segment) {
            if ($i % 2 === 1 || trim($segment) === '') {
                continue;
            }
            $decoded = html_entity_decode($segment, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $translated = $this->translateText($decoded, $lang);
            if ($translated !== null) {
                $segments[$i] = htmlspecialchars($translated, ENT_NOQUOTES, 'UTF-8');
            }
        }

        return implode('', $segments);
    }

    /** Từ điển đã biên dịch (mẫu câu → regex), nhớ trong suốt request. */
    private function compiled(string $lang): array
    {
        if (isset($this->memo[$lang])) {
            return $this->memo[$lang];
        }

        try {
            $dict = $this->dictionary($lang);
        } catch (\Throwable $e) {
            $dict = ['entries' => [], 'patterns' => []];
        }

        $patterns = [];
        foreach ($dict['patterns'] as $source => $value) {
            preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $source, $names);
            $regex = preg_quote($source, '/');
            $regex = preg_replace('/\\\\\{[a-zA-Z0-9_]+\\\\\}/', '(.*?)', $regex);
            // Mẫu có ít chữ cố định (vd "{0} ngày") rất dễ khớp nhầm cả câu dài → giới hạn giá trị biến tối đa 3 từ
            $literal = trim(preg_replace('/\{[a-zA-Z0-9_]+\}/', ' ', $source) ?? '');
            $literalWords = $literal === '' ? 0 : count(preg_split('/\s+/u', $literal));
            $patterns[] = [
                'literal_length' => mb_strlen(str_replace(' ', '', $literal)),
                'regex' => '/^' . $regex . '$/u',
                'names' => $names[1],
                'value' => $value,
                'max_capture_words' => $literalWords < 3 ? 3 : PHP_INT_MAX,
            ];
        }

        // Mẫu cụ thể hơn (nhiều chữ cố định hơn) được thử trước
        usort($patterns, fn (array $a, array $b) => $b['literal_length'] <=> $a['literal_length']);

        return $this->memo[$lang] = ['entries' => $dict['entries'], 'patterns' => $patterns];
    }

    private function capturesFit(array $pattern, array $found): bool
    {
        foreach (array_slice($found, 1) as $part) {
            if (count(preg_split('/\s+/u', trim($part))) > $pattern['max_capture_words']) {
                return false;
            }
        }

        return true;
    }

    public function forget(): void
    {
        $this->memo = [];
    }

    private function connection(): string
    {
        return config('database_domains.system_connection', 'mysql_system');
    }
}
