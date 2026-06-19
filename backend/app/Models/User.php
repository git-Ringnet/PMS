<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'username', 'email', 'password', 'employee_code', 'department_code', 'department',
    'job_title_code', 'job_title', 'birth_date', 'start_date', 'phone', 'address',
    'is_active_user', 'signature_url'
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active_user' => 'boolean',
            'birth_date' => 'date:Y-m-d',
            'start_date' => 'date:Y-m-d',
        ];
    }

    /**
     * Get the signature URL with full asset path.
     */
    public function getSignatureUrlAttribute($value)
    {
        return $value ? asset($value) : null;
    }
}
