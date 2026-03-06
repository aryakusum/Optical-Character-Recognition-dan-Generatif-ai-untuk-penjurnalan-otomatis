<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Model untuk Chart of Accounts (Daftar Akun)
class Account extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'type',          // Asset, Liability, Equity, Revenue, Expense
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relasi ke units yang menggunakan akun ini
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'unit_accounts')
            ->withTimestamps();
    }

    // Scope: filter akun aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope: filter berdasarkan tipe akun
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
