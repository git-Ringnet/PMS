<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class PortalWallpaperController extends Controller
{
    const CONFIG_KEY = 'Portal_WallpaperUrl';
    const POSITION_KEY = 'Portal_WallpaperPosition';
    const FIT_KEY = 'Portal_WallpaperFit';
    const OVERLAY_KEY = 'Portal_WallpaperOverlay';

    public function getWallpaper()
    {
        $url = HotelConfig::where('name', self::CONFIG_KEY)->value('value');
        $position = HotelConfig::where('name', self::POSITION_KEY)->value('value') ?: 'center center';
        $fit = HotelConfig::where('name', self::FIT_KEY)->value('value') ?: 'cover';
        $overlayVal = HotelConfig::where('name', self::OVERLAY_KEY)->value('value');
        $overlay = $overlayVal !== null ? (int) $overlayVal : 20;

        return response()->json([
            'success' => true,
            'wallpaper_url' => $url ?: null,
            'wallpaper_position' => $position,
            'wallpaper_fit' => $fit,
            'wallpaper_overlay' => $overlay,
        ]);
    }

    public function updateWallpaper(Request $request)
    {
        $request->validate([
            'wallpaper' => 'nullable|file|image|mimes:jpeg,png,jpg,webp|max:10240',
            'wallpaper_url' => 'nullable|string|max:500',
            'wallpaper_position' => 'nullable|string|max:50',
            'wallpaper_fit' => 'nullable|string|in:cover,contain',
            'wallpaper_overlay' => 'nullable|integer|min:0|max:100',
        ]);

        $wallpaperUrl = null;

        if ($request->hasFile('wallpaper')) {
            $file = $request->file('wallpaper');
            $uploadDir = public_path('uploads/wallpaper');
            if (!File::isDirectory($uploadDir)) {
                File::makeDirectory($uploadDir, 0755, true, true);
            }

            // Remove old uploaded file if exists
            $oldUrl = HotelConfig::where('name', self::CONFIG_KEY)->value('value');
            if ($oldUrl && str_starts_with($oldUrl, '/uploads/wallpaper/') && file_exists(public_path(ltrim($oldUrl, '/')))) {
                @unlink(public_path(ltrim($oldUrl, '/')));
            }

            $filename = 'wallpaper_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($uploadDir, $filename);
            $wallpaperUrl = '/uploads/wallpaper/' . $filename;
        } elseif ($request->filled('wallpaper_url')) {
            $wallpaperUrl = $request->input('wallpaper_url');
        } else {
            $wallpaperUrl = HotelConfig::where('name', self::CONFIG_KEY)->value('value');
        }

        if ($wallpaperUrl) {
            HotelConfig::updateOrCreate(
                ['name' => self::CONFIG_KEY],
                [
                    'value' => $wallpaperUrl,
                    'description' => 'Hình nền Portal / HomePage',
                    'is_visible' => true,
                ]
            );
        }

        $position = $request->input('wallpaper_position', 'center center');
        HotelConfig::updateOrCreate(
            ['name' => self::POSITION_KEY],
            [
                'value' => $position,
                'description' => 'Vị trí hiển thị hình nền Portal',
                'is_visible' => true,
            ]
        );

        $fit = $request->input('wallpaper_fit', 'cover');
        HotelConfig::updateOrCreate(
            ['name' => self::FIT_KEY],
            [
                'value' => $fit,
                'description' => 'Kiểu hiển thị hình nền Portal (cover / contain)',
                'is_visible' => true,
            ]
        );

        $overlay = $request->has('wallpaper_overlay') ? (int) $request->input('wallpaper_overlay') : 20;
        HotelConfig::updateOrCreate(
            ['name' => self::OVERLAY_KEY],
            [
                'value' => (string) $overlay,
                'description' => 'Độ tối lớp phủ hình nền Portal (0 - 100%)',
                'is_visible' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'wallpaper_url' => $wallpaperUrl,
            'wallpaper_position' => $position,
            'wallpaper_fit' => $fit,
            'wallpaper_overlay' => $overlay,
            'message' => 'Cập nhật hình nền thành công!',
        ]);
    }

    public function resetWallpaper()
    {
        $oldUrl = HotelConfig::where('name', self::CONFIG_KEY)->value('value');
        if ($oldUrl && str_starts_with($oldUrl, '/uploads/wallpaper/') && file_exists(public_path(ltrim($oldUrl, '/')))) {
            @unlink(public_path(ltrim($oldUrl, '/')));
        }

        HotelConfig::where('name', self::CONFIG_KEY)->delete();
        HotelConfig::where('name', self::POSITION_KEY)->delete();
        HotelConfig::where('name', self::FIT_KEY)->delete();
        HotelConfig::where('name', self::OVERLAY_KEY)->delete();

        return response()->json([
            'success' => true,
            'wallpaper_url' => null,
            'wallpaper_position' => 'center center',
            'wallpaper_fit' => 'cover',
            'wallpaper_overlay' => 20,
            'message' => 'Đã khôi phục hình nền mặc định.',
        ]);
    }
}
