<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserSettingController extends Controller
{
    /**
     * Lấy thiết lập của user đang đăng nhập.
     * Nếu chưa có → tạo mới với giá trị mặc định.
     */
    public function show()
    {
        $userId = Auth::id();

        $setting = UserSetting::firstOrCreate(
            ['user_id' => $userId],
            [
                'sort_option' => 'Phòng',
                'night_view'  => true,
                'show_notes'  => true,
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => $setting,
        ]);
    }

    /**
     * Cập nhật thiết lập của user đang đăng nhập.
     */
    public function update(Request $request)
    {
        $userId = Auth::id();

        $validated = $request->validate([
            'sort_option' => 'sometimes|string|in:Phòng,Loại phòng,Dạng phòng,Tầng',
            'night_view'  => 'sometimes|boolean',
            'show_notes'  => 'sometimes|boolean',
        ]);

        $setting = UserSetting::updateOrCreate(
            ['user_id' => $userId],
            $validated
        );

        return response()->json([
            'success' => true,
            'data'    => $setting,
        ]);
    }
}
