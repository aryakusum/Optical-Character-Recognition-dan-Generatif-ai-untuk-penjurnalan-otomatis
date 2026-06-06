<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    const ROLE_STAFF_UNIT = 'staff_unit';
    const ROLE_VERIFIKATOR = 'verifikator';
    const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'name',
        'email',
        'password',
        'unit_id',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function isStaffUnit(): bool
    {
        return $this->role === self::ROLE_STAFF_UNIT;
    }

    public function isVerifikator(): bool
    {
        return $this->role === self::ROLE_VERIFIKATOR;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_STAFF_UNIT => 'Staff Unit',
            self::ROLE_VERIFIKATOR => 'Keuangan Pusat',
            self::ROLE_ADMIN => 'Administrator',
            default => ucfirst($this->role ?? 'staff_unit'),
        };
    }
}
