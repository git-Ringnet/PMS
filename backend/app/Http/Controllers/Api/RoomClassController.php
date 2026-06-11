<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomClassResource;
use App\Models\RoomClass;

class RoomClassController extends Controller
{
    /**
     * Display a listing of the room classes.
     */
    public function index()
    {
        $classes = RoomClass::all();
        return RoomClassResource::collection($classes);
    }
}
