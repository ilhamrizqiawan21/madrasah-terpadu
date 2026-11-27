<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Pembuat PDF kartu QR siswa ukuran ID-1 (85,6 × 54 mm), 10 kartu per lembar A4 (T-503).
 * Memakai bacon/bacon-qr-code (sudah terpasang via Fortify) + barryvdh/laravel-dompdf.
 */
final class StudentCardPdfService
{
    public function __construct(
        private readonly StudentCardService $cards,
        private readonly SettingsRepository $settings,
    ) {}

    public function renderForStudent(Student $student, User $actor): string
    {
        $student->load(['unit']);

        /** @var Collection<int, Student> $students */
        $students = new Collection([$student]);

        return $this->renderForStudents($students, $actor);
    }

    public function renderForClassroom(Classroom $classroom, User $actor): string
    {
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id')
            ?? $classroom->academic_year_id;

        $students = Student::query()
            ->with([
                'unit',
                'enrollments' => fn (HasMany $q) => $q
                    ->where('academic_year_id', $activeYearId)
                    ->with('classroom'),
            ])
            ->where('status', StudentStatus::Active->value)
            ->whereHas('enrollments', fn ($q) => $q
                ->where('academic_year_id', $activeYearId)
                ->where('classroom_id', $classroom->id))
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $this->renderForStudents($students, $actor);
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    public function renderForStudents(Collection $students, User $actor): string
    {
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');

        if ($activeYearId !== null) {
            $students->loadMissing([
                'unit',
                'enrollments' => fn (HasMany $q) => $q
                    ->where('academic_year_id', $activeYearId)
                    ->with('classroom'),
            ]);
        } else {
            $students->loadMissing('unit');
        }

        $identity = $this->settings->identity();
        $primaryColor = $identity['primary_color'] ?? '#166534';
        $schoolName = $identity['app_name'] ?: $identity['foundation_name'];

        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle(160, 0),
                new SvgImageBackEnd,
            ),
        );

        $cards = [];

        foreach ($students as $student) {
            $issued = $this->cards->issueQr($student, $actor);
            $qrSvg = $writer->writeString($issued['token']);

            $cards[] = [
                'name' => $student->name,
                'nis' => $student->nis,
                'unit' => $student->unit->code,
                'classroom' => $student->enrollments->first()?->classroom->name ?? '-',
                'photo_data_uri' => $this->photoDataUri($student->photo_path),
                'initials' => $this->initials($student->name),
                'qr_data_uri' => 'data:image/svg+xml;base64,'.base64_encode($qrSvg),
            ];
        }

        return Pdf::loadView('pdf.student-cards', [
            'cards' => $cards,
            'pages' => array_chunk($cards, 10),
            'schoolName' => $schoolName,
            'primaryColor' => $primaryColor,
        ])
            ->setPaper('a4', 'portrait')
            ->output();
    }

    private function photoDataUri(?string $photoPath): ?string
    {
        if ($photoPath === null || $photoPath === '' || ! Storage::disk('local')->exists($photoPath)) {
            return null;
        }

        $bytes = Storage::disk('local')->get($photoPath);

        if ($bytes === null || $bytes === '') {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode($bytes);
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return mb_strtoupper(
            collect($parts)
                ->filter(fn (string $part): bool => $part !== '')
                ->take(2)
                ->map(fn (string $part): string => mb_substr($part, 0, 1))
                ->implode(''),
        );
    }
}
