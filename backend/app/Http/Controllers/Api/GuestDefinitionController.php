<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GuestTitle;
use App\Models\BorderGate;
use App\Models\EntryPurpose;
use App\Models\GuestType;
use App\Models\IdType;
use App\Models\Province;
use App\Models\District;
use App\Models\Ward;

class GuestDefinitionController extends Controller
{
    /**
     * Lấy toàn bộ danh mục thông tin khách định nghĩa trong hệ thống
     * GET /api/guest-definitions
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'titles'         => GuestTitle::where('is_active', true)->orderBy('order_index')->get(),
                'border_gates'   => BorderGate::where('is_active', true)->orderBy('order_index')->get(),
                'entry_purposes' => EntryPurpose::where('is_active', true)->orderBy('order_index')->get(),
                'guest_types'    => GuestType::where('is_active', true)->orderBy('order_index')->get(),
                'id_types'       => IdType::where('is_active', true)->orderBy('order_index')->get(),
                'provinces'      => Province::where('is_active', true)->orderBy('order_index')->get(),
            ],
        ]);
    }

    /**
     * Danh sách danh xưng
     * GET /api/guest-titles
     */
    public function titles()
    {
        return response()->json([
            'success' => true,
            'data'    => GuestTitle::where('is_active', true)->orderBy('order_index')->get(),
        ]);
    }

    /**
     * Danh sách cửa khẩu
     * GET /api/border-gates
     */
    public function borderGates()
    {
        return response()->json([
            'success' => true,
            'data'    => BorderGate::where('is_active', true)->orderBy('order_index')->get(),
        ]);
    }

    /**
     * Danh sách mục đích lưu trú
     * GET /api/entry-purposes
     */
    public function entryPurposes()
    {
        return response()->json([
            'success' => true,
            'data'    => EntryPurpose::where('is_active', true)->orderBy('order_index')->get(),
        ]);
    }

    /**
     * Danh sách loại khách
     * GET /api/guest-types
     */
    public function guestTypes()
    {
        return response()->json([
            'success' => true,
            'data'    => GuestType::where('is_active', true)->orderBy('order_index')->get(),
        ]);
    }

    /**
     * Danh sách loại giấy tờ
     * GET /api/id-types
     */
    public function idTypes()
    {
        return response()->json([
            'success' => true,
            'data'    => IdType::where('is_active', true)->orderBy('order_index')->get(),
        ]);
    }

    /**
     * Danh sách tỉnh/thành phố trong DB
     * GET /api/provinces
     */
    public function provinces()
    {
        return response()->json([
            'success' => true,
            'data'    => Province::where('is_active', true)->orderBy('order_index')->get(),
        ]);
    }

    /**
     * Danh sách quận/huyện trong DB theo tỉnh
     * GET /api/districts?province_name=...
     */
    public function districts(Request $request)
    {
        $query = District::where('is_active', true);
        if ($request->filled('province_name')) {
            $query->where('province_name', $request->input('province_name'));
        }
        if ($request->filled('province_code')) {
            $query->where('province_code', $request->input('province_code'));
        }
        return response()->json([
            'success' => true,
            'data'    => $query->orderBy('order_index')->get(),
        ]);
    }

    /**
     * Danh sách phường/xã trong DB theo quận/huyện
     * GET /api/wards?district_name=...
     */
    public function wards(Request $request)
    {
        $query = Ward::where('is_active', true);
        if ($request->filled('district_name')) {
            $query->where('district_name', $request->input('district_name'));
        }
        if ($request->filled('district_code')) {
            $query->where('district_code', $request->input('district_code'));
        }
        return response()->json([
            'success' => true,
            'data'    => $query->orderBy('order_index')->get(),
        ]);
    }

    /**
     * Đồng bộ địa giới hành chính vào Database (khi user chọn từ API)
     * POST /api/geo/sync
     */
    public function syncGeo(Request $request)
    {
        $province = $request->input('province');
        $district = $request->input('district');
        $ward     = $request->input('ward');

        $provName = is_array($province) ? ($province['name'] ?? null) : $province;
        $distName = is_array($district) ? ($district['name'] ?? null) : $district;
        $wardName = is_array($ward)     ? ($ward['name'] ?? null)     : $ward;

        if ($provName) {
            $provCode = is_array($province) ? ($province['code'] ?? null) : null;
            $provType = is_array($province) ? ($province['type'] ?? null) : null;
            Province::updateOrCreate(
                ['name' => trim($provName)],
                array_filter(['code' => $provCode ? (string)$provCode : null, 'type' => $provType, 'is_active' => true])
            );
        }

        if ($distName) {
            $distCode = is_array($district) ? ($district['code'] ?? null) : null;
            $distType = is_array($district) ? ($district['type'] ?? null) : null;
            District::updateOrCreate(
                ['name' => trim($distName), 'province_name' => $provName ? trim($provName) : null],
                array_filter(['code' => $distCode ? (string)$distCode : null, 'type' => $distType, 'is_active' => true])
            );
        }

        if ($wardName) {
            $wardCode = is_array($ward) ? ($ward['code'] ?? null) : null;
            $wardType = is_array($ward) ? ($ward['type'] ?? null) : null;
            Ward::updateOrCreate(
                ['name' => trim($wardName), 'district_name' => $distName ? trim($distName) : null, 'province_name' => $provName ? trim($provName) : null],
                array_filter(['code' => $wardCode ? (string)$wardCode : null, 'type' => $wardType, 'is_active' => true])
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã đồng bộ địa giới vào cơ sở dữ liệu thành công.',
        ]);
    }
}
