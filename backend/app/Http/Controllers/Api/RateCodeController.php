<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RateCode;
use Illuminate\Http\Request;

class RateCodeController extends Controller
{
    public function index()
    {
        $rateCodes = RateCode::orderBy('code')->get();

        return response()->json([
            'success' => true,
            'data'    => $rateCodes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'               => 'required|string|max:20|unique:rate_codes,code',
            'description'        => 'nullable|string|max:150',
            'begin_date'         => 'required|date',
            'end_date'           => 'required|date|after_or_equal:begin_date',
            'include_bf'         => 'boolean',
            'currency'           => 'nullable|string|max:5',
            'type'               => 'nullable|string|max:10',
            'value'              => 'nullable|array',
            'disable'            => 'boolean',
            'allow_change_rate'  => 'boolean',
            'is_channel_manager' => 'boolean',
            'promotion_code'     => 'nullable|string|max:20',
            'source_code'        => 'nullable|integer',
            'market_segment'     => 'nullable|integer',
        ]);

        if (empty($validated['value'])) {
                $roomClasses = \App\Models\RoomClass::all();
                $roomForms   = \App\Models\RoomForm::all();

                $defaultValue = [];
                foreach ($roomClasses as $rc) {
                    foreach ($roomForms as $rf) {
                        $defaultValue[] = [
                            'RoomTypeId' => $rc->id,
                            'RoomKindId' => $rf->id,
                            'Price'      => 0.0,
                        ];
                    }
                }
                $validated['value'] = json_encode($defaultValue);
            }

        $rateCode = RateCode::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $rateCode,
        ], 201);
    }

    public function show($id)
    {
        $rateCode = RateCode::find($id);

        if (!$rateCode) {
            return response()->json([
                'success' => false,
                'message' => 'Rate code not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $rateCode,
        ]);
    }

    public function update(Request $request, $id)
    {
        $rateCode = RateCode::find($id);

        if (!$rateCode) {
            return response()->json([
                'success' => false,
                'message' => 'Rate code not found',
            ], 404);
        }

        $validated = $request->validate([
            'code'               => 'required|string|max:20|unique:rate_codes,code,' . $rateCode->id,
            'description'        => 'nullable|string|max:150',
            'begin_date'         => 'required|date',
            'end_date'           => 'required|date|after_or_equal:begin_date',
            'include_bf'         => 'boolean',
            'currency'           => 'nullable|string|max:5',
            'type'               => 'nullable|string|max:10',
            'value'              => 'nullable|array',
            'disable'            => 'boolean',
            'allow_change_rate'  => 'boolean',
            'is_channel_manager' => 'boolean',
            'promotion_code'     => 'nullable|string|max:20',
            'source_code'        => 'nullable|integer',
            'market_segment'     => 'nullable|integer',
        ]);

        $rateCode->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $rateCode,
        ]);
    }

    public function destroy($id)
    {
        $rateCode = RateCode::find($id);

        if (!$rateCode) {
            return response()->json([
                'success' => false,
                'message' => 'Rate code not found',
            ], 404);
        }

        $rateCode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rate code deleted successfully',
        ]);
    }

    public function toggle(Request $request, $rate_code)
    {
        $rateCode = RateCode::find($rate_code);

        if (!$rateCode) {
            return response()->json([
                'success' => false,
                'message' => 'Rate code not found',
            ], 404);
        }

        $validated = $request->validate([
            'field' => 'required|in:disable,include_bf,allow_change_rate,is_channel_manager',
        ]);

        $field            = $validated['field'];
        $rateCode->$field = !$rateCode->$field;
        $rateCode->save();

        return response()->json([
            'success' => true,
            'data'    => $rateCode,
        ]);
    }
}