<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UnitRequest;
use App\Http\Requests\Admin\UpdateUnitStatusRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use App\Models\User;
use App\Services\Units\UnitService;
use App\Support\AttendanceHours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function __construct(private readonly UnitService $service) {}

    public function index(): Response
    {
        // Jumlah unit kecil (MI/RA/TPQ), jadi tanpa paginasi.
        $units = Unit::query()->orderBy('code')->get();

        return Inertia::render('admin/settings/units', [
            'units' => UnitResource::collection($units)->resolve(),
            'defaultAttendance' => AttendanceHours::defaults(),
        ]);
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        $this->service->create($request->unitData(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit berhasil ditambahkan.']);

        return back();
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        $this->service->update($unit, $request->unitData(), $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit berhasil diperbarui.']);

        return back();
    }

    public function updateStatus(UpdateUnitStatusRequest $request, Unit $unit): RedirectResponse
    {
        $active = $request->boolean('is_active');

        $this->service->setActive($unit, $active, $this->actor($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $active ? 'Unit diaktifkan kembali.' : 'Unit dinonaktifkan.',
        ]);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
