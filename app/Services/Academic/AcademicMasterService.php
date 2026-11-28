<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\ClassGroup;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\Major;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penulis tunggal master Tingkat Kelas, Rombel (nama kelompok), dan Jurusan.
 */
final class AcademicMasterService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, int|string>  $data
     */
    public function save(GradeLevel|ClassGroup|Major $model, array $data, User $actor): GradeLevel|ClassGroup|Major
    {
        return DB::transaction(function () use ($model, $data, $actor): GradeLevel|ClassGroup|Major {
            $before = $model->exists ? $model->only(array_keys($data)) : null;
            $model->fill($data)->save();
            $this->audit->record($this->action($model, $before === null ? 'created' : 'updated'), $model, $before, $model->only(array_keys($data)), $actor->id);

            return $model;
        });
    }

    public function delete(GradeLevel|ClassGroup|Major $model, User $actor): void
    {
        // Rombel menyimpan tingkat sebagai teks; tingkat yang masih dipakai rombel tidak boleh hilang.
        if ($model instanceof GradeLevel && Classroom::query()->where('unit_id', $model->unit_id)->where('grade', $model->name)->exists()) {
            throw ValidationException::withMessages(['master' => 'Tingkat ini masih dipakai rombel. Ubah rombelnya dulu.']);
        }

        DB::transaction(function () use ($model, $actor): void {
            $this->audit->record($this->action($model, 'deleted'), $model, $model->only(['unit_id', 'name']), null, $actor->id);
            $model->delete();
        });
    }

    private function action(GradeLevel|ClassGroup|Major $model, string $verb): string
    {
        $prefix = match (true) {
            $model instanceof GradeLevel => 'grade_level',
            $model instanceof ClassGroup => 'class_group',
            default => 'major',
        };

        return "{$prefix}.{$verb}";
    }
}
