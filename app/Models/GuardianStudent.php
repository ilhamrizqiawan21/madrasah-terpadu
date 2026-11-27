<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GuardianRelation;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Hubungan wali–anak. Tepat satu wali utama per siswa dijaga GuardianService.
 *
 * @property int $id
 * @property int $guardian_id
 * @property int $student_id
 * @property GuardianRelation $relation
 * @property bool $is_primary
 */
class GuardianStudent extends Pivot
{
    public $incrementing = true;

    public $timestamps = false;

    protected $table = 'guardian_student';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relation' => GuardianRelation::class,
            'is_primary' => 'boolean',
        ];
    }
}
