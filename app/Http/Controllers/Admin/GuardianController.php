<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\GuardianRelation;
use App\Enums\StudentStatus;
use App\Exceptions\GuardianAccountException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuardianRequest;
use App\Http\Requests\Admin\UpdateGuardianStatusRequest;
use App\Http\Resources\GuardianResource;
use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Guardians\GuardianService;
use App\Services\Settings\SettingsRepository;
use App\Support\Branding;
use App\Support\PhoneNumber;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GuardianController extends Controller
{
    private const ALL_STATUSES = 'all';

    private const STATUSES = ['active', 'inactive', self::ALL_STATUSES];

    public function __construct(
        private readonly GuardianService $service,
        private readonly SettingsRepository $settings,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Guardian::class);

        $units = Unit::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $context = SchoolContext::resolve($request->session());

        // Bawaan saringan unit = unit di topbar; wali tampil bila punya anak di unit itu.
        $unit = $units->first(fn (Unit $unit): bool => $unit->id === $request->integer('unit_id'))
            ?? $units->first(fn (Unit $unit): bool => $unit->code === $context['unit']);
        $yearId = $context['academicYearId'];

        $status = in_array($request->query('status'), self::STATUSES, true) ? (string) $request->query('status') : 'active';
        $search = trim((string) $request->query('search', ''));
        $phoneSearch = PhoneNumber::normalize($search);

        $guardians = Guardian::query()
            ->with([
                'user.roles',
                'students' => fn (BelongsToMany $query) => $query->orderBy('name')->orderBy('students.id'),
                'students.enrollments' => fn (HasMany $query) => $query->where('academic_year_id', $yearId)->with('classroom'),
            ])
            ->when(
                $unit === null,
                fn (Builder $query) => $query->whereKey([]),
                fn (Builder $query) => $query->whereHas('students', fn (Builder $students) => $students->where('unit_id', $unit?->id)),
            )
            ->when($status !== self::ALL_STATUSES, fn (Builder $query) => $query->whereHas(
                'user',
                fn (Builder $user) => $user->where('is_active', $status === 'active'),
            ))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search, $phoneSearch): void {
                $inner->whereLike('name', "%{$search}%")
                    ->orWhereLike('phone', '%'.($phoneSearch ?? $search).'%')
                    ->orWhereHas('students', fn (Builder $students) => $students
                        ->whereLike('name', "%{$search}%")
                        ->orWhereLike('nis', "%{$search}%"));
            }))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $identity = $this->settings->identity();

        return Inertia::render('admin/guardians/index', [
            'guardians' => GuardianResource::collection($guardians),
            'filters' => [
                'unit_id' => $unit?->id,
                'status' => $status,
                'search' => $search,
            ],
            'unitHasGuardians' => $unit !== null && Guardian::query()
                ->whereHas('students', fn (Builder $students) => $students->where('unit_id', $unit->id))
                ->exists(),
            'academicYearLabel' => $context['academicYear'],
            'unitOptions' => $units->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
            ])->values(),
            'relationOptions' => GuardianRelation::options(),
            'maxChildren' => GuardianRequest::MAX_CHILDREN,
            'canSendTestNotification' => $this->actor($request)->can('sendTestNotification', Guardian::class),
            // Kop "surat akun wali" yang dicetak dari browser.
            'letter' => [
                'foundation_name' => $identity['foundation_name'],
                'app_name' => $identity['app_name'],
                'address' => $identity['address'],
                'logo_url' => Branding::assetUrl('logo', $identity['logo_path']),
                'login_url' => route('login'),
            ],
        ]);
    }

    /**
     * Pemilih anak di dialog wali: siswa aktif semua unit (anak bisa beda unit), cari nama/NIS.
     */
    public function searchStudents(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Guardian::class);

        $search = trim((string) $request->query('q', ''));

        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');

        $students = Student::query()
            ->with([
                'unit:id,code',
                'enrollments' => fn (HasMany $query) => $query->where('academic_year_id', $activeYearId)->with('classroom:id,name'),
            ])
            ->where('status', StudentStatus::Active)
            ->where(fn (Builder $query) => $query->whereLike('name', "%{$search}%")->orWhereLike('nis', "%{$search}%"))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(10)
            ->get(['id', 'unit_id', 'name', 'nis']);

        return response()->json([
            'data' => $students->map(fn (Student $student): array => [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                'unit' => $student->unit->code,
                'classroom' => $student->enrollments->first()?->classroom->name,
            ])->values(),
        ]);
    }

    public function store(GuardianRequest $request): RedirectResponse
    {
        Gate::authorize('create', Guardian::class);

        try {
            $result = $this->service->create($request->guardianData(), $this->actor($request));
        } catch (GuardianAccountException $exception) {
            return $this->rejected($exception);
        }

        $guardian = $result['guardian'];

        if ($result['password'] === null) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => $result['outcome'] === GuardianService::OUTCOME_LINKED_EXISTING
                    ? "Nomor HP sudah terdaftar atas nama {$guardian->name}. Anak ditautkan ke akun wali itu."
                    : "Akun staf {$guardian->user->name} kini juga akun wali dan tetap memakai kata sandinya sendiri.",
            ]);

            return back();
        }

        Inertia::flash([
            'toast' => ['type' => 'success', 'message' => 'Wali berhasil ditambahkan.'],
            'credentials' => $this->credentials($guardian, $result['password']),
        ]);

        return back();
    }

    public function update(GuardianRequest $request, Guardian $guardian): RedirectResponse
    {
        Gate::authorize('update', $guardian);

        $this->service->update($guardian, $request->guardianData(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data wali berhasil diperbarui.']);

        return back();
    }

    public function updateStatus(UpdateGuardianStatusRequest $request, Guardian $guardian): RedirectResponse
    {
        Gate::authorize('update', $guardian);

        $active = $request->boolean('is_active');

        try {
            $this->service->setActive($guardian, $active, $this->actor($request));
        } catch (GuardianAccountException $exception) {
            return $this->rejected($exception);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $active ? "Akun {$guardian->name} diaktifkan kembali." : "Akun {$guardian->name} dinonaktifkan.",
        ]);

        return back();
    }

    public function resetPassword(Request $request, Guardian $guardian): RedirectResponse
    {
        Gate::authorize('update', $guardian);

        try {
            $password = $this->service->resetPassword($guardian, $this->actor($request));
        } catch (GuardianAccountException $exception) {
            return $this->rejected($exception);
        }

        Inertia::flash([
            'toast' => ['type' => 'success', 'message' => 'Kata sandi berhasil direset.'],
            'credentials' => $this->credentials($guardian, $password),
        ]);

        return back();
    }

    public function sendTestNotification(Request $request, Guardian $guardian): RedirectResponse
    {
        Gate::authorize('sendTestNotification', Guardian::class);

        try {
            $subscriptionCount = $this->service->sendTestNotification($guardian, $this->actor($request));
        } catch (GuardianAccountException $exception) {
            return $this->rejected($exception);
        }

        $message = $subscriptionCount > 0
            ? "Notifikasi uji dikirim ke {$guardian->name} ({$subscriptionCount} perangkat push aktif)."
            : "Notifikasi uji dikirim ke kotak masuk {$guardian->name}. Belum ada perangkat push terdaftar.";

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);

        return back();
    }

    /**
     * Isi surat akun wali. Kata sandi hanya ada di flash satu kali ini.
     *
     * @return array<string, mixed>
     */
    private function credentials(Guardian $guardian, string $password): array
    {
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');

        $guardian->load([
            'user',
            'students' => fn (BelongsToMany $query) => $query->orderBy('name')->orderBy('students.id'),
            'students.enrollments' => fn (HasMany $query) => $query->where('academic_year_id', $activeYearId)->with('classroom'),
        ]);

        return [
            'name' => $guardian->name,
            'username' => $guardian->user->username,
            'password' => $password,
            'relation' => $guardian->relation->label(),
            'children' => $guardian->students->map(fn (Student $student): array => [
                'name' => $student->name,
                'classroom' => $student->enrollments->first()?->classroom->name,
            ])->values()->all(),
        ];
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function rejected(GuardianAccountException $exception): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

        return back();
    }
}
