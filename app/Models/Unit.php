<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Model untuk Unit Kerja (departemen/cabang)
class Unit extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',          // department, branch, project
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relasi ke users di unit ini
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Relasi ke akun yang diizinkan untuk unit ini
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'unit_accounts')
            ->withTimestamps();
    }

    // Scope: filter unit aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
