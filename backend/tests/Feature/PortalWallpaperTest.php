<?php

namespace Tests\Feature;

use App\Models\HotelConfig;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PortalWallpaperTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['username' => 'admin_user']);
        $adminRole = Role::firstOrCreate(
            ['code' => 'admin_role'],
            ['name' => 'Admin Role', 'level' => 1, 'department_scope' => 'ALL', 'is_active' => true]
        );
        $settingPerm = Permission::firstOrCreate(
            ['code' => 'system.setting'],
            ['name' => 'System Setting', 'module' => 'SYSTEM']
        );
        $adminRole->permissions()->syncWithoutDetaching([$settingPerm->id]);
        $this->adminUser->roles()->syncWithoutDetaching([$adminRole->id]);

        $this->normalUser = User::factory()->create(['username' => 'normal_user']);
    }

    public function test_get_wallpaper_returns_null_by_default(): void
    {
        $this->actingAs($this->normalUser);
        $response = $this->getJson('/api/portal-wallpaper');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('wallpaper_url', null);
    }

    public function test_unauthorized_user_cannot_update_wallpaper(): void
    {
        $this->actingAs($this->normalUser);
        $response = $this->postJson('/api/portal-wallpaper', [
            'wallpaper_url' => 'https://example.com/bg.jpg',
        ]);
        $response->assertForbidden();
    }

    public function test_authorized_user_can_update_wallpaper_url(): void
    {
        $this->actingAs($this->adminUser);
        $response = $this->postJson('/api/portal-wallpaper', [
            'wallpaper_url' => 'https://example.com/custom-bg.jpg',
            'wallpaper_position' => 'center top',
            'wallpaper_fit' => 'contain',
            'wallpaper_overlay' => 10,
        ]);
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('wallpaper_url', 'https://example.com/custom-bg.jpg')
            ->assertJsonPath('wallpaper_position', 'center top')
            ->assertJsonPath('wallpaper_fit', 'contain')
            ->assertJsonPath('wallpaper_overlay', 10);

        $this->assertDatabaseHas('hotel_configs', [
            'name' => 'Portal_WallpaperUrl',
            'value' => 'https://example.com/custom-bg.jpg',
        ]);
        $this->assertDatabaseHas('hotel_configs', [
            'name' => 'Portal_WallpaperPosition',
            'value' => 'center top',
        ]);
        $this->assertDatabaseHas('hotel_configs', [
            'name' => 'Portal_WallpaperFit',
            'value' => 'contain',
        ]);
        $this->assertDatabaseHas('hotel_configs', [
            'name' => 'Portal_WallpaperOverlay',
            'value' => '10',
        ]);
    }

    public function test_authorized_user_can_upload_wallpaper_image(): void
    {
        $this->actingAs($this->adminUser);
        $fakeImage = UploadedFile::fake()->create('custom_wallpaper.png', 500, 'image/png');

        $response = $this->postJson('/api/portal-wallpaper', [
            'wallpaper' => $fakeImage,
            'wallpaper_position' => '50% 20%',
            'wallpaper_fit' => 'cover',
            'wallpaper_overlay' => 0,
        ]);
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('wallpaper_position', '50% 20%')
            ->assertJsonPath('wallpaper_fit', 'cover')
            ->assertJsonPath('wallpaper_overlay', 0);

        $uploadedUrl = $response->json('wallpaper_url');
        $this->assertNotNull($uploadedUrl);
        $this->assertStringStartsWith('/uploads/wallpaper/', $uploadedUrl);

        // Clean up uploaded file
        $filePath = public_path(ltrim($uploadedUrl, '/'));
        if (File::exists($filePath)) {
            File::delete($filePath);
        }
    }

    public function test_authorized_user_can_reset_wallpaper(): void
    {
        HotelConfig::create([
            'name' => 'Portal_WallpaperUrl',
            'value' => 'https://example.com/custom-bg.jpg',
        ]);
        HotelConfig::create([
            'name' => 'Portal_WallpaperPosition',
            'value' => 'center top',
        ]);
        HotelConfig::create([
            'name' => 'Portal_WallpaperFit',
            'value' => 'contain',
        ]);
        HotelConfig::create([
            'name' => 'Portal_WallpaperOverlay',
            'value' => '15',
        ]);

        $this->actingAs($this->adminUser);
        $response = $this->deleteJson('/api/portal-wallpaper');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('wallpaper_url', null)
            ->assertJsonPath('wallpaper_position', 'center center')
            ->assertJsonPath('wallpaper_fit', 'cover')
            ->assertJsonPath('wallpaper_overlay', 20);

        $this->assertDatabaseMissing('hotel_configs', [
            'name' => 'Portal_WallpaperUrl',
        ]);
        $this->assertDatabaseMissing('hotel_configs', [
            'name' => 'Portal_WallpaperPosition',
        ]);
        $this->assertDatabaseMissing('hotel_configs', [
            'name' => 'Portal_WallpaperFit',
        ]);
        $this->assertDatabaseMissing('hotel_configs', [
            'name' => 'Portal_WallpaperOverlay',
        ]);
    }

    public function test_unauthenticated_request_returns_401_json(): void
    {
        $response = $this->postJson('/api/portal-wallpaper', [
            'wallpaper_position' => 'center center',
        ]);
        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_branch_admin_user_can_update_wallpaper(): void
    {
        $branchAdmin = User::factory()->create(['username' => 'branch_admin_user']);
        $branchAdminRole = Role::firstOrCreate(
            ['code' => 'branch_admin'],
            ['name' => 'Branch Admin', 'level' => 2, 'department_scope' => 'ALL', 'is_active' => true]
        );
        $branchAdmin->roles()->syncWithoutDetaching([$branchAdminRole->id]);

        $this->actingAs($branchAdmin);
        $response = $this->postJson('/api/portal-wallpaper', [
            'wallpaper_url' => 'https://example.com/branch-admin-bg.jpg',
            'wallpaper_position' => '50% 50%',
            'wallpaper_fit' => 'cover',
            'wallpaper_overlay' => 25,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('wallpaper_url', 'https://example.com/branch-admin-bg.jpg');
    }
}

