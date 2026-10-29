<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Dòng 333: tải ảnh khách lên không bị lỗi khi tên file gốc dài / có dấu tiếng Việt.
 */
class GuestAvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1PX = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private array $createdFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $path) {
            @unlink(public_path($path));
        }
        parent::tearDown();
    }

    private function pngUpload(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'avatar');
        file_put_contents($path, base64_decode(self::PNG_1PX));

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function upload(Guest $guest, string $name)
    {
        $response = $this->post("/api/guests/{$guest->id}/avatar", ['avatar' => $this->pngUpload($name)], ['Accept' => 'application/json']);
        if ($response->json('avatar')) {
            $this->createdFiles[] = $response->json('avatar');
        }

        return $response;
    }

    public function test_upload_with_long_vietnamese_file_name_succeeds_with_safe_stored_name(): void
    {
        $this->actingAs(User::factory()->withRole('fo_staff', ['fo.guest.edit'])->create());
        $guest = Guest::create(['full_name' => 'Walkin Guest']);

        $response = $this->upload($guest, str_repeat('ảnh khách hàng chụp tại quầy lễ tân ', 10) . '.png')->assertOk();

        $stored = $response->json('avatar');
        $this->assertMatchesRegularExpression('#^uploads/avatars/avatar_[A-Za-z0-9_-]+_\d{14}_[A-Za-z0-9]{6}\.png$#', $stored);
        $this->assertLessThanOrEqual(255, strlen($stored));
        $this->assertFileExists(public_path($stored));
        $this->assertSame($stored, $guest->fresh()->avatar);
    }

    public function test_new_upload_replaces_old_file(): void
    {
        $this->actingAs(User::factory()->withRole('fo_staff', ['fo.guest.edit'])->create());
        $guest = Guest::create(['full_name' => 'Walkin Guest']);

        $first = $this->upload($guest, 'webcam_1.jpg')->assertOk()->json('avatar');
        $second = $this->upload($guest, 'webcam_2.jpg')->assertOk()->json('avatar');

        $this->assertNotSame($first, $second);
        $this->assertFileDoesNotExist(public_path($first));
        $this->assertFileExists(public_path($second));
    }

    public function test_non_image_is_rejected(): void
    {
        $this->actingAs(User::factory()->withRole('fo_staff', ['fo.guest.edit'])->create());
        $guest = Guest::create(['full_name' => 'Walkin Guest']);

        $path = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($path, 'not an image');
        $this->post("/api/guests/{$guest->id}/avatar", ['avatar' => new UploadedFile($path, 'a.png', 'image/png', null, true)], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }
}
