<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomFormResource;
use App\Models\RoomForm;

class RoomFormController extends Controller
{
    /**
     * Display a listing of the room forms.
     */
    public function index()
    {
        $forms = RoomForm::all();
        return RoomFormResource::collection($forms);
    }

    /**
     * Store a newly created room form in storage.
     */
    public function store(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:room_forms,name',
            'max_adults' => 'required|integer|min:0',
        ]);

        $form = RoomForm::create($validated);

        return new RoomFormResource($form);
    }

    /**
     * Update the specified room form in storage.
     */
    public function update(\Illuminate\Http\Request $request, $id)
    {
        $form = RoomForm::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:room_forms,name,' . $form->id,
            'max_adults' => 'required|integer|min:0',
        ]);

        $form->update($validated);

        return new RoomFormResource($form);
    }

    /**
     * Remove the specified room form from storage.
     */
    public function destroy($id)
    {
        $form = RoomForm::findOrFail($id);

        $hasRates = $form->standardRates()->exists();
        $hasRooms = $form->rooms()->exists();
        if ($hasRates || $hasRooms) {
            return response()->json([
                'message' => 'Không thể xóa dạng phòng này vì đang có giá phòng chuẩn hoặc phòng đang sử dụng dạng phòng này!'
            ], 422);
        }

        $form->delete();

        return response()->json(['message' => 'Xóa dạng phòng thành công!']);
    }
}
