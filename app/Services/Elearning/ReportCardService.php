<?php

declare(strict_types=1);

namespace App\Services\Elearning;

use App\Models\AcademicYear;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Layanan unggah, terbit, dan hapus PDF E-Rapor (T-1604).
 */
final class ReportCardService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function upload(
        User $actor,
        Student $student,
        AcademicYear $academicYear,
        string $semester,
        UploadedFile $file,
        bool $publishNow = false,
    ): ReportCard {
        $directory = "report-cards/{$academicYear->id}/{$semester}";
        $filename = "{$student->id}-".Str::uuid().'.pdf';
        $storedPath = $file->storeAs($directory, $filename, 'local');

        if ($storedPath === false) {
            throw new \RuntimeException('Gagal menyimpan berkas PDF rapor.');
        }

        try {
            return DB::transaction(function () use ($actor, $student, $academicYear, $semester, $storedPath, $publishNow): ReportCard {
                $existing = ReportCard::query()
                    ->where('student_id', $student->id)
                    ->where('academic_year_id', $academicYear->id)
                    ->where('semester', $semester)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $oldPath = $existing->file_path;
                    $existing->forceFill([
                        'file_path' => $storedPath,
                        'uploaded_by' => $actor->id,
                        'published_at' => $publishNow ? ($existing->published_at ?? now()) : $existing->published_at,
                    ])->save();

                    $this->audit->record('report_card.updated', $existing, null, [
                        'student_id' => $student->id,
                        'academic_year_id' => $academicYear->id,
                        'semester' => $semester,
                        'published' => $existing->published_at !== null,
                    ], $actor->id);

                    DB::afterCommit(static function () use ($oldPath): void {
                        Storage::disk('local')->delete($oldPath);
                    });

                    return $existing;
                }

                $created = ReportCard::query()->create([
                    'student_id' => $student->id,
                    'academic_year_id' => $academicYear->id,
                    'semester' => $semester,
                    'file_path' => $storedPath,
                    'published_at' => $publishNow ? now() : null,
                    'uploaded_by' => $actor->id,
                ]);

                $this->audit->record('report_card.uploaded', $created, null, [
                    'student_id' => $student->id,
                    'academic_year_id' => $academicYear->id,
                    'semester' => $semester,
                    'published' => $created->published_at !== null,
                ], $actor->id);

                return $created;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($storedPath);

            throw $e;
        }
    }

    public function setPublished(User $actor, ReportCard $reportCard, bool $published): ReportCard
    {
        return DB::transaction(function () use ($actor, $reportCard, $published): ReportCard {
            $reportCard->forceFill([
                'published_at' => $published ? ($reportCard->published_at ?? now()) : null,
            ])->save();

            $this->audit->record(
                $published ? 'report_card.published' : 'report_card.unpublished',
                $reportCard,
                null,
                [
                    'student_id' => $reportCard->student_id,
                    'academic_year_id' => $reportCard->academic_year_id,
                    'semester' => $reportCard->semester,
                ],
                $actor->id,
            );

            return $reportCard;
        });
    }

    public function delete(User $actor, ReportCard $reportCard): void
    {
        DB::transaction(function () use ($actor, $reportCard): void {
            $path = $reportCard->file_path;

            $this->audit->record('report_card.deleted', $reportCard, null, [
                'student_id' => $reportCard->student_id,
                'academic_year_id' => $reportCard->academic_year_id,
                'semester' => $reportCard->semester,
            ], $actor->id);

            $reportCard->delete();

            DB::afterCommit(static function () use ($path): void {
                Storage::disk('local')->delete($path);
            });
        });
    }
}
