<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active_user' => true,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Gán vai trò (kèm danh sách quyền) cho user, dùng cho test cần user có quyền thao tác.
     * Vd: User::factory()->withRole('branch_admin')->create(), ->withRole('fo_staff', ['fo.guest.edit'])
     */
    public function withRole(string $roleCode, array $permissionCodes = []): static
    {
        return $this->afterCreating(function (User $user) use ($roleCode, $permissionCodes) {
            $role = Role::firstOrCreate(['code' => $roleCode], ['name' => $roleCode, 'level' => 3, 'is_active' => true]);
            $permissionIds = collect($permissionCodes)
                ->map(fn ($code) => Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'FO'])->id);
            $role->permissions()->syncWithoutDetaching($permissionIds->all());
            UserRole::firstOrCreate(['user_id' => $user->id, 'role_id' => $role->id, 'system_branch_id' => null]);
        });
    }
}
