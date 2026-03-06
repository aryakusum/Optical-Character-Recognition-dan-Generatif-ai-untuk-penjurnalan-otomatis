<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Model untuk Detail Jurnal (baris debit/credit)
class JournalLine extends Model
{
    protected $fillable = [
        'journal_id',
        'account_code',
        'account_name',
        'description',
        'debit',
        'credit',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    // Relasi ke jurnal induk
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
