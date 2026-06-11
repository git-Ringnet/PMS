<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelSettingResource;
use App\Models\HotelSetting;
use Illuminate\Http\Request;

class HotelSettingController extends Controller
{
    /**
     * Display the hotel settings.
     */
    public function show()
    {
        $setting = HotelSetting::first();
        if (!$setting) {
            return response()->json(['message' => 'Hotel settings not found'], 404);
        }
        return new HotelSettingResource($setting);
    }

    /**
     * Update the hotel settings.
     */
    public function update(Request $request)
    {
        $setting = HotelSetting::first();
        if (!$setting) {
            $setting = new HotelSetting();
        }

        $validated = $request->validate([
            'code' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'tax_code' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'facebook' => 'nullable|string|max:255',
            'channel_manager' => 'nullable|string|max:100',
            'currency' => 'nullable|string|max:10',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'adult_breakfast_price' => 'nullable|numeric|min:0',
            'child_breakfast_price' => 'nullable|numeric|min:0',
            'extra_bed_price' => 'nullable|numeric|min:0',
            'total_rooms' => 'nullable|integer|min:0',
            'website' => 'nullable|string|max:255',
            'booking_prefix' => 'nullable|string|max:50',
            'logo_url' => 'nullable|string|max:255',
            'qr_code_url' => 'nullable|string|max:255',
        ]);

        $setting->fill($validated);
        $setting->save();

        return new HotelSettingResource($setting);
    }
}
