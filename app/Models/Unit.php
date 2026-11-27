<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Unit sekolah (MI/RA/TPQ). Ditulis hanya lewat App\Services\Units\UnitService atau UnitSeeder.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $head_user_id
 * @property array{check_in_opens_at: string, late_after: string, absent_after: string, check_out_opens_at: string, weekend_days?: list<int>} $attendance_settings
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['code', 'name', 'head_user_id', 'attendance_settings', 'is_active'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }
}
