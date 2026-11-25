<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // <- add this


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable; 

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_code',
        'email_verification_expires_at',
        'email_verification_attempts',
        'email_verification_sent_at',
    ];

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
            'must_change_password' => 'boolean',
            'email_verification_expires_at' => 'datetime',
            'email_verification_sent_at' => 'datetime',
        ];
    }

    /**
     * Only OSY accounts are checked, and only while the switch in
     * config/email_verification.php is on. SK and Maxima accounts are never blocked.
     */
    public function needsEmailVerification(): bool
    {
        return config('email_verification.enabled')
            && $this->role === 'osy'
            && $this->email_verified_at === null;
    }
}