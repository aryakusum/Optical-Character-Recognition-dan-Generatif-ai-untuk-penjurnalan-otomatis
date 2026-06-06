<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Journal extends Model
{
    const STATUS_DRAFT = 'draft';
    const STATUS_VERIFIED_UNIT = 'verified_unit';
    const STATUS_VERIFIED_FINANCE = 'verified_finance';
    const STATUS_POSTED = 'posted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_VOID = 'void';

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
        'document_path',
        'document_original_name',
        'verified_unit_at',
        'verified_unit_by',
        'verified_unit_notes',
        'verified_finance_at',
        'verified_finance_by',
        'verified_finance_notes',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'total_amount' => 'decimal:2',
        'raw_data' => 'array',
        'verified_unit_at' => 'datetime',
        'verified_finance_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedByUnit(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_unit_by');
    }

    public function verifiedByFinance(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_finance_by');
    }

    public static function generateNumber(): string
    {
        $prefix = 'JU-' . date('Ym');

        $lastJournal = self::where('journal_number', 'like', $prefix . '%')
            ->orderBy('journal_number', 'desc')
            ->first();

        if ($lastJournal) {
            $lastNumber = (int) substr($lastJournal->journal_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_VERIFIED_UNIT => 'Diverifikasi Unit',
            self::STATUS_VERIFIED_FINANCE => 'Diverifikasi Keuangan',
            self::STATUS_POSTED => 'Posted',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_VOID => 'Void',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'warning',
            self::STATUS_VERIFIED_UNIT => 'info',
            self::STATUS_VERIFIED_FINANCE => 'primary',
            self::STATUS_POSTED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_VOID => 'secondary',
            default => 'secondary',
        };
    }

    public function hasDocument(): bool
    {
        return !empty($this->document_path);
    }

    public function scopePosted($query)
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeNeedUnitVerification($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeNeedFinanceVerification($query)
    {
        return $query->where('status', self::STATUS_VERIFIED_UNIT);
    }

    public function getTotalDebitAttribute(): float
    {
        return $this->lines->sum('debit');
    }

    public function getTotalCreditAttribute(): float
    {
        return $this->lines->sum('credit');
    }

    public function getIsBalancedAttribute(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.01;
    }
}
