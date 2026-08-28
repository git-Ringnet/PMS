<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelSetting;
use App\Models\ReportDefinition;
use App\Models\Template;
use App\Services\Reports\ReportDatasetEnricher;
use App\Services\Reports\ReportDataExecutorService;
use App\Services\Reports\ReportExportService;
use App\Services\RoomAvailabilityService;
use App\Services\TemplateRendererService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class ReportDefinitionController extends Controller
{
    public function __construct(
        private readonly ReportDataExecutorService $executor,
        private readonly TemplateRendererService $renderer,
        private readonly ReportDatasetEnricher $datasetEnricher,
        private readonly ReportExportService $exporter,
        private readonly RoomAvailabilityService $roomAvailability
    ) {}

    public function index(Request $request)
    {
        $query = ReportDefinition::with(['reportDataSource', 'templates']);

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        $reports = $query->orderBy('group')->orderBy('sort_order')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $reports->map(fn (ReportDefinition $report) => $this->serialize($report)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $report = DB::transaction(function () use ($validated) {
            $report = ReportDefinition::create($this->definitionAttributes($validated));
            $this->syncTemplates($report, $validated['template_ids'], $validated['default_template_id'] ?? null);

            return $report;
        });

        return response()->json([
            'success' => true,
            'data' => $this->serialize($report->load(['reportDataSource', 'templates'])),
        ], 201);
    }

    public function show(ReportDefinition $reportDefinition)
    {
        return response()->json([
            'success' => true,
            'data' => $this->serialize($reportDefinition->load(['reportDataSource', 'templates'])),
        ]);
    }

    public function update(Request $request, ReportDefinition $reportDefinition)
    {
        $validated = $this->validatePayload($request, $reportDefinition);

        DB::transaction(function () use ($validated, $reportDefinition) {
            $reportDefinition->update($this->definitionAttributes($validated));
            $this->syncTemplates(
                $reportDefinition,
                $validated['template_ids'],
                $validated['default_template_id'] ?? null
            );
        });

        return response()->json([
            'success' => true,
            'data' => $this->serialize($reportDefinition->fresh()->load(['reportDataSource', 'templates'])),
        ]);
    }

    public function destroy(ReportDefinition $reportDefinition)
    {
        $reportDefinition->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa định nghĩa báo cáo.']);
    }

    public function execute(Request $request, ReportDefinition $reportDefinition)
    {
        $validated = $request->validate([
            'parameters' => 'nullable|array',
            'template_id' => 'nullable|integer|exists:templates,id',
        ]);

        try {
            $template = $this->resolveTemplate($reportDefinition, $validated['template_id'] ?? null);
            $data = $this->executor->executeSource(
                $reportDefinition->reportDataSource,
                $validated['parameters'] ?? []
            );
            $data['report'] = [
                'code' => $reportDefinition->code,
                'name' => $reportDefinition->name,
                'generated_at' => $this->systemReportTimestamp(),
                'generated_by' => $request->user()?->name ?? $request->user()?->username ?? '',
            ];
            $data['hotel'] = $this->hotelContext();

            $data = $this->datasetEnricher->enrich($reportDefinition, $data);

            if (isset($data['parameters']) && is_array($data['parameters'])) {
                foreach ($data['parameters'] as $key => $val) {
                    if (is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                        $data['parameters'][$key] = \Carbon\Carbon::parse($val)->format('d/m/Y');
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'template' => $this->templateSummary($template),
                'html' => $this->renderTemplate($template, $data),
            ]);
        } catch (InvalidArgumentException|RuntimeException|ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function render(Request $request, ReportDefinition $reportDefinition)
    {
        $validated = $request->validate([
            'template_id' => 'required|integer|exists:templates,id',
            'data' => 'required|array',
        ]);

        try {
            $template = $this->resolveTemplate($reportDefinition, $validated['template_id']);
            $reportData = $validated['data'];

            $reportData = $this->datasetEnricher->enrich($reportDefinition, $reportData);

            if (isset($reportData['parameters']) && is_array($reportData['parameters'])) {
                foreach ($reportData['parameters'] as $key => $val) {
                    if (is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                        $reportData['parameters'][$key] = \Carbon\Carbon::parse($val)->format('d/m/Y');
                    }
                }
            }

            return response()->json([
                'success' => true,
                'template' => $this->templateSummary($template),
                'html' => $this->renderTemplate($template, $reportData),
            ]);
        } catch (ValidationException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function export(Request $request, ReportDefinition $reportDefinition)
    {
        $validated = $request->validate([
            'parameters' => 'nullable|array',
            'template_id' => 'nullable|integer|exists:templates,id',
            'format' => 'required|in:pdf,xlsx,docx',
        ]);

        try {
            $template = $this->resolveTemplate($reportDefinition, $validated['template_id'] ?? null);
            $data = $this->prepareReportData($reportDefinition, $validated['parameters'] ?? [], $request);
            $html = $this->renderTemplate($template, $data);

            return $this->exporter->download($validated['format'], $template, $data, $html, $reportDefinition->code);
        } catch (InvalidArgumentException|RuntimeException|ValidationException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    private function systemReportTimestamp(): string
    {
        $systemDate = $this->roomAvailability->getSystemDate();
        $time = now()->timezone('Asia/Ho_Chi_Minh')->format('H:i:s');

        return $systemDate->format('d/m/Y').' '.$time;
    }

    private function prepareReportData(ReportDefinition $reportDefinition, array $parameters, Request $request): array
    {
        $data = $this->executor->executeSource($reportDefinition->reportDataSource, $parameters);
        $data['report'] = [
            'code' => $reportDefinition->code,
            'name' => $reportDefinition->name,
            'generated_at' => $this->systemReportTimestamp(),
            'generated_by' => $request->user()?->name ?? $request->user()?->username ?? '',
        ];
        $data['hotel'] = $this->hotelContext();
        $data = $this->datasetEnricher->enrich($reportDefinition, $data);

        if (isset($data['parameters']) && is_array($data['parameters'])) {
            foreach ($data['parameters'] as $key => $value) {
                if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    $data['parameters'][$key] = \Carbon\Carbon::parse($value)->format('d/m/Y');
                }
            }
        }

        return $data;
    }

    private function validatePayload(Request $request, ?ReportDefinition $report = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:100', 'regex:/^[A-Z][A-Z0-9_]*$/',
                Rule::unique('report_definitions', 'code')->ignore($report?->id),
            ],
            'name' => 'required|string|max:255',
            'group' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'report_data_source_id' => 'required|exists:report_data_sources,id',
            'parameter_ui_schema' => 'nullable|array',
            'parameter_ui_schema.*.name' => 'required|string|max:128',
            'parameter_ui_schema.*.label' => 'nullable|string|max:255',
            'parameter_ui_schema.*.control' => 'nullable|in:text,number,date,date-range,datetime-local,select,radio,checkbox,hidden',
            'parameter_ui_schema.*.required' => 'nullable|boolean',
            'parameter_ui_schema.*.default' => 'nullable',
            'parameter_ui_schema.*.options' => 'nullable|array',
            'parameter_ui_schema.*.options_source' => 'nullable|string|in:areas,companies,bookings,room-classes,registration-statuses',
            'parameter_ui_schema.*.range_end_parameter' => 'nullable|string|max:128',
            'template_ids' => 'required|array|min:1',
            'template_ids.*' => 'integer|distinct|exists:templates,id',
            'default_template_id' => 'nullable|integer|exists:templates,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'show_in_menu' => 'sometimes|boolean',
            'menu_locations' => 'nullable|array',
            'menu_locations.*' => 'string|in:reservation,frontdesk,housekeeping',
            'menu_top_order' => 'nullable|integer|min:0|max:999',
            'menu_group_order' => 'nullable|integer|min:0|max:999',
            'menu_item_order' => 'nullable|integer|min:0|max:999',
        ]);

        if (! empty($validated['default_template_id'])
            && ! in_array($validated['default_template_id'], $validated['template_ids'], true)) {
            throw ValidationException::withMessages([
                'default_template_id' => 'Mẫu mặc định phải nằm trong danh sách mẫu đã gán.',
            ]);
        }

        return $validated;
    }

    private function definitionAttributes(array $validated): array
    {
        return collect($validated)->only([
            'code', 'name', 'group', 'description', 'report_data_source_id',
            'parameter_ui_schema', 'sort_order', 'is_active',
            'show_in_menu', 'menu_locations', 'menu_top_order',
            'menu_group_order', 'menu_item_order',
        ])->all();
    }

    private function syncTemplates(ReportDefinition $report, array $templateIds, ?int $defaultTemplateId): void
    {
        $defaultTemplateId ??= (int) $templateIds[0];
        $sync = [];
        foreach (array_values($templateIds) as $index => $templateId) {
            $sync[$templateId] = [
                'is_default' => (int) $templateId === $defaultTemplateId,
                'sort_order' => $index,
            ];
        }
        $report->templates()->sync($sync);
    }

    private function resolveTemplate(ReportDefinition $report, ?int $templateId): Template
    {
        $templates = $report->templates()->get();
        $template = $templateId
            ? $templates->firstWhere('id', $templateId)
            : ($templates->first(fn (Template $item) => (bool) $item->pivot->is_default) ?? $templates->first());

        if (! $template) {
            throw ValidationException::withMessages(['template_id' => 'Báo cáo chưa có mẫu đầu ra hợp lệ.']);
        }

        return $template;
    }

    private function renderTemplate(Template $template, array $data): string
    {
        $css = $template->css ?? '';
        // Keep the room-type summary aligned with the main report table even
        // for templates saved before the full-width rule was introduced.
        if (isset($data['room_type_summary'])) {
            // Older saved templates contain `max-width: 600px` in the
            // .summary-table rule. Remove that declaration instead of merely
            // overriding it, so the table has no hidden width constraint.
            $css = preg_replace_callback('/(\.summary-table\s*\{)([^}]*)\}/i', function (array $matches) {
                $rules = preg_replace('/\s*max-width\s*:\s*[^;]+;?/i', '', $matches[2]);

                return $matches[1].$rules.'}';
            }, $css) ?? $css;
            $css .= '\n.summary-table { width: 100% !important; margin-left: 0 !important; margin-right: 0 !important; }';
        }

        return $this->renderer->render(
            $template->content_html ?? '',
            $css,
            $data,
            [
                'page_size' => $template->page_size,
                'page_orientation' => $template->page_orientation,
                'margin_top' => $template->margin_top,
                'margin_bottom' => $template->margin_bottom,
                'margin_left' => $template->margin_left,
                'margin_right' => $template->margin_right,
            ]
        );
    }

    private function hotelContext(): array
    {
        $hotel = HotelSetting::first();
        $logoUrl = $hotel?->logo_url ?: $hotel?->logo;

        return [
            'name' => $hotel?->hotel_name ?? '',
            'address' => $hotel?->address ?? '',
            'phone' => $hotel?->phone ?? '',
            'email' => $hotel?->email ?? '',
            'logo' => $logoUrl
                ? '<img src="'.$logoUrl.'" alt="Logo" class="hotel-logo-image">'
                : '<div class="hotel-logo-fallback">PMS</div>',
        ];
    }

    private function serialize(ReportDefinition $report): array
    {
        $source = $report->reportDataSource;
        $configured = collect($report->parameter_ui_schema ?? [])->keyBy('name');
        $parameterSchema = collect($source?->parameter_schema ?? [])->map(function (array $parameter) use ($configured) {
            $ui = $configured->get($parameter['name'], []);

            return [
                ...$parameter,
                'label' => $ui['label'] ?? $parameter['name'],
                'control' => $ui['control'] ?? $this->defaultControl($parameter['data_type'] ?? ''),
                'default' => $ui['default'] ?? null,
                'options' => $ui['options'] ?? [],
                'options_source' => $ui['options_source'] ?? null,
                'range_end_parameter' => $ui['range_end_parameter'] ?? null,
                'required' => $ui['required'] ?? ($parameter['required'] ?? true),
            ];
        })->values()->all();

        return [
            'id' => $report->id,
            'code' => $report->code,
            'name' => $report->name,
            'group' => $report->group,
            'description' => $report->description,
            'report_data_source_id' => $report->report_data_source_id,
            'report_data_source' => $source,
            'parameter_ui_schema' => $parameterSchema,
            'sort_order' => $report->sort_order,
            'is_active' => $report->is_active,
            'show_in_menu' => $report->show_in_menu,
            'menu_locations' => $report->menu_locations ?? ['reservation'],
            'menu_top_order' => $report->menu_top_order,
            'menu_group_order' => $report->menu_group_order,
            'menu_item_order' => $report->menu_item_order,
            'templates' => $report->templates->map(fn (Template $template) => $this->templateSummary($template))->values(),
            'created_at' => $report->created_at,
            'updated_at' => $report->updated_at,
        ];
    }

    private function templateSummary(Template $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'group' => $template->group,
            'version' => $template->version,
            'page_size' => $template->page_size,
            'page_orientation' => $template->page_orientation,
            'is_default' => (bool) ($template->pivot?->is_default ?? false),
        ];
    }

    private function defaultControl(string $dataType): string
    {
        return match (strtolower($dataType)) {
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime-local',
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint',
            'decimal', 'numeric', 'float', 'double' => 'number',
            default => 'text',
        };
    }
}
