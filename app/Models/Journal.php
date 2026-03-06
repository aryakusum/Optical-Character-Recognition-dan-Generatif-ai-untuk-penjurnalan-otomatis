<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Model untuk Jurnal Umum (General Journal)
class Journal extends Model
{
    // Field yang boleh diisi massal
    protected $fillable = [
        'journal_number',
        'transaction_date',
        'document_type',
        'document_number',
        'vendor',
        'description',
        'total_amount',
        'currency',
        'unit_id',
        'user_id',
        'status',
        'raw_data',
    ];

    // Casting tipe data
    protected $casts = [
        'transaction_date' => 'date',
        'total_amount' => 'decimal:2',
        'raw_data' => 'array',
    ];

    // ===================== RELASI =====================

    // Relasi ke detail baris jurnal
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    // Relasi ke unit kerja
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    // Relasi ke user pembuat
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ===================== HELPER =====================

    // Generate nomor jurnal otomatis format: JU-YYYYMM-XXXX
    public static function generateNumber(): string
    {
        $prefix = 'JU-' . date('Ym');

        // Cari nomor terakhir bulan ini
        $lastJournal = self::where('journal_number', 'like', $prefix . '%')
            ->orderBy('journal_number', 'desc')
            ->first();

        // Hitung nomor berikutnya
        if ($lastJournal) {
            $lastNumber = (int) substr($lastJournal->journal_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    // ===================== SCOPE =====================

    // Filter jurnal yang sudah diposting
    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    // Filter jurnal draft
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    // ===================== ACCESSOR =====================

    // Hitung total debit dari semua lines
    public function getTotalDebitAttribute(): float
    {
        return $this->lines->sum('debit');
    }

    // Hitung total credit dari semua lines
    public function getTotalCreditAttribute(): float
    {
        return $this->lines->sum('credit');
    }

    // Cek apakah jurnal balance (debit = credit)
    public function getIsBalancedAttribute(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.01;
    }
}
