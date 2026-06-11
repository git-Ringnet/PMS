<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StandardRateResource;
use App\Models\StandardRate;
use Illuminate\Http\Request;

class StandardRateController extends Controller
{
    /**
     * Display a listing of the standard rates.
     */
    public function index()
    {
        $rates = StandardRate::with(['roomClass', 'roomForm'])->get();
        return StandardRateResource::collection($rates);
    }
}
