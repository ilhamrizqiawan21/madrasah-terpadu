<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Exceptions\StaffUserGuardException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffUserRequest;
use App\Http\Requests\Admin\UpdateStaffUserStatusRequest;
use App\Http\Resources\StaffUserResource;
use App\Models\AcademicYear;
use App\Models\Unit;
use App\Models\User;
use App\Services\Users\StaffUserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StaffUserController extends Controller
{
    private const SORTABLE = ['name', 'username', 'last_login_at'];

    public function __construct(private readonly StaffUserService $service) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $search = trim((string) $request->query('search', ''));
        $role = (string) $request->query('role', '');
        $status = (string) $request->query('status', '');
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? (string) $request->query('sort') : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');

        $users = StaffUserService::staffQuery()
            ->with([
                'roles',
                'staffProfile.unit',
                'homeroomClassrooms' => fn ($query) => $query
                    ->with('unit')
                    ->when($activeYearId !== null, fn ($q) => $q->where('academic_year_id', $activeYearId))
                    ->where('is_active', true)
                    ->orderBy('name'),
            ])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $inner) use ($search): void {
                $inner->whereLike('name', "%{$search}%")
                    ->orWhereLike('username', "%{$search}%")
                    ->orWhereLike('phone', "%{$search}%");
            }))
            ->when(in_array($role, StaffUserService::staffRoleValues(), true), fn (Builder $query) => $query->role($role))
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => StaffUserResource::collection($users),
            'filters' => [
                'search' => $search,
                'role' => $role,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'roleOptions' => array_map(
                fn (Role $option): array => ['value' => $option->value, 'label' => $option->label()],
                Role::staff(),
            ),
            'unitOptions' => Unit::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get(['id', 'code', 'name'])
                ->map(fn (Unit $unit): array => [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                ])
                ->values(),
        ]);
    }

    public function store(StaffUserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $result = $this->service->create($request->staffData(), $this->actor($request));

        Inertia::flash([
            'toast' => ['type' => 'success', 'message' => 'Akun staf berhasil ditambahkan.'],
            'credentials' => [
                'name' => $result['user']->name,
                'username' => $result['user']->username,
                'password' => $result['password'],
            ],
        ]);

        return back();
    }

    public function update(StaffUserRequest $request, User $staffUser): RedirectResponse
    {
        Gate::authorize('update', $staffUser);

        try {
            $this->service->update($staffUser, $request->staffData(), $this->actor($request));
        } catch (StaffUserGuardException $exception) {
            return $this->rejected($exception);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun staf berhasil diperbarui.']);

        return back();
    }

    public function updateStatus(UpdateStaffUserStatusRequest $request, User $staffUser): RedirectResponse
    {
        Gate::authorize('updateStatus', $staffUser);

        $active = $request->boolean('is_active');

        try {
            $this->service->setActive($staffUser, $active, $this->actor($request));
        } catch (StaffUserGuardException $exception) {
            return $this->rejected($exception);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $active ? 'Akun staf diaktifkan kembali.' : 'Akun staf dinonaktifkan.',
        ]);

        return back();
    }

    public function resetPassword(Request $request, User $staffUser): RedirectResponse
    {
        Gate::authorize('resetPassword', $staffUser);

        $password = $this->service->resetPassword($staffUser, $this->actor($request));

        Inertia::flash([
            'toast' => ['type' => 'success', 'message' => 'Kata sandi berhasil direset.'],
            'credentials' => [
                'name' => $staffUser->name,
                'username' => $staffUser->username,
                'password' => $password,
            ],
        ]);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function rejected(StaffUserGuardException $exception): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

        return back();
    }
}
