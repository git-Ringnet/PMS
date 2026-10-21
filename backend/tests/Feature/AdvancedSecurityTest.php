<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SecurityAllowedIp;
use App\Models\SecurityExemptUser;
use App\Models\SecuritySetting;
use App\Models\SecurityTimeWindow;
use App\Models\User;
use App\Services\AccessRestrictionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AdvancedSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['username' => 'root_admin']);
        $role = Role::create(['code' => 'super_admin', 'name' => 'Super admin', 'level' => 0, 'department_scope' => 'ALL', 'is_active' => true]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function fromIp(string $ip): self
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    public function test_ip_rule_matching_supports_single_ip_cidr_and_ipv6(): void
    {
        $this->assertTrue(AccessRestrictionService::ipMatches('113.161.185.53', '113.161.185.53'));
        $this->assertFalse(AccessRestrictionService::ipMatches('113.161.185.54', '113.161.185.53'));
        $this->assertTrue(AccessRestrictionService::ipMatches('192.168.1.77', '192.168.1.0/24'));
        $this->assertFalse(AccessRestrictionService::ipMatches('192.168.2.77', '192.168.1.0/24'));
        $this->assertTrue(AccessRestrictionService::ipMatches('10.1.2.3', '10.0.0.0/8'));
        $this->assertTrue(AccessRestrictionService::ipMatches('10.1.2.3', '10.1.2.0/23'));
        $this->assertTrue(AccessRestrictionService::ipMatches('2001:db8::1', '2001:db8::/32'));
        $this->assertFalse(AccessRestrictionService::ipMatches('2001:db9::1', '2001:db8::/32'));
        $this->assertFalse(AccessRestrictionService::ipMatches('10.0.0.1', '10.0.0.0/99'));
        $this->assertFalse(AccessRestrictionService::ipMatches('abc', '10.0.0.0/8'));

        $this->assertTrue(AccessRestrictionService::isValidRule('192.168.1.0/24'));
        $this->assertTrue(AccessRestrictionService::isValidRule('113.161.185.53'));
        $this->assertFalse(AccessRestrictionService::isValidRule('999.1.1.1'));
        $this->assertFalse(AccessRestrictionService::isValidRule('10.0.0.0/33'));
        $this->assertFalse(AccessRestrictionService::isValidRule('hello'));
    }

    public function test_ip_restriction_blocks_outside_ips_but_not_allowed_or_exempt_users(): void
    {
        SecurityAllowedIp::create(['name' => 'Mạng nội bộ', 'ip' => '10.0.0.0/24']);
        SecuritySetting::current()->update(['ip_restriction_enabled' => true]);

        $staff = User::factory()->create(['username' => 'staff']);
        $this->actingAs($staff);

        $this->fromIp('10.0.0.5')->getJson('/api/me')->assertOk();

        $this->fromIp('8.8.8.8')->getJson('/api/me')
            ->assertStatus(401)->assertJsonPath('code', 'access_restricted');

        // User được miễn truy cập được từ mọi IP
        SecurityExemptUser::create(['user_id' => $staff->id]);
        $this->fromIp('8.8.8.8')->getJson('/api/me')->assertOk();
    }

    public function test_super_admin_is_never_locked_out_by_ip_restriction(): void
    {
        SecurityAllowedIp::create(['name' => 'Chỉ văn phòng', 'ip' => '10.0.0.1']);
        SecuritySetting::current()->update(['ip_restriction_enabled' => true]);

        $this->actingAs($this->superAdmin());

        $this->fromIp('8.8.8.8')->getJson('/api/me')->assertOk();
    }

    public function test_time_restriction_blocks_outside_windows(): void
    {
        // Thứ hai 21/09/2026 ngày làm việc 08:00 - 17:00
        SecurityTimeWindow::create(['days' => '1,2,3,4,5', 'start_time' => '08:00', 'end_time' => '17:00']);
        SecuritySetting::current()->update(['time_restriction_enabled' => true]);
        $this->actingAs(User::factory()->create(['username' => 'staff']));

        Carbon::setTestNow(Carbon::parse('2026-09-21 10:30', 'Asia/Ho_Chi_Minh'));
        $this->getJson('/api/me')->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-09-21 20:00', 'Asia/Ho_Chi_Minh'));
        $this->getJson('/api/me')->assertStatus(401)->assertJsonPath('code', 'access_restricted');

        // Chủ nhật 27/09/2026 không nằm trong khoảng
        Carbon::setTestNow(Carbon::parse('2026-09-27 10:30', 'Asia/Ho_Chi_Minh'));
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_login_is_rejected_from_disallowed_ip(): void
    {
        User::factory()->create(['username' => 'staff', 'password' => bcrypt('secret-pass')]);
        SecurityAllowedIp::create(['name' => 'Mạng nội bộ', 'ip' => '10.0.0.5']);
        SecuritySetting::current()->update(['ip_restriction_enabled' => true]);

        $this->fromIp('8.8.8.8')->postJson('/api/login', ['username' => 'staff', 'password' => 'secret-pass'])
            ->assertStatus(403)->assertJsonPath('code', 'access_restricted');

        $this->fromIp('10.0.0.5')->postJson('/api/login', ['username' => 'staff', 'password' => 'secret-pass'])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_auto_logout_revokes_token_after_configured_hours(): void
    {
        SecuritySetting::current()->update(['auto_logout_enabled' => true, 'auto_logout_hours' => 5]);
        $user = User::factory()->create(['username' => 'staff']);
        $plain = $user->createToken('auth_token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$plain}")->getJson('/api/me')->assertOk();

        PersonalAccessToken::query()->update(['created_at' => now()->subHours(6)]);
        $this->app['auth']->forgetGuards(); // request thứ hai phải nạp lại token từ DB
        $this->withHeader('Authorization', "Bearer {$plain}")->getJson('/api/me')
            ->assertStatus(401)->assertJsonPath('code', 'session_expired');
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_auto_logout_disabled_keeps_old_tokens_valid(): void
    {
        $user = User::factory()->create(['username' => 'staff']);
        $plain = $user->createToken('auth_token')->plainTextToken;
        PersonalAccessToken::query()->update(['created_at' => now()->subDays(3)]);

        $this->withHeader('Authorization', "Bearer {$plain}")->getJson('/api/me')->assertOk();
    }

    public function test_only_super_admin_can_manage_security_settings(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'staff']));
        $this->getJson('/api/security-settings')->assertStatus(403);
        $this->putJson('/api/security-settings', ['auto_logout_enabled' => true])->assertStatus(403);
        $this->postJson('/api/security-settings/ips', ['name' => 'X', 'ip' => '1.1.1.1'])->assertStatus(403);
    }

    public function test_super_admin_configures_ips_time_windows_and_exempt_users(): void
    {
        $this->actingAs($this->superAdmin());

        // Bật công tắc trước rồi mới thêm IP / khoảng thời gian (giao diện chỉ hiện chi tiết khi đã bật)
        $this->putJson('/api/security-settings', ['ip_restriction_enabled' => true])->assertOk();
        $this->putJson('/api/security-settings', ['ip_restriction_enabled' => false])->assertOk();

        $this->postJson('/api/security-settings/ips', ['name' => 'TL1-VNPT', 'ip' => '999.1.1.1'])->assertStatus(422);
        $this->postJson('/api/security-settings/ips', ['name' => 'TL1-VNPT', 'ip' => '113.161.186.246'])->assertStatus(201);
        $ipId = SecurityAllowedIp::first()->id;
        $this->putJson("/api/security-settings/ips/{$ipId}", ['name' => 'TL1-VNPT (đổi)', 'ip' => '113.161.186.0/24'])->assertOk();
        $this->assertSame('113.161.186.0/24', SecurityAllowedIp::first()->ip);

        $this->postJson('/api/security-settings/time-windows', ['days' => [1, 2, 3], 'start_time' => '17:00', 'end_time' => '08:00'])->assertStatus(422);
        $this->postJson('/api/security-settings/time-windows', ['days' => [], 'start_time' => '08:00', 'end_time' => '17:00'])->assertStatus(422);
        $this->postJson('/api/security-settings/time-windows', ['days' => [3, 1, 2, 2], 'start_time' => '08:00', 'end_time' => '17:00'])->assertStatus(201);
        $this->assertSame('1,2,3', SecurityTimeWindow::first()->days);

        $this->putJson('/api/security-settings', [
            'ip_restriction_enabled' => true, 'time_restriction_enabled' => true,
            'auto_logout_enabled' => true, 'auto_logout_hours' => 5,
        ])->assertOk()->assertJsonPath('data.settings.ipRestrictionEnabled', true);
        $this->putJson('/api/security-settings', ['auto_logout_hours' => 0])->assertStatus(422);

        // Không xóa mục cuối khi đang bật giới hạn
        $this->deleteJson("/api/security-settings/ips/{$ipId}")->assertStatus(422);
        $this->deleteJson('/api/security-settings/time-windows/' . SecurityTimeWindow::first()->id)->assertStatus(422);

        $staff = User::factory()->create(['username' => 'staff']);
        $this->postJson('/api/security-settings/exempt-users', ['user_ids' => [$staff->id]])->assertOk()
            ->assertJsonPath('data.exemptUsers.0.id', $staff->id);
        $this->postJson('/api/security-settings/exempt-users', ['user_ids' => [$staff->id]])->assertOk();
        $this->assertSame(1, SecurityExemptUser::count());
        $this->deleteJson("/api/security-settings/exempt-users/{$staff->id}")->assertOk();
        $this->assertSame(0, SecurityExemptUser::count());

        $this->fromIp('203.0.113.9')->getJson('/api/security-settings/my-ip')->assertOk()->assertJsonPath('data.ip', '203.0.113.9');
    }

    public function test_time_window_boundaries_and_multiple_windows(): void
    {
        SecurityTimeWindow::create(['days' => '1,2,3,4,5', 'start_time' => '08:00', 'end_time' => '17:00']);
        SecurityTimeWindow::create(['days' => '6', 'start_time' => '08:00', 'end_time' => '12:00']);
        SecuritySetting::current()->update(['time_restriction_enabled' => true]);
        $this->actingAs(User::factory()->create(['username' => 'staff']));

        $at = fn (string $time) => Carbon::setTestNow(Carbon::parse($time, 'Asia/Ho_Chi_Minh'));

        // Thứ hai 21/09/2026: biên 08:00 và 17:00 được phép, 07:59 và 17:01 bị chặn
        foreach (['2026-09-21 08:00' => 200, '2026-09-21 17:00' => 200, '2026-09-21 07:59' => 401, '2026-09-21 17:01' => 401] as $time => $status) {
            $at($time);
            $this->getJson('/api/me')->assertStatus($status);
        }

        // Thứ bảy 26/09/2026 chỉ được đến 12:00
        $at('2026-09-26 11:59');
        $this->getJson('/api/me')->assertOk();
        $at('2026-09-26 14:00');
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_time_is_evaluated_in_vietnam_timezone(): void
    {
        SecurityTimeWindow::create(['days' => '1', 'start_time' => '08:00', 'end_time' => '17:00']);
        SecuritySetting::current()->update(['time_restriction_enabled' => true]);
        $this->actingAs(User::factory()->create(['username' => 'staff']));

        // 02:00 UTC thứ hai = 09:00 giờ Việt Nam thứ hai => được phép
        Carbon::setTestNow(Carbon::parse('2026-09-21 02:00', 'UTC'));
        $this->getJson('/api/me')->assertOk();

        // 11:00 UTC thứ hai = 18:00 giờ Việt Nam => bị chặn
        Carbon::setTestNow(Carbon::parse('2026-09-21 11:00', 'UTC'));
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_ip_and_time_restrictions_must_both_pass_and_exempt_bypasses_both(): void
    {
        SecurityAllowedIp::create(['name' => 'Nội bộ', 'ip' => '10.0.0.0/24']);
        SecurityTimeWindow::create(['days' => '1,2,3,4,5,6,7', 'start_time' => '08:00', 'end_time' => '17:00']);
        SecuritySetting::current()->update(['ip_restriction_enabled' => true, 'time_restriction_enabled' => true]);
        $staff = User::factory()->create(['username' => 'staff']);
        $this->actingAs($staff);

        Carbon::setTestNow(Carbon::parse('2026-09-21 10:00', 'Asia/Ho_Chi_Minh'));
        $this->fromIp('10.0.0.9')->getJson('/api/me')->assertOk();                  // đúng IP, đúng giờ
        $this->fromIp('8.8.8.8')->getJson('/api/me')->assertStatus(401);            // sai IP, đúng giờ

        Carbon::setTestNow(Carbon::parse('2026-09-21 22:00', 'Asia/Ho_Chi_Minh'));
        $this->fromIp('10.0.0.9')->getJson('/api/me')->assertStatus(401);           // đúng IP, sai giờ
        $this->fromIp('8.8.8.8')->getJson('/api/me')->assertStatus(401);            // sai cả hai

        SecurityExemptUser::create(['user_id' => $staff->id]);
        $this->fromIp('8.8.8.8')->getJson('/api/me')->assertOk();                   // được miễn cả hai
    }

    public function test_disabled_toggles_ignore_configured_entries(): void
    {
        SecurityAllowedIp::create(['name' => 'Nội bộ', 'ip' => '10.0.0.1']);
        SecurityTimeWindow::create(['days' => '1', 'start_time' => '08:00', 'end_time' => '09:00']);
        $this->actingAs(User::factory()->create(['username' => 'staff']));

        Carbon::setTestNow(Carbon::parse('2026-09-27 23:00', 'Asia/Ho_Chi_Minh'));
        $this->fromIp('8.8.8.8')->getJson('/api/me')->assertOk();
    }

    public function test_removing_an_allowed_ip_blocks_it_immediately(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);
        $this->postJson('/api/security-settings/ips', ['name' => 'A', 'ip' => '10.0.0.1'])->assertStatus(201);
        $this->postJson('/api/security-settings/ips', ['name' => 'B', 'ip' => '10.0.0.2'])->assertStatus(201);
        $this->putJson('/api/security-settings', ['ip_restriction_enabled' => true])->assertOk();

        $staff = User::factory()->create(['username' => 'staff']);
        $this->actingAs($staff);
        $this->fromIp('10.0.0.2')->getJson('/api/me')->assertOk();

        $this->actingAs($admin);
        $this->deleteJson('/api/security-settings/ips/' . SecurityAllowedIp::where('ip', '10.0.0.2')->value('id'))->assertOk();

        $this->actingAs($staff);
        $this->fromIp('10.0.0.2')->getJson('/api/me')->assertStatus(401);
        $this->fromIp('10.0.0.1')->getJson('/api/me')->assertOk();
    }

    public function test_enabling_with_empty_lists_blocks_regular_users_but_not_super_admin_or_exempt(): void
    {
        SecuritySetting::current()->update(['ip_restriction_enabled' => true, 'time_restriction_enabled' => true]);

        $this->actingAs(User::factory()->create(['username' => 'staff']));
        $this->getJson('/api/me')->assertStatus(401);

        $exempt = User::factory()->create(['username' => 'exempt']);
        SecurityExemptUser::create(['user_id' => $exempt->id]);
        $this->actingAs($exempt)->getJson('/api/me')->assertOk();

        $this->actingAs($this->superAdmin())->getJson('/api/me')->assertOk();
    }

    public function test_login_with_cidr_and_ipv6_rules(): void
    {
        User::factory()->create(['username' => 'staff', 'password' => bcrypt('secret-pass')]);
        SecurityAllowedIp::create(['name' => 'LAN', 'ip' => '192.168.1.0/24']);
        SecurityAllowedIp::create(['name' => 'IPv6', 'ip' => '2001:db8::/32']);
        SecuritySetting::current()->update(['ip_restriction_enabled' => true]);

        $login = fn (string $ip) => $this->fromIp($ip)->postJson('/api/login', ['username' => 'staff', 'password' => 'secret-pass']);

        $login('192.168.1.200')->assertOk();
        $login('2001:db8::abcd')->assertOk();
        $login('192.168.2.1')->assertStatus(403);
        $login('2001:db9::1')->assertStatus(403);
    }

    public function test_login_is_rejected_outside_time_window_and_wrong_password_still_fails(): void
    {
        User::factory()->create(['username' => 'staff', 'password' => bcrypt('secret-pass')]);
        SecurityTimeWindow::create(['days' => '1,2,3,4,5', 'start_time' => '08:00', 'end_time' => '17:00']);
        SecuritySetting::current()->update(['time_restriction_enabled' => true]);

        Carbon::setTestNow(Carbon::parse('2026-09-21 20:00', 'Asia/Ho_Chi_Minh'));
        $this->postJson('/api/login', ['username' => 'staff', 'password' => 'secret-pass'])
            ->assertStatus(403)->assertJsonPath('code', 'access_restricted');
        $this->postJson('/api/login', ['username' => 'staff', 'password' => 'wrong'])->assertStatus(422);

        Carbon::setTestNow(Carbon::parse('2026-09-21 09:00', 'Asia/Ho_Chi_Minh'));
        $this->postJson('/api/login', ['username' => 'staff', 'password' => 'secret-pass'])->assertOk();
    }

    public function test_auto_logout_applies_to_exempt_users_and_super_admin_too(): void
    {
        SecuritySetting::current()->update(['auto_logout_enabled' => true, 'auto_logout_hours' => 1]);

        foreach ([$this->superAdmin(), User::factory()->create(['username' => 'exempt'])] as $user) {
            SecurityExemptUser::firstOrCreate(['user_id' => $user->id]);
            $plain = $user->createToken('auth_token')->plainTextToken;
            PersonalAccessToken::query()->where('tokenable_id', $user->id)->update(['created_at' => now()->subHours(2)]);
            $this->app['auth']->forgetGuards();

            $this->withHeader('Authorization', "Bearer {$plain}")->getJson('/api/me')
                ->assertStatus(401)->assertJsonPath('code', 'session_expired');
        }
    }

    public function test_auto_logout_hours_boundary_and_new_login_after_expiry(): void
    {
        SecuritySetting::current()->update(['auto_logout_enabled' => true, 'auto_logout_hours' => 5]);
        $user = User::factory()->create(['username' => 'staff', 'password' => bcrypt('secret-pass')]);
        $plain = $user->createToken('auth_token')->plainTextToken;

        // Mới 4 giờ 59 phút: vẫn dùng được
        PersonalAccessToken::query()->update(['created_at' => now()->subHours(4)->subMinutes(59)]);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$plain}")->getJson('/api/me')->assertOk();

        // Quá 5 giờ: bị đăng xuất, nhưng đăng nhập lại được ngay
        PersonalAccessToken::query()->update(['created_at' => now()->subHours(5)->subMinute()]);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$plain}")->getJson('/api/me')->assertStatus(401);

        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web'); // request Bearer trước đó đã đổi guard mặc định trong cùng tiến trình test
        $this->postJson('/api/login', ['username' => 'staff', 'password' => 'secret-pass'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_logout_is_allowed_even_when_access_is_restricted(): void
    {
        SecuritySetting::current()->update(['ip_restriction_enabled' => true]);
        $staff = User::factory()->create(['username' => 'staff']);
        $plain = $staff->createToken('auth_token')->plainTextToken;

        $this->fromIp('8.8.8.8')->withHeader('Authorization', "Bearer {$plain}")->postJson('/api/logout')->assertOk();
        $this->assertSame(0, PersonalAccessToken::count());
    }
}
