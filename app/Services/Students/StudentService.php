<?php

declare(strict_types=1);

namespace App\Services\Students;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Satu-satunya penulis siswa dan enrollment (T-205).
 *
 * Siswa tidak dihapus dari aplikasi; statusnya diubah (nonaktif, lulus, pindah).
 * Rombel siswa hanya diatur untuk tahun ajaran aktif: enrollment tahun lain
 * tidak disentuh sehingga menjadi riwayat, dan pindah rombel di tahun yang sama
 * memperbarui satu baris enrollment itu dengan audit sebelum-sesudah.
 *
 * @phpstan-type StudentData array{
 *     nis: string,
 *     nisn: string|null,
 *     name: string,
 *     nickname: string|null,
 *     gender: string,
 *     birth_place: string|null,
 *     birth_date: string|null,
 *     address: string|null,
 *     family_card_number?: string|null,
 *     religion?: string|null,
 *     father_name?: string|null,
 *     father_occupation?: string|null,
 *     mother_name?: string|null,
 *     mother_occupation?: string|null,
 *     status: string,
 *     classroom_id: int|null
 * }
 */
final class StudentService
{
    public const DISK = 'local';

    private const PHOTO_DIRECTORY = 'students/photos';

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly StudentPhotoProcessor $photos,
    ) {}

    /**
     * @param  StudentData  $data
     */
    public function create(int $unitId, array $data, ?UploadedFile $photo, User $actor): Student
    {
        $photoPath = $photo === null ? null : $this->storePhoto($photo);

        try {
            return DB::transaction(function () use ($unitId, $data, $photoPath, $actor): Student {
                $student = Student::query()->create([
                    ...$this->attributes($data),
                    'unit_id' => $unitId,
                    'photo_path' => $photoPath,
                ]);

                $this->auditLogger->record(
                    action: 'created',
                    subject: $student,
                    after: $this->snapshot($student),
                    userId: $actor->id,
                );

                $this->syncActiveEnrollment($student, $data['classroom_id'], $actor);

                return $student;
            });
        } catch (Throwable $exception) {
            $this->deletePhoto($photoPath);

            throw $exception;
        }
    }

    /**
     * Unit siswa tetap; rombel yang dipilih berlaku untuk tahun ajaran aktif.
     *
     * @param  StudentData  $data
     */
    public function update(Student $student, array $data, ?UploadedFile $photo, bool $removePhoto, User $actor): Student
    {
        $oldPhoto = $student->photo_path;
        $newPhoto = $photo === null ? null : $this->storePhoto($photo);
        $photoChanged = $newPhoto !== null || ($removePhoto && $oldPhoto !== null);

        try {
            DB::transaction(function () use ($student, $data, $newPhoto, $photoChanged, $actor): void {
                $before = $this->snapshot($student);

                $student->fill($this->attributes($data));

                if ($photoChanged) {
                    $student->photo_path = $newPhoto;
                }

                if ($student->isDirty()) {
                    $student->save();

                    $this->auditLogger->record(
                        action: 'updated',
                        subject: $student,
                        before: $before,
                        after: $this->snapshot($student),
                        userId: $actor->id,
                    );
                }

                $this->syncActiveEnrollment($student, $data['classroom_id'], $actor);
            });
        } catch (Throwable $exception) {
            $this->deletePhoto($newPhoto);

            throw $exception;
        }

        // Berkas lama baru dihapus setelah jalur baru tersimpan, agar foto tidak hilang di tengah jalan.
        if ($photoChanged) {
            $this->deletePhoto($oldPhoto);
        }

        return $student;
    }

    public function setStatus(Student $student, StudentStatus $status, User $actor): Student
    {
        if ($student->status === $status) {
            return $student;
        }

        return DB::transaction(function () use ($student, $status, $actor): Student {
            $before = $student->status;
            $student->forceFill(['status' => $status])->save();

            $this->auditLogger->record(
                action: 'status_changed',
                subject: $student,
                before: ['status' => $before->value],
                after: ['status' => $status->value],
                userId: $actor->id,
            );

            return $student;
        });
    }

    /**
     * Rombel kosong berarti tidak mengubah apa pun: enrollment yang sudah ada
     * tidak dihapus dari sini (pengosongan ditolak di validasi).
     */
    private function syncActiveEnrollment(Student $student, ?int $classroomId, User $actor): void
    {
        if ($classroomId === null) {
            return;
        }

        $year = AcademicYear::query()->where('is_active', true)->first();
        $classroom = Classroom::query()->find($classroomId);

        // Validasi request sudah memeriksa ini; diulang karena service bisa dipanggil dari tempat lain (impor T-207).
        if (
            $year === null
            || $classroom === null
            || $classroom->academic_year_id !== $year->id
            || $classroom->unit_id !== $student->unit_id
        ) {
            throw new RuntimeException('Rombel harus milik unit siswa pada tahun ajaran aktif.');
        }

        $enrollment = Enrollment::query()
            ->with('classroom')
            ->where('student_id', $student->id)
            ->where('academic_year_id', $year->id)
            ->lockForUpdate()
            ->first();

        if ($enrollment === null) {
            $enrollment = Enrollment::query()->create([
                'student_id' => $student->id,
                'classroom_id' => $classroom->id,
                'academic_year_id' => $year->id,
            ]);

            $this->auditLogger->record(
                action: 'enrolled',
                subject: $enrollment,
                after: $this->enrollmentSnapshot($student, $year, $classroom),
                userId: $actor->id,
            );

            return;
        }

        if ($enrollment->classroom_id === $classroom->id) {
            return;
        }

        $before = $this->enrollmentSnapshot($student, $year, $enrollment->classroom);
        $enrollment->forceFill(['classroom_id' => $classroom->id])->save();

        $this->auditLogger->record(
            action: 'enrollment_moved',
            subject: $enrollment,
            before: $before,
            after: $this->enrollmentSnapshot($student, $year, $classroom),
            userId: $actor->id,
        );
    }

    private function storePhoto(UploadedFile $photo): string
    {
        $path = self::PHOTO_DIRECTORY.'/'.Str::random(40).'.jpg';

        if (! Storage::disk(self::DISK)->put($path, $this->photos->toJpeg($photo))) {
            throw new RuntimeException('Foto siswa gagal disimpan.');
        }

        return $path;
    }

    private function deletePhoto(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /**
     * @param  StudentData  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'nis' => $data['nis'],
            'nisn' => $data['nisn'],
            'name' => $data['name'],
            'nickname' => $data['nickname'],
            'gender' => $data['gender'],
            'birth_place' => $data['birth_place'],
            'birth_date' => $data['birth_date'],
            'address' => $data['address'],
            'family_card_number' => $data['family_card_number'] ?? null,
            'religion' => $data['religion'] ?? null,
            'father_name' => $data['father_name'] ?? null,
            'father_occupation' => $data['father_occupation'] ?? null,
            'mother_name' => $data['mother_name'] ?? null,
            'mother_occupation' => $data['mother_occupation'] ?? null,
            'status' => $data['status'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Student $student): array
    {
        return [
            'unit_id' => $student->unit_id,
            'nis' => $student->nis,
            'nisn' => $student->nisn,
            'name' => $student->name,
            'nickname' => $student->nickname,
            'gender' => $student->gender->value,
            'birth_place' => $student->birth_place,
            'birth_date' => $student->birth_date?->toDateString(),
            'status' => $student->status->value,
            // Alamat dan jalur foto tidak disalin ke audit; cukup tanda adanya foto.
            'has_photo' => $student->photo_path !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function enrollmentSnapshot(Student $student, AcademicYear $year, Classroom $classroom): array
    {
        return [
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'classroom_id' => $classroom->id,
            'classroom' => $classroom->name,
        ];
    }
}
