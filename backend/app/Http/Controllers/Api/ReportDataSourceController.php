<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportDataSource;
use App\Services\Reports\ReportDataExecutorService;
use App\Services\Reports\ReportProcedureCatalogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

class ReportDataSourceController extends Controller
{
    public function __construct(
        private readonly ReportProcedureCatalogService $catalog,
        private readonly ReportDataExecutorService $executor
    ) {}

    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => ReportDataSource::orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        try {
            $metadata = $this->catalog->describe($validated['object_name']);
            $result = null;
            if (array_key_exists('sample_parameters', $validated)) {
                $result = $this->executor->executeProcedure(
                    $validated['object_name'],
                    $metadata['parameters'],
                    $validated['sample_parameters'] ?? [],
                    $validated['max_rows'] ?? null
                );
            }
            $source = ReportDataSource::create([
                ...$validated,
                'source_type' => 'procedure',
                'schema_name' => $metadata['schema_name'],
                'parameter_schema' => $metadata['parameters'],
                'field_schema' => $result['fields'] ?? [],
                'last_discovered_at' => now(),
            ]);

            return response()->json(['success' => true, 'data' => $source->fresh()], 201);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function show(ReportDataSource $reportDataSource)
    {
        return response()->json(['success' => true, 'data' => $reportDataSource]);
    }

    public function update(Request $request, ReportDataSource $reportDataSource)
    {
        $validated = $this->validatePayload($request, $reportDataSource);

        try {
            $metadata = $this->catalog->describe($validated['object_name']);
            $reportDataSource->update([
                ...$validated,
                'source_type' => 'procedure',
                'schema_name' => $metadata['schema_name'],
                'parameter_schema' => $metadata['parameters'],
                'last_discovered_at' => now(),
            ]);

            return response()->json(['success' => true, 'data' => $reportDataSource->fresh()]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function destroy(ReportDataSource $reportDataSource)
    {
        if ($reportDataSource->templates()->exists() || $reportDataSource->reportDefinitions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Nguồn dữ liệu đang được báo cáo hoặc template sử dụng và không thể xóa.',
            ], 409);
        }

        $reportDataSource->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa nguồn dữ liệu báo cáo.']);
    }

    public function sample(Request $request, ReportDataSource $reportDataSource)
    {
        $validated = $request->validate(['parameters' => 'nullable|array']);

        try {
            $result = $this->executor->executeSource(
                $reportDataSource,
                $validated['parameters'] ?? $reportDataSource->sample_parameters ?? []
            );

            return response()->json(['success' => true, 'data' => $result]);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể thực thi nguồn dữ liệu báo cáo. Vui lòng kiểm tra Store và tham số.',
            ], 422);
        }
    }

    public function refreshSchema(Request $request, ReportDataSource $reportDataSource)
    {
        $validated = $request->validate(['parameters' => 'nullable|array']);
        $parameters = $validated['parameters'] ?? $reportDataSource->sample_parameters ?? [];

        try {
            $previousFields = collect($reportDataSource->field_schema ?? [])->pluck('name')->filter()->values();
            $this->refreshSource($reportDataSource, $parameters);
            $currentFields = collect($reportDataSource->fresh()->field_schema ?? [])->pluck('name')->filter()->values();
            $removedFields = $previousFields->diff($currentFields)->values();
            $addedFields = $currentFields->diff($previousFields)->values();
            $affectedTemplates = $removedFields->isEmpty()
                ? collect()
                : $reportDataSource->templates()
                    ->get(['id', 'name', 'content_json'])
                    ->filter(function ($template) use ($removedFields) {
                        $content = json_encode($template->content_json);

                        return $removedFields->contains(fn ($field) => str_contains($content, 'row.'.$field));
                    })
                    ->map->only(['id', 'name'])
                    ->values();

            return response()->json([
                'success' => true,
                'data' => $reportDataSource->fresh(),
                'schema_changes' => [
                    'added_fields' => $addedFields,
                    'removed_fields' => $removedFields,
                    'affected_templates' => $affectedTemplates,
                ],
            ]);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    private function refreshSource(ReportDataSource $source, array $parameters): void
    {
        $metadata = $this->catalog->describe($source->object_name);
        $result = $this->executor->executeProcedure(
            $source->object_name,
            $metadata['parameters'],
            $parameters,
            $source->max_rows
        );
        $source->update([
            'schema_name' => $metadata['schema_name'],
            'parameter_schema' => $metadata['parameters'],
            'field_schema' => $result['fields'],
            'sample_parameters' => $parameters,
            'last_discovered_at' => now(),
        ]);
    }

    private function validatePayload(Request $request, ?ReportDataSource $source = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:100', 'regex:/^[A-Z][A-Z0-9_]*$/',
                Rule::unique('report_data_sources', 'code')->ignore($source?->id),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'object_name' => [
                'required', 'string', 'max:128',
                Rule::unique('report_data_sources', 'object_name')->ignore($source?->id),
            ],
            'sample_parameters' => 'nullable|array',
            'max_rows' => 'nullable|integer|min:1|max:'.config('reporting.maximum_max_rows', 5000),
            'is_active' => 'sometimes|boolean',
        ]);
    }
}
