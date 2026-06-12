<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $templates = Template::all();
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
        ]);

        $template = Template::create($validated);

        return response()->json([
            'success' => true,
            'data' => new TemplateResource($template)
        ], 201);
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
        ]);

        $template->update($validated);

        return response()->json([
            'success' => true,
            'data' => new TemplateResource($template)
        ]);
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

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully'
        ]);
    }
}
