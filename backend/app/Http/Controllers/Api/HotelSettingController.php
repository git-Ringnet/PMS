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
        $resource = new HotelSettingResource($setting);
        $data = $resource->toArray(request());
        
        $configs = \App\Models\HotelConfig::whereIn('name', [
            'ColorDefaultBookingRoomMap',
            'RoomPlan_ColorRoomReservation',
            'RoomPlan_ColorRoomInhouse',
            'RoomPlan_ColorRoomLateCheckout',
            'RoomPlan_ColorOOO',
            'RoomPlan_ColorOOS'
        ])->get()->pluck('value', 'name');

        $data['ColorDefaultBookingRoomMap'] = $configs->get('ColorDefaultBookingRoomMap', '#97D5FF');
        $data['RoomPlan_ColorRoomReservation'] = $configs->get('RoomPlan_ColorRoomReservation', '#E3E8C4');
        $data['RoomPlan_ColorRoomInhouse'] = $configs->get('RoomPlan_ColorRoomInhouse', '#4a90e2');
        $data['RoomPlan_ColorRoomLateCheckout'] = $configs->get('RoomPlan_ColorRoomLateCheckout', '#FCF55F');
        $data['RoomPlan_ColorOOO'] = $configs->get('RoomPlan_ColorOOO', '#107eeb');
        $data['RoomPlan_ColorOOS'] = $configs->get('RoomPlan_ColorOOS', '#107eeb');
        
        $bfConfig = \App\Models\HotelConfig::where('name', 'DefaultBreakfast')->first();
        $data['DefaultBreakfast'] = $bfConfig ? intval($bfConfig->value) : 1;
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
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
            'first_name' => 'nullable|string|max:255',
            'hotel_name' => 'required|string|max:255',
            'hotel_name1' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'address1' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'website' => 'nullable|string|max:255',
            'account' => 'nullable|string|max:50',
            'bank_code' => 'nullable|string|max:50',
            'bank' => 'nullable|string|max:255',
            'tax_code' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:255',
            'serial' => 'nullable|string|max:100',
            'invoice_number' => 'nullable|string|max:100',
            'invoice_number_length' => 'nullable|integer|min:0',
            'form_no' => 'nullable|string|max:100',
            'logo' => 'nullable|string|max:255',
            'invoice_address' => 'nullable|string|max:1000',
            'breakfast_adult_rate' => 'nullable|numeric|min:0',
            'breakfast_child_rate' => 'nullable|numeric|min:0',
            'extra_bed_rate' => 'nullable|numeric|min:0',
            'room_number' => 'nullable|integer|min:0',
            'division' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'prefix_booking_id' => 'nullable|string|max:50',
            'channel_manager' => 'nullable|string|max:100',
            'facebook' => 'nullable|string|max:255',
            'hotel_link' => 'nullable|string|max:255',
            'pos_serial' => 'nullable|string|max:100',
            'pos_invoice_number' => 'nullable|string|max:100',
            'pos_invoice_number_length' => 'nullable|integer|min:0',
            'pos_invoice_form_no' => 'nullable|string|max:100',
            'pos_invoice_symbol' => 'nullable|string|max:100',
            'logo_url' => 'nullable|string|max:255',
            'qr_code_url' => 'nullable|string|max:255',
        ], [
            'hotel_name.required' => 'Tên khách sạn không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
            'breakfast_adult_rate.numeric' => 'Giá ăn sáng người lớn phải là số.',
            'breakfast_child_rate.numeric' => 'Giá ăn sáng trẻ em phải là số.',
            'extra_bed_rate.numeric' => 'Giá thêm giường phải là số.',
            'room_number.integer' => 'Số phòng phải là số nguyên.',
        ]);

        $setting->fill($validated);
        $setting->save();

        return new HotelSettingResource($setting);
    }

    /**
     * Upload hotel logo.
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|max:2048',
        ]);

        $setting = HotelSetting::first();
        if (!$setting) {
            $setting = HotelSetting::create(['hotel_name' => 'Default']);
        }

        if ($request->hasFile('logo')) {
            // Remove old file
            if ($setting->logo_url && file_exists(public_path($setting->logo_url))) {
                @unlink(public_path($setting->logo_url));
            }

            $file = $request->file('logo');
            $filename = 'logo_' . time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/hotel'), $filename);
            
            $setting->logo_url = 'uploads/hotel/' . $filename;
            $setting->save();
        }

        return new HotelSettingResource($setting);
    }

    /**
     * Delete hotel logo.
     */
    public function deleteLogo()
    {
        $setting = HotelSetting::first();
        if ($setting) {
            if ($setting->logo_url && file_exists(public_path($setting->logo_url))) {
                @unlink(public_path($setting->logo_url));
            }
            $setting->logo_url = null;
            $setting->save();
        }

        return new HotelSettingResource($setting);
    }

    /**
     * Upload hotel QR code.
     */
    public function uploadQrCode(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|image|max:2048',
        ]);

        $setting = HotelSetting::first();
        if (!$setting) {
            $setting = HotelSetting::create(['hotel_name' => 'Default']);
        }

        if ($request->hasFile('qr_code')) {
            // Remove old file
            if ($setting->qr_code_url && file_exists(public_path($setting->qr_code_url))) {
                @unlink(public_path($setting->qr_code_url));
            }

            $file = $request->file('qr_code');
            $filename = 'qr_' . time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('uploads/hotel'), $filename);
            
            $setting->qr_code_url = 'uploads/hotel/' . $filename;
            $setting->save();
        }

        return new HotelSettingResource($setting);
    }

    /**
     * Delete hotel QR code.
     */
    public function deleteQrCode()
    {
        $setting = HotelSetting::first();
        if ($setting) {
            if ($setting->qr_code_url && file_exists(public_path($setting->qr_code_url))) {
                @unlink(public_path($setting->qr_code_url));
            }
            $setting->qr_code_url = null;
            $setting->save();
        }

        return new HotelSettingResource($setting);
    }
}
