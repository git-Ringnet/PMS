<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Services\TemplateRendererService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Order by group, then default templates first, then by name
        $templates = Template::orderBy('group')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => TemplateResource::collection($templates)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'group' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'report' => 'nullable|string|max:255',
            'page_size' => 'nullable|string|max:50',
            'page_orientation' => 'nullable|string|max:50',
            'margin_top' => 'nullable|integer|min:0',
            'margin_bottom' => 'nullable|integer|min:0',
            'margin_left' => 'nullable|integer|min:0',
            'margin_right' => 'nullable|integer|min:0',
            'content_json' => 'nullable|array',
            'content_html' => 'nullable|string',
            'css' => 'nullable|string',
        ]);

        // Default values for a banded template if none provided
        if (empty($validated['content_json'])) {
            $validated['content_json'] = [
                'header' => [
                    ['id' => 'h1', 'type' => 'text', 'content' => 'MẪU IN KHÁCH SẠN', 'style' => ['textAlign' => 'center', 'fontSize' => '22px', 'fontWeight' => 'bold', 'marginBottom' => '15px', 'whiteSpace' => 'pre-wrap']]
                ],
                'detail' => [
                    ['id' => 'd1', 'type' => 'text', 'content' => 'Chi tiết giao dịch...', 'style' => ['whiteSpace' => 'pre-wrap']]
                ],
                'footer' => [
                    ['id' => 'f1', 'type' => 'text', 'content' => "Người lập phiếu\n\n\nLễ tân", 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold', 'whiteSpace' => 'pre-wrap']]
                ]
            ];
            
            // Build a very simple default HTML matching the json
            $validated['content_html'] = '<div id="h1" style="text-align: center; font-size: 22px; font-weight: bold; margin-bottom: 15px; white-space: pre-wrap;">MẪU IN KHÁCH SẠN</div>'
                . '<div id="d1" style="white-space: pre-wrap;">Chi tiết giao dịch...</div>'
                . '<div id="f1" style="text-align: right; font-weight: bold; white-space: pre-wrap;">Người lập phiếu' . "\n\n\n" . 'Lễ tân</div>';
        }

        return DB::transaction(function () use ($validated) {
            $template = Template::create($validated);

            // Check if it's the first template in the group, set as default if so
            $groupCount = Template::where('group', $template->group)->count();
            if ($groupCount === 1) {
                $template->update(['is_default' => true]);
            }

            // Create initial version 1.0 record
            TemplateVersion::create([
                'template_id' => $template->id,
                'version' => '1.0',
                'content_json' => $template->content_json,
                'content_html' => $template->content_html,
                'css' => $template->css,
                'note' => 'Khởi tạo mẫu biểu',
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => new TemplateResource($template)
            ], 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $template = Template::find($id);
        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new TemplateResource($template)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $template = Template::find($id);
        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        $validated = $request->validate([
            'group' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'report' => 'nullable|string|max:255',
            'page_size' => 'nullable|string|max:50',
            'page_orientation' => 'nullable|string|max:50',
            'margin_top' => 'nullable|integer|min:0',
            'margin_bottom' => 'nullable|integer|min:0',
            'margin_left' => 'nullable|integer|min:0',
            'margin_right' => 'nullable|integer|min:0',
            'content_json' => 'nullable|array',
            'content_html' => 'nullable|string',
            'css' => 'nullable|string',
            'note' => 'nullable|string|max:255', // Note for version change
        ]);

        return DB::transaction(function () use ($validated, $template) {
            // Check if editor content actually changed
            $contentChanged = false;
            
            // Compare arrays for content_json, and strings for content_html / css
            $oldJson = json_encode($template->content_json);
            $newJson = json_encode($validated['content_json'] ?? []);
            
            if ($oldJson !== $newJson || 
                $template->content_html !== ($validated['content_html'] ?? '') || 
                $template->css !== ($validated['css'] ?? '') ||
                $template->page_size !== ($validated['page_size'] ?? 'A4') ||
                $template->page_orientation !== ($validated['page_orientation'] ?? 'portrait') ||
                $template->margin_top !== ($validated['margin_top'] ?? 10) ||
                $template->margin_bottom !== ($validated['margin_bottom'] ?? 10) ||
                $template->margin_left !== ($validated['margin_left'] ?? 10) ||
                $template->margin_right !== ($validated['margin_right'] ?? 10) ||
                (isset($validated['note']) && $validated['note'] !== 'Bản nháp tự động')
            ) {
                $contentChanged = true;
            }

            if ($contentChanged) {
                // Increment version (e.g. 1.0 -> 1.1)
                $currentVer = (float)$template->version;
                $nextVer = number_format($currentVer + 0.1, 1);
                $validated['version'] = (string)$nextVer;
            }

            $template->update($validated);

            if ($contentChanged) {
                // Save new version entry
                TemplateVersion::create([
                    'template_id' => $template->id,
                    'version' => $template->version,
                    'content_json' => $template->content_json,
                    'content_html' => $template->content_html,
                    'css' => $template->css,
                    'note' => $validated['note'] ?? 'Cập nhật mẫu biểu',
                    'updated_by' => auth()->id(),
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => new TemplateResource($template)
            ]);
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $template = Template::find($id);
        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        // Cannot delete default template
        if ($template->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa mẫu đang kích hoạt mặc định. Hãy chọn mẫu khác làm mặc định trước.'
            ], 400);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa mẫu biểu thành công'
        ]);
    }

    /**
     * Duplicate a template.
     */
    public function duplicate($id)
    {
        $template = Template::find($id);
        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        return DB::transaction(function () use ($template) {
            $newTemplate = $template->replicate();
            $newTemplate->name = $template->name . ' - Sao chép';
            $newTemplate->is_default = false;
            $newTemplate->version = '1.0';
            $newTemplate->save();

            // Create initial version
            TemplateVersion::create([
                'template_id' => $newTemplate->id,
                'version' => '1.0',
                'content_json' => $newTemplate->content_json,
                'content_html' => $newTemplate->content_html,
                'css' => $newTemplate->css,
                'note' => 'Bản sao từ mẫu ' . $template->name,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sao chép mẫu biểu thành công',
                'data' => new TemplateResource($newTemplate)
            ]);
        });
    }

    /**
     * Set template as default for its group.
     */
    public function makeDefault($id)
    {
        $template = Template::find($id);
        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        return DB::transaction(function () use ($template) {
            // Set all templates in the same group to is_default = false
            Template::where('group', $template->group)
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);

            $template->update(['is_default' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Kích hoạt mẫu mặc định thành công',
                'data' => new TemplateResource($template)
            ]);
        });
    }

    /**
     * Remove default status from a template in its group.
     */
    public function removeDefault($id)
    {
        $template = Template::find($id);
        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        if (!$template->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Mẫu biểu này hiện không phải mặc định.'
            ], 400);
        }

        $template->update(['is_default' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Đã hủy mẫu mặc định thành công',
            'data' => new TemplateResource($template->fresh())
        ]);
    }

    /**
     * Retrieve the version history for a template.
     */
    public function versions($id)
    {
        $template = Template::find($id);
        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        $versions = $template->versions()->with('updater:id,name')->get();

        return response()->json([
            'success' => true,
            'data' => $versions
        ]);
    }

    /**
     * Rollback the template to a historical version.
     */
    public function rollback(Request $request, $id)
    {
        $template = Template::find($id);
        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        $request->validate([
            'version_id' => 'required|exists:template_versions,id',
        ]);

        $version = TemplateVersion::where('template_id', $template->id)
            ->where('id', $request->version_id)
            ->first();

        if (!$version) {
            return response()->json(['message' => 'Version not found for this template'], 404);
        }

        return DB::transaction(function () use ($template, $version) {
            // Increment major version or compute rollback version
            $currentVer = (float)$template->version;
            $nextVer = number_format($currentVer + 1.0, 1); // Rollback increments major version

            $template->update([
                'content_json' => $version->content_json,
                'content_html' => $version->content_html,
                'css' => $version->css,
                'version' => (string)$nextVer,
            ]);

            // Save new version entry for the rollback action itself
            TemplateVersion::create([
                'template_id' => $template->id,
                'version' => $template->version,
                'content_json' => $template->content_json,
                'content_html' => $template->content_html,
                'css' => $template->css,
                'note' => 'Khôi phục về phiên bản ' . $version->version,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Khôi phục phiên bản thành công',
                'data' => new TemplateResource($template)
            ]);
        });
    }

    /**
     * Preview the template using mock data.
     */
    public function preview($id)
    {
        $template = Template::find($id);
        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        // Đọc template liên kết qua trường report nếu có
        if ($template->report) {
            $linkedTemplate = Template::where('report', $template->report)->first();
            if ($linkedTemplate && $linkedTemplate->id !== $template->id) {
                $template = $linkedTemplate;
            }
        }

        $renderer = app(TemplateRendererService::class);
        $mockData = $renderer->getMockData($template->group, $template->name);
        
        $html = $renderer->render(
            $template->content_html ?? '', 
            $template->css ?? '', 
            $mockData,
            [
                'page_size' => $template->page_size,
                'page_orientation' => $template->page_orientation,
                'margin_top' => $template->margin_top,
                'margin_bottom' => $template->margin_bottom,
                'margin_left' => $template->margin_left,
                'margin_right' => $template->margin_right,
            ]
        );

        return response()->json([
            'success' => true,
            'html' => $html
        ]);
    }

    /**
     * Render the template with actual payload data or fallback to mock data.
     */
    public function render(Request $request, $id)
    {
        $template = Template::find($id);
        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        // Đọc template liên kết qua trường report nếu có
        if ($template->report) {
            $linkedTemplate = Template::where('report', $template->report)->first();
            if ($linkedTemplate && $linkedTemplate->id !== $template->id) {
                $template = $linkedTemplate;
            }
        }

        $renderer = app(TemplateRendererService::class);
        
        // Accept payload directly from frontend, fallback to mock data if empty
        $data = $request->input('data');
        if (empty($data) || !is_array($data)) {
            $data = $renderer->getMockData($template->group, $template->name);
        }

        $html = $renderer->render(
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
        );

        return response()->json([
            'success' => true,
            'html' => $html
        ]);
    }

    /**
     * Upload an image to use in templates.
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120', // maximum 5MB
        ], [
            'image.required' => 'Vui lòng chọn một tệp hình ảnh.',
            'image.image' => 'Tệp tải lên phải là hình ảnh.',
            'image.max' => 'Hình ảnh tối đa không quá 5MB.',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = 'template_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
            $file->move(public_path('uploads/templates'), $filename);
            
            $url = '/uploads/templates/' . $filename;
            
            return response()->json([
                'success' => true,
                'url' => $url,
                'message' => 'Tải ảnh lên thành công!'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Không tìm thấy tệp tải lên.'
        ], 400);
    }
}

