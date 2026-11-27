<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Membutuhkan relasi `enrollments.classroom` dan `enrollments.academicYear` yang sudah dimuat.
 *
 * @mixin Student
 */
class StudentResource extends JsonResource
{
    /**
     * @param  int|null  $viewedYearId  tahun ajaran yang sedang dilihat (kolom Rombel)
     * @param  int|null  $activeYearId  tahun ajaran aktif (rombel yang bisa diubah di form)
     */
    public function __construct(
        Student $resource,
        private readonly ?int $viewedYearId,
        private readonly ?int $activeYearId,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewed = $this->enrollments->firstWhere('academic_year_id', $this->viewedYearId);
        $active = $this->enrollments->firstWhere('academic_year_id', $this->activeYearId);

        return [
            'id' => $this->id,
            'nis' => $this->nis,
            'nisn' => $this->nisn,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'gender' => ['value' => $this->gender->value, 'label' => $this->gender->label()],
            'birth_place' => $this->birth_place,
            'birth_date' => $this->birth_date?->toDateString(),
            'address' => $this->address,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'classroom' => $viewed === null ? null : ['id' => $viewed->classroom->id, 'name' => $viewed->classroom->name],
            'active_classroom_id' => $active?->classroom_id,
            'enrollments' => $this->enrollments
                ->sortByDesc(fn (Enrollment $enrollment): string => $enrollment->academicYear->name.$enrollment->academicYear->semester->value)
                ->map(fn (Enrollment $enrollment): array => [
                    'id' => $enrollment->id,
                    'academic_year' => $enrollment->academicYear->label(),
                    'classroom' => $enrollment->classroom->name,
                ])
                ->values()
                ->all(),
            // Versi di URL memaksa browser memuat ulang setelah foto diganti.
            'photo_url' => $this->photo_path === null
                ? null
                : route('admin.students.photo', ['student' => $this->id, 'v' => substr(md5($this->photo_path), 0, 8)]),
        ];
    }
}
