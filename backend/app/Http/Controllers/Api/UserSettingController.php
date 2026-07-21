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
                'settings'    => [],
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
            'settings'    => 'sometimes|array',
        ]);

        $setting = UserSetting::firstOrCreate(['user_id' => $userId]);

        if ($request->has('settings')) {
            $currentSettings = $setting->settings ?? [];
            $newSettings = $request->input('settings', []);
            $setting->settings = array_replace_recursive($currentSettings, $newSettings);
        }

        if ($request->has('sort_option')) {
            $setting->sort_option = $validated['sort_option'];
        }
        if ($request->has('night_view')) {
            $setting->night_view = $validated['night_view'];
        }
        if ($request->has('show_notes')) {
            $setting->show_notes = $validated['show_notes'];
        }

        $setting->save();

        return response()->json([
            'success' => true,
            'data'    => $setting,
        ]);
    }
}
