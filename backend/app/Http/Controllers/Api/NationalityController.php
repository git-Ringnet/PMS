<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Nationality;
use Illuminate\Http\Request;

class NationalityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Get all nationalities except placeholder/non-country codes (---, UNO, NGUOCNGOAI)
        // Order by placing 'VN' on top, then alphabetical by English name
        $nationalities = Nationality::query()
            ->where(function ($query) {
                $query->whereNotIn('nationality_id', ['---', 'UNO'])
                      ->orWhereNull('nationality_id');
            })
            ->where(function ($query) {
                $query->whereNotIn('asm_code', ['NGUOCNGOAI'])
                      ->orWhereNull('asm_code');
            })
            ->orderByRaw("CASE WHEN asm_code = 'VN' THEN 0 ELSE 1 END")
            ->orderByRaw("COALESCE(nationality_name_en, asm_description) ASC")
            ->get();

        return response()->json([
            'success' => true,
            'data' => $nationalities
        ]);
    }
}
