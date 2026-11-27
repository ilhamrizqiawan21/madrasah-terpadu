<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Semester;
use App\Exceptions\AcademicYearGuardException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AcademicYearRequest;
use App\Http\Resources\AcademicYearResource;
use App\Models\AcademicYear;
use App\Models\User;
use App\Services\AcademicYears\AcademicYearService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function __construct(private readonly AcademicYearService $service) {}

    public function index(): Response
    {
        $years = AcademicYear::query()
            ->orderByDesc('name')
            ->orderByDesc('semester')
            ->paginate(25);

        return Inertia::render('admin/academic-years/index', [
            'academicYears' => AcademicYearResource::collection($years),
            'semesterOptions' => array_map(
                fn (Semester $semester): array => ['value' => $semester->value, 'label' => $semester->label()],
                Semester::cases(),
            ),
        ]);
    }

    public function store(AcademicYearRequest $request): RedirectResponse
    {
        $this->service->create($request->yearData(), $this->actor($request));

        return $this->done('Tahun ajaran berhasil ditambahkan.');
    }

    public function update(AcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $this->service->update($academicYear, $request->yearData(), $this->actor($request));

        return $this->done('Tahun ajaran berhasil diperbarui.');
    }

    public function activate(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $year = $this->service->activate($academicYear, $this->actor($request));

        return $this->done("{$year->label()} sekarang menjadi tahun ajaran aktif.");
    }

    public function destroy(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        try {
            $this->service->delete($academicYear, $this->actor($request));
        } catch (AcademicYearGuardException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        return $this->done('Tahun ajaran berhasil dihapus.');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
