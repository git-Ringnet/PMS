<?php

namespace Tests\Feature;

use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\HotelSetting;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/** Task 325 (TC_LT_23 / TC_BP_04): mã đăng ký trong log theo tiền tố cấu hình (NV), không cứng GAL. */
class ActivityLogPrefixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->withRole('super_admin')->create(['username' => 'prefix_user']));
        HotelSetting::query()->delete();
        HotelSetting::create(['hotel_name' => 'Test', 'prefix_booking_id' => 'NV']);
        ActivityLogService::forgetBookingPrefix();
    }

    protected function tearDown(): void
    {
        ActivityLogService::forgetBookingPrefix();
        parent::tearDown();
    }

    public function test_booking_code_uses_hotel_prefix(): void
    {
        $this->assertSame('NV22', ActivityLogService::bookingCode(22));
        $this->assertSame('NV22', ActivityLogService::bookingCode('NV22'));
    }

    public function test_old_logs_with_gal_prefix_are_shown_with_current_prefix(): void
    {
        $log = ActivityLog::create([
            'user_id' => auth()->id(), 'user_name' => 'prefix_user', 'action' => 'update', 'module' => 'Booking',
            'description' => '* Cập Nhật Thông Tin Đăng Ký GAL22 : - Số ngày : 4 ➜ 6', 'booking_code' => 'GAL22',
            'log_version' => 2, 'component' => 'Tạo đăng ký',
        ]);

        $data = (new ActivityLogResource($log))->toArray(Request::create('/'));

        $this->assertSame('NV22', $data['booking_code']);
        $this->assertStringContainsString('Đăng Ký NV22', $data['description']);
        $this->assertStringNotContainsString('GAL', $data['description']);
    }

    public function test_filter_by_number_finds_both_old_and_new_prefix_logs(): void
    {
        foreach (['GAL22', 'NV22', 'NV23'] as $code) {
            ActivityLog::create([
                'user_id' => auth()->id(), 'user_name' => 'prefix_user', 'action' => 'update', 'module' => 'Booking',
                'description' => 'x ' . $code, 'booking_code' => $code, 'log_version' => 2, 'component' => 'Tạo đăng ký',
            ]);
        }

        $codes = collect($this->getJson('/api/activity-logs?registration_code=22')->assertOk()->json('data'))->pluck('booking_code')->all();

        $this->assertEqualsCanonicalizing(['NV22', 'NV22'], $codes);
    }

    public function test_technical_models_are_not_written_to_the_activity_log(): void
    {
        $before = ActivityLog::count();

        \App\Models\HotelConfig::updateOrCreate(['name' => 'SystemThemeTopbarColor'], ['value' => '#0b233c']);

        $this->assertSame($before, ActivityLog::count());
    }

    public function test_preview_requests_are_not_logged(): void
    {
        $before = ActivityLog::count();

        $this->postJson('/api/bookings/999/checkout-preview', []);
        $this->postJson('/api/bookings/999/init-guests', []);
        $this->postJson('/api/i18n/collect', []);
        $this->postJson('/api/bookings/999/guests/import-preview', []);

        $this->assertSame($before, ActivityLog::count());
    }
}
