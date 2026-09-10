<?php

namespace Cms\Auth\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * A back-office administrator.
 *
 * Authenticates on the `admin` guard only, so an Admin can never sign in as a
 * site user and a site user can never reach the panel.
 */
class Admin extends Authenticatable implements FilamentUser, HasName
{
    use Notifiable;

    protected $table = 'admins';

    protected $fillable = [
        'firstname',
        'lastname',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $admin) {
            $admin->uuid ??= Str::uuid()->toString();
        });
    }

    /**
     * Gate panel access on the account still being active, so revoking someone
     * is a checkbox rather than a deletion.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    public function getFilamentName(): string
    {
        return trim("{$this->firstname} {$this->lastname}") ?: $this->email;
    }
}
