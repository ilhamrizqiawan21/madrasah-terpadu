<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Pos akun kas (COA) per unit: `in` = pendapatan/sumber dana, `out` = pengeluaran (ADR-030).
 *
 * @property int $id
 * @property int $unit_id
 * @property string|null $code
 * @property string $name
 * @property string $direction in|out
 * @property string|null $system_key
 * @property bool $is_active
 * @property-read Unit $unit
 */
#[Fillable(['unit_id', 'code', 'name', 'direction', 'system_key', 'is_active'])]
class CashAccount extends Model
{
    public const string IN = 'in';

    public const string OUT = 'out';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Pos pendapatan yang boleh membiayai pos pengeluaran ini (Alokasi Dana). Kosong = semua boleh.
     *
     * @return BelongsToMany<CashAccount, $this>
     */
    public function allowedFundingAccounts(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'cash_account_allocations', 'expense_account_id', 'income_account_id')->withTimestamps();
    }

    public function label(): string
    {
        return $this->code !== null && $this->code !== '' ? "{$this->code} · {$this->name}" : $this->name;
    }
}
