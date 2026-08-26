<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrintTemplateSlot;
use App\Models\Template;
use App\Services\Reports\ReportDataExecutorService;
use App\Services\TemplateRendererService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PrintTemplateSlotController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => PrintTemplateSlot::with('template.reportDataSource')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, PrintTemplateSlot $printTemplateSlot)
    {
        $validated = $request->validate([
            'template_id' => 'nullable|integer|exists:templates,id',
        ]);

        if (! empty($validated['template_id'])) {
            $template = Template::findOrFail($validated['template_id']);
            if ($template->group !== $printTemplateSlot->group) {
                throw ValidationException::withMessages([
                    'template_id' => 'Thiết kế được chọn không thuộc đúng nhóm mẫu in.',
                ]);
            }
        }

        $printTemplateSlot->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật thiết kế sử dụng cho mẫu in.',
            'data' => $printTemplateSlot->fresh()->load('template.reportDataSource'),
        ]);
    }

    public function render(
        Request $request,
        PrintTemplateSlot $printTemplateSlot,
        TemplateRendererService $renderer,
        ReportDataExecutorService $executor
    ) {
        $validated = $request->validate([
            'data' => 'nullable|array',
            'parameters' => 'nullable|array',
        ]);
        $template = $printTemplateSlot->template()->with('reportDataSource')->first();

        if (! $template) {
            throw ValidationException::withMessages([
                'template_id' => 'Vị trí mẫu in này chưa được chọn thiết kế.',
            ]);
        }

        $data = $validated['data'] ?? null;
        if ($data === null && $template->reportDataSource) {
            $data = $executor->executeSource(
                $template->reportDataSource,
                $validated['parameters'] ?? $template->parameter_defaults ?? []
            );
        }

        if ($data === null) {
            $data = $renderer->getMockData($template->group, $template->name);
        }

        return response()->json([
            'success' => true,
            'slot' => $printTemplateSlot->only(['id', 'code', 'group', 'name']),
            'template' => $template->only(['id', 'name', 'version']),
            'html' => $renderer->render(
                $template->content_html ?? '',
                $template->css ?? '',
                $data,
                [
                    'page_size' => $template->page_size,
                    'page_orientation' => $template->page_orientation,
                    'margin_top' => $template->margin_top,
                    'margin_bottom' => $template->margin_bottom,
                    'margin_left' => $template->margin_left,
                    'margin_right' => $template->margin_right,
                ]
            ),
        ]);
    }
}
