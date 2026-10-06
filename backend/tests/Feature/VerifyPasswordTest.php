<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VerifyPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_password_success()
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secret123@'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/me/verify-password', [
            'password' => 'Secret123@',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_verify_password_fails_with_wrong_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secret123@'),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/me/verify-password', [
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Đăng nhập không thành công',
            ]);
    }
}
