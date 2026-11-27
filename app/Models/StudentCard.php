<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StudentCardKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Kartu presensi/kantin siswa (QR token acak 32 byte atau UID RFID ter-hash, T-501).
 * Token mentah tidak pernah disimpan; hanya `token_hash` SHA-256 (64 hex).
 *
 * @property int $id
 * @property int $student_id
 * @property StudentCardKind $kind
 * @property string $token_hash
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable|null $revoked_at
 * @property int|null $issued_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Student $student
 * @property-read User|null $issuer
 */
#[Fillable(['student_id', 'kind', 'token_hash', 'issued_at', 'revoked_at', 'issued_by'])]
class StudentCard extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => StudentCardKind::class,
            'issued_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @param  Builder<StudentCard>  $query
     * @return Builder<StudentCard>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
