<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GuardianRelation;
use Database\Factories\GuardianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Wali murid. Ditulis hanya lewat App\Services\Guardians\GuardianService.
 * Login memakai akun `users` terkait (username = nomor HP ternormalisasi).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $phone
 * @property GuardianRelation $relation
 * @property string|null $address
 * @property string|null $family_card_number
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Collection<int, Student> $students
 */
#[Fillable(['user_id', 'name', 'phone', 'relation', 'address', 'family_card_number'])]
class Guardian extends Model
{
    /** @use HasFactory<GuardianFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relation' => GuardianRelation::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Anak yang terhubung. Semua akses wali ke data siswa berawal dari relasi ini (ARCHITECTURE §9).
     *
     * @return BelongsToMany<Student, $this, GuardianStudent, 'link'>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)
            ->using(GuardianStudent::class)
            ->as('link')
            ->withPivot(['id', 'relation', 'is_primary']);
    }
}
