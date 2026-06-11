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
}
