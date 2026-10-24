<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Models\Concerns\HasTwoFactor;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * Admin MFA (TOTP) lives in HasTwoFactor and is off unless config('mfa.enabled'). TODO: BUSINESS DECISION REQUIRED - roles, factor types, grace period, lost-device recovery (see config/mfa.php).
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTwoFactor, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'security_stamp',
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
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'access_permissions' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $user): void {
            if ($user->isDirty(['password', 'role', 'is_active', 'access_permissions', 'mfi_institution_id', 'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes'])) {
                $user->security_stamp = Str::random(48);
                $user->remember_token = Str::random(60);
            }
        });
    }

    public function permission(string $key, bool $default): bool
    {
        return $this->access_permissions[$key] ?? $default;
    }

    public function effectivePermissions(): array
    {
        return ['view' => $this->permission('view', true), 'record' => $this->permission('record', $this->role === UserRole::SmeUser),
            'approve' => $this->permission('approve', $this->role === UserRole::BeaconAdmin), 'export' => $this->permission('export', true),
            'require_approval' => $this->permission('require_approval', false)];
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }
}
