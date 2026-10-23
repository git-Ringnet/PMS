<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\TranslationEntry;
use App\Models\TranslationLocation;
use App\Models\TranslationValue;
use App\Services\ActivityLogService;
use App\Services\TranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Ngôn ngữ & Bản dịch dùng chung toàn hệ thống (Cấu hình hệ thống › Ngôn ngữ).
 */
class TranslationController extends Controller
{
    public function __construct(private readonly TranslationService $translations)
    {
    }

    // =====================================================================
    // PUBLIC: giao diện tải danh sách ngôn ngữ và từ điển
    // =====================================================================

    /** GET /i18n/languages */
    public function languages()
    {
        return response()->json([
            'success' => true,
            'data' => $this->translations->enabled() ? $this->translations->activeLanguages() : [],
        ]);
    }

    /** GET /i18n/dictionary/{lang}?v=<phiên bản client đang có> */
    public function dictionary(Request $request, string $lang)
    {
        if (! $this->translations->enabled()) {
            return response()->json(['success' => true, 'data' => ['version' => 'off', 'entries' => [], 'patterns' => []]]);
        }

        $version = $this->translations->version();
        if ($request->query('v') === $version) {
            return response()->json(['success' => true, 'data' => ['version' => $version, 'unchanged' => true]]);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge(['version' => $version], $this->translations->dictionary($lang)),
        ]);
    }

    // =====================================================================
    // QUẢN TRỊ BẢN DỊCH
    // =====================================================================

    /** GET /i18n/admin/areas - cây vị trí kèm số dòng và số dòng còn thiếu dịch */
    public function areas()
    {
        $languages = Language::where('is_active', true)->where('is_default', false)->pluck('code');

        $rows = DB::connection($this->connection())->table('translation_locations as l')
            ->select('l.area', DB::raw('COUNT(DISTINCT l.entry_id) as total'), DB::raw('MIN(l.sort_order) as sort_order'))
            ->groupBy('l.area')
            ->get();

        $missing = [];
        if ($languages->isNotEmpty()) {
            $missing = DB::connection($this->connection())->table('translation_locations as l')
                ->whereExists(function ($q) use ($languages) {
                    $q->selectRaw('1')->from('languages as lg')
                        ->whereIn('lg.code', $languages)
                        ->whereNotExists(function ($q2) {
                            $q2->selectRaw('1')->from('translation_values as v')
                                ->whereColumn('v.entry_id', 'l.entry_id')
                                ->whereColumn('v.lang_code', 'lg.code')
                                ->whereRaw("COALESCE(NULLIF(v.value, ''), NULLIF(v.default_value, '')) IS NOT NULL");
                        });
                })
                ->groupBy('l.area')
                ->selectRaw('l.area, COUNT(DISTINCT l.entry_id) as cnt')
                ->pluck('cnt', 'area')
                ->all();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'areas' => $rows->map(fn ($r) => [
                    'area' => $r->area,
                    'total' => (int) $r->total,
                    'missing' => (int) ($missing[$r->area] ?? 0),
                    'sort_order' => (int) $r->sort_order,
                ])->values(),
                'unplaced' => TranslationEntry::doesntHave('locations')->count(),
                'total' => TranslationEntry::count(),
            ],
        ]);
    }

    /** GET /i18n/admin/entries?area=&search=&missing=en&modified=1&page= */
    public function entries(Request $request)
    {
        $query = TranslationEntry::query()->with(['values', 'locations' => fn ($q) => $q->orderBy('sort_order')]);
        $area = (string) $request->query('area', '');

        if ($area === '__unplaced') {
            $query->doesntHave('locations');
        } elseif ($area !== '') {
            $query->whereHas('locations', fn ($q) => $q->where('area', $area)->orWhere('area', 'like', $this->escapeLike($area) . ' › %'));
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $like = '%' . $this->escapeLike($search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('source', 'like', $like)
                    ->orWhereHas('values', fn ($v) => $v->where('value', 'like', $like)->orWhere('default_value', 'like', $like));
            });
        }

        if ($missing = $request->query('missing')) {
            $query->whereDoesntHave('values', fn ($v) => $v->where('lang_code', $missing)
                ->whereRaw("COALESCE(NULLIF(value, ''), NULLIF(default_value, '')) IS NOT NULL"));
        }

        if ($request->boolean('modified')) {
            $query->whereHas('values', fn ($v) => $v->whereNotNull('value')->where('value', '!=', ''));
        }

        if ($area !== '' && $area !== '__unplaced') {
            $query->orderBy(
                TranslationLocation::selectRaw('MIN(sort_order)')
                    ->whereColumn('translation_locations.entry_id', 'translation_entries.id')
                    ->where(fn ($q) => $q->where('area', $area)->orWhere('area', 'like', $this->escapeLike($area) . ' › %'))
            );
        }
        $query->orderBy('id');

        $page = $query->paginate(min(200, max(20, (int) $request->query('per_page', 100))));

        return response()->json([
            'success' => true,
            'data' => collect($page->items())->map(fn (TranslationEntry $e) => $this->presentEntry($e))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** GET /i18n/admin/lookup?source= - dùng cho popup sửa trực tiếp trên màn hình */
    public function lookup(Request $request)
    {
        $source = TranslationService::normalize((string) $request->query('source', ''));
        $entry = $source === '' ? null : TranslationEntry::with(['values', 'locations'])
            ->where('source_hash', TranslationService::hash($source))->first();

        return response()->json([
            'success' => true,
            'data' => $entry ? $this->presentEntry($entry) : ['id' => null, 'source' => $source, 'values' => [], 'locations' => []],
        ]);
    }

    /** POST /i18n/admin/entries - thêm dòng thủ công (vd mẫu câu "Còn {n} phòng trống") */
    public function storeEntry(Request $request)
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:255'],
            'values' => ['nullable', 'array'],
        ]);

        $entry = $this->translations->findOrCreateEntry($data['source']);
        if (! $entry) {
            return response()->json(['success' => false, 'message' => 'Chữ gốc không hợp lệ.'], 422);
        }
        if (isset($data['note'])) {
            $entry->update(['note' => $data['note']]);
        }
        $this->saveValues($entry, $data['values'] ?? [], $request);

        ActivityLogService::logBusiness([
            'action' => 'create', 'module' => 'SYSTEM', 'component' => 'Ngôn ngữ',
            'description' => 'Thêm dòng dịch: ' . $entry->source,
            'target_type' => 'TranslationEntry', 'target_id' => $entry->id, 'target_label' => $entry->source,
        ], $request);

        return response()->json(['success' => true, 'data' => $this->presentEntry($entry->fresh(['values', 'locations']))]);
    }

    /** PUT /i18n/admin/entries - lưu nhiều dòng một lần: [{id|source, values: {en: '...', vi: null}}] */
    public function saveEntries(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'max:500'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.source' => ['nullable', 'string', 'max:1000'],
            'items.*.values' => ['required', 'array'],
            'items.*.area' => ['nullable', 'string', 'max:255'],
            'items.*.route' => ['nullable', 'string', 'max:255'],
        ]);

        $saved = [];
        $changes = [];
        DB::connection($this->connection())->transaction(function () use ($data, $request, &$saved, &$changes) {
            foreach ($data['items'] as $item) {
                $entry = ! empty($item['id'])
                    ? TranslationEntry::find($item['id'])
                    : $this->translations->findOrCreateEntry((string) ($item['source'] ?? ''));
                if (! $entry) {
                    continue;
                }
                if (! empty($item['area'])) {
                    $this->touchLocation($entry->id, $item['area'], $item['route'] ?? null, 0);
                }
                foreach ($this->saveValues($entry, $item['values'], $request) as $change) {
                    $changes[] = $change;
                }
                $saved[] = $entry->id;
            }
        });

        if ($changes) {
            ActivityLogService::logBusiness([
                'action' => 'update', 'module' => 'SYSTEM', 'component' => 'Ngôn ngữ',
                'description' => 'Cập nhật ' . count($changes) . ' bản dịch',
                'target_type' => 'TranslationEntry',
                'target_label' => mb_substr(implode('; ', array_map(fn ($c) => $c['label'], $changes)), 0, 250),
                'old_values' => array_map(fn ($c) => $c['old'], $changes),
                'new_values' => array_map(fn ($c) => $c['new'], $changes),
            ], $request);
        }

        $entries = TranslationEntry::with(['values', 'locations'])->whereIn('id', $saved)->get();

        return response()->json([
            'success' => true,
            'data' => $entries->map(fn ($e) => $this->presentEntry($e))->values(),
            'version' => $this->translations->version(),
        ]);
    }

    /** DELETE /i18n/admin/entries/{id} */
    public function destroyEntry(Request $request, int $id)
    {
        $entry = TranslationEntry::findOrFail($id);
        $label = $entry->source;
        $entry->delete();

        ActivityLogService::logBusiness([
            'action' => 'delete', 'module' => 'SYSTEM', 'component' => 'Ngôn ngữ',
            'description' => 'Xóa dòng dịch: ' . $label,
            'target_type' => 'TranslationEntry', 'target_id' => $id, 'target_label' => $label,
        ], $request);

        return response()->json(['success' => true]);
    }

    /** DELETE /i18n/admin/entries/{id}/locations - bỏ vị trí cũ (màn hình đã không còn chữ này) */
    public function clearLocations(int $id)
    {
        TranslationLocation::where('entry_id', $id)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * POST /i18n/collect - ghi nhận chữ đang hiển thị và vị trí của nó.
     * Gọi từ giao diện khi người có quyền bật "Chế độ dịch" hoặc khi dựng menu.
     */
    public function collect(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'max:500'],
            'items.*.source' => ['required', 'string', 'max:1000'],
            'items.*.area' => ['required', 'string', 'max:255'],
            'items.*.route' => ['nullable', 'string', 'max:255'],
            'items.*.sort' => ['nullable', 'integer'],
        ]);

        $count = 0;
        foreach ($data['items'] as $item) {
            $entry = $this->translations->findOrCreateEntry($item['source']);
            if (! $entry) {
                continue;
            }
            $this->touchLocation($entry->id, $item['area'], $item['route'] ?? null, (int) ($item['sort'] ?? 0));
            $count++;
        }

        return response()->json(['success' => true, 'data' => ['collected' => $count]]);
    }

    /**
     * POST /i18n/admin/sync-defaults - nạp các bản dịch có sẵn trong code (frontend/src/utils/i18n.js).
     * Chỉ ghi vào default_value, không đè lên bản dịch khách sạn đã sửa.
     */
    public function syncDefaults(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'max:3000'],
            'items.*.source' => ['required', 'string', 'max:1000'],
            'items.*.key' => ['nullable', 'string', 'max:150'],
            'items.*.values' => ['nullable', 'array'],
        ]);

        $count = 0;
        DB::connection($this->connection())->transaction(function () use ($data, &$count) {
            foreach ($data['items'] as $item) {
                $entry = $this->translations->findOrCreateEntry($item['source']);
                if (! $entry) {
                    continue;
                }
                if (! empty($item['key']) && ! $entry->note) {
                    $entry->update(['note' => 'i18n: ' . $item['key']]);
                }
                foreach (($item['values'] ?? []) as $lang => $text) {
                    if (! is_string($text) || $text === '' || ! preg_match('/^[a-zA-Z-]{2,10}$/', (string) $lang)) {
                        continue;
                    }
                    $value = TranslationValue::firstOrNew(['entry_id' => $entry->id, 'lang_code' => $lang]);
                    if ($value->default_value !== $text) {
                        $value->default_value = $text;
                        $value->save();
                        $count++;
                    }
                }
            }
        });

        return response()->json(['success' => true, 'data' => ['updated' => $count]]);
    }

    /** GET /i18n/admin/export - file CSV (UTF-8) để dịch bằng Excel */
    public function export(Request $request)
    {
        $languages = Language::orderBy('sort_order')->orderBy('id')->pluck('code')->all();
        $area = (string) $request->query('area', '');

        $query = TranslationEntry::with(['values', 'locations'])->orderBy('id');
        if ($area !== '') {
            $query->whereHas('locations', fn ($q) => $q->where('area', $area)->orWhere('area', 'like', $this->escapeLike($area) . ' › %'));
        }

        return response()->streamDownload(function () use ($query, $languages) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(['id', 'source', 'location'], $languages));
            $query->chunk(500, function ($entries) use ($out, $languages) {
                foreach ($entries as $entry) {
                    $values = $entry->values->keyBy('lang_code');
                    $row = [$entry->id, $entry->source, $entry->locations->pluck('area')->first() ?? ''];
                    foreach ($languages as $code) {
                        $v = $values->get($code);
                        $row[] = $v ? ($v->value ?? $v->default_value ?? '') : '';
                    }
                    fputcsv($out, $row);
                }
            });
            fclose($out);
        }, 'ban-dich-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** POST /i18n/admin/import - nhận file CSV cùng định dạng export; ô trống = giữ nguyên */
    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:10240']]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle);
        if (! $header) {
            return response()->json(['success' => false, 'message' => 'File trống.'], 422);
        }
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $sourceCol = array_search('source', $header, true);
        if ($sourceCol === false) {
            fclose($handle);

            return response()->json(['success' => false, 'message' => 'File thiếu cột "source".'], 422);
        }

        $languages = Language::pluck('code')->all();
        $langCols = [];
        foreach ($header as $index => $name) {
            if (in_array($name, $languages, true)) {
                $langCols[$name] = $index;
            }
        }

        $updated = 0;
        DB::connection($this->connection())->transaction(function () use ($handle, $sourceCol, $langCols, $request, &$updated) {
            while (($row = fgetcsv($handle)) !== false) {
                $source = (string) ($row[$sourceCol] ?? '');
                if (trim($source) === '') {
                    continue;
                }
                $values = [];
                foreach ($langCols as $lang => $index) {
                    $text = trim((string) ($row[$index] ?? ''));
                    if ($text !== '') {
                        $values[$lang] = $text;
                    }
                }
                if (! $values) {
                    continue;
                }
                $entry = $this->translations->findOrCreateEntry($source);
                if ($entry) {
                    $updated += count($this->saveValues($entry, $values, $request, true));
                }
            }
        });
        fclose($handle);

        ActivityLogService::logBusiness([
            'action' => 'update', 'module' => 'SYSTEM', 'component' => 'Ngôn ngữ',
            'description' => "Import bản dịch từ file: {$updated} ô thay đổi",
        ], $request);

        return response()->json(['success' => true, 'data' => ['updated' => $updated]]);
    }

    // =====================================================================
    // QUẢN TRỊ DANH SÁCH NGÔN NGỮ
    // =====================================================================

    /** GET /i18n/admin/languages - gồm cả ngôn ngữ đang tắt */
    public function allLanguages()
    {
        return response()->json([
            'success' => true,
            'data' => Language::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    /** POST /i18n/admin/languages */
    public function storeLanguage(Request $request)
    {
        $data = $this->validateLanguage($request);
        $data['code'] = strtolower($data['code']);
        $data['sort_order'] ??= ((int) Language::max('sort_order')) + 1;
        $language = Language::create($data);

        ActivityLogService::logBusiness([
            'action' => 'create', 'module' => 'SYSTEM', 'component' => 'Ngôn ngữ',
            'description' => "Thêm ngôn ngữ {$language->name} ({$language->code})",
            'target_type' => 'Language', 'target_id' => $language->id, 'target_label' => $language->name,
        ], $request);

        return response()->json(['success' => true, 'data' => $language]);
    }

    /** PUT /i18n/admin/languages/{id} */
    public function updateLanguage(Request $request, int $id)
    {
        $language = Language::findOrFail($id);
        $data = $this->validateLanguage($request, $language);
        unset($data['code']); // Mã ngôn ngữ là khóa của bản dịch, không cho đổi
        if ($language->is_default) {
            $data['is_active'] = true;
        }
        $language->update($data);

        return response()->json(['success' => true, 'data' => $language]);
    }

    /** DELETE /i18n/admin/languages/{id} - xóa ngôn ngữ và toàn bộ bản dịch của nó */
    public function destroyLanguage(Request $request, int $id)
    {
        $language = Language::findOrFail($id);
        if ($language->is_default) {
            return response()->json(['success' => false, 'message' => 'Không thể xóa ngôn ngữ gốc của hệ thống.'], 422);
        }

        DB::connection($this->connection())->transaction(function () use ($language) {
            TranslationValue::where('lang_code', $language->code)->delete();
            $language->delete();
        });

        ActivityLogService::logBusiness([
            'action' => 'delete', 'module' => 'SYSTEM', 'component' => 'Ngôn ngữ',
            'description' => "Xóa ngôn ngữ {$language->name} ({$language->code})",
            'target_type' => 'Language', 'target_id' => $id, 'target_label' => $language->name,
        ], $request);

        return response()->json(['success' => true]);
    }

    // =====================================================================

    private function validateLanguage(Request $request, ?Language $language = null): array
    {
        return $request->validate([
            'code' => [$language ? 'sometimes' : 'required', 'string', 'regex:/^[a-zA-Z]{2,3}(-[a-zA-Z]{2,4})?$/',
                Rule::unique($this->connection() . '.languages', 'code')->ignore($language?->id)],
            'name' => ['required', 'string', 'max:60'],
            'flag' => ['nullable', 'string', 'max:16'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'code.regex' => 'Mã ngôn ngữ gồm 2-3 chữ cái, ví dụ: ja, km, ko, zh-CN.',
            'code.unique' => 'Mã ngôn ngữ này đã tồn tại.',
        ]);
    }

    /**
     * Ghi bản dịch cho một dòng. Giá trị null/'' = khôi phục về mặc định.
     * $skipEqualDefault: khi import, ô trùng bản dịch mặc định thì không coi là đã sửa.
     *
     * @return array<int, array{label: string, old: ?string, new: ?string}>
     */
    private function saveValues(TranslationEntry $entry, array $values, Request $request, bool $skipEqualDefault = false): array
    {
        $languages = Language::pluck('code')->all();
        $changes = [];

        foreach ($values as $lang => $text) {
            if (! in_array($lang, $languages, true)) {
                continue;
            }
            $text = is_string($text) ? trim($text) : null;
            $text = $text === '' ? null : $text;

            $value = TranslationValue::firstOrNew(['entry_id' => $entry->id, 'lang_code' => $lang]);
            if ($text !== null && ($text === $value->default_value || ($lang === $this->translations->sourceLanguage() && $text === $entry->source))) {
                $text = null; // Trùng mặc định → không lưu thành bản sửa
            }
            if ($value->exists && $value->value === $text) {
                continue;
            }
            if (! $value->exists && $text === null) {
                continue;
            }
            if ($skipEqualDefault && $text === null && $value->value === null) {
                continue;
            }

            $changes[] = ['label' => "{$entry->source} [{$lang}]", 'old' => $value->value, 'new' => $text];
            $value->value = $text;
            $value->updated_by = $request->user()?->id;
            $value->save();
        }

        return $changes;
    }

    private function touchLocation(int $entryId, string $area, ?string $route, int $sort): void
    {
        $area = mb_substr(TranslationService::normalize($area), 0, 255);
        $location = TranslationLocation::firstOrNew(['entry_id' => $entryId, 'area' => $area]);
        if ($route) {
            $location->route = mb_substr($route, 0, 255);
        }
        if (! $location->exists || $sort) {
            $location->sort_order = $sort;
        }
        $location->last_seen_at = now();
        $location->save();
    }

    private function presentEntry(TranslationEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'source' => $entry->source,
            'is_pattern' => $entry->is_pattern,
            'note' => $entry->note,
            'values' => $entry->values->mapWithKeys(fn (TranslationValue $v) => [
                $v->lang_code => ['value' => $v->value, 'default' => $v->default_value],
            ]),
            'locations' => $entry->locations->map(fn (TranslationLocation $l) => [
                'area' => $l->area,
                'route' => $l->route,
            ])->values(),
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function connection(): string
    {
        return config('database_domains.system_connection', 'mysql_system');
    }
}
