<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelConfig;
use Illuminate\Http\Request;

class SystemThemeController extends Controller
{
    private const CONFIG_NAME = 'SystemThemeTopbarColor';
    private const DEFAULT_COLOR = '#006bdb';

    public function show()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'color' => $this->currentColor(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ quản trị viên được phép thay đổi màu nền hệ thống.',
            ], 403);
        }

        $validated = $request->validate([
            'color' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $this->isAllowedColor((string) $value)) {
                    $fail('Màu nền hệ thống không hợp lệ.');
                }
            }],
        ]);

        $config = HotelConfig::updateOrCreate(
            ['name' => self::CONFIG_NAME],
            [
                'value' => trim($validated['color']),
                'description' => 'Màu nền thanh hệ thống dùng chung cho toàn bộ người dùng.',
                'is_visible' => false,
            ],
        );

        return response()->json([
            'success' => true,
            'data' => [
                'color' => $config->value,
            ],
        ]);
    }

    private function currentColor(): string
    {
        $value = HotelConfig::query()->where('name', self::CONFIG_NAME)->value('value');

        return $this->isAllowedColor((string) $value) ? trim((string) $value) : self::DEFAULT_COLOR;
    }

    private function isAllowedColor(string $value): bool
    {
        $value = trim($value);

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return true;
        }

        return (bool) preg_match(
            '/^linear-gradient\(\s*(?:[0-9]{1,3})deg\s*,\s*#[0-9a-fA-F]{6}\s*,\s*#[0-9a-fA-F]{6}\s*,\s*#[0-9a-fA-F]{6}\s*\)$/',
            $value,
        );
    }
}
