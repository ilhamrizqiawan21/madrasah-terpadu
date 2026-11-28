<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Dompet kantin siswa (T-801, ADR-006). Kolom `balance` adalah cache ledger,
 * hanya boleh diperbarui lewat App\Services\Wallet\WalletService dalam transaksi + lockForUpdate.
 *
 * @property int $id
 * @property int $student_id
 * @property int $balance
 * @property int $daily_limit
 * @property bool $is_frozen
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Student $student
 * @property-read Collection<int, WalletEntry> $entries
 */
#[Fillable(['student_id', 'balance', 'daily_limit', 'is_frozen'])]
class Wallet extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'daily_limit' => 'integer',
            'is_frozen' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return HasMany<WalletEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(WalletEntry::class);
    }
}
