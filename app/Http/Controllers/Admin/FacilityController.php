<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use App\Services\Academic\FacilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akademik > Gedung dan Ruangan & Lab (alur aplikasi lama).
 */
class FacilityController extends Controller
{
    public function __construct(private readonly FacilityService $service) {}

    public function buildings(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $unitId = $request->integer('unit') ?: null;

        $buildings = Building::query()
            ->with('unit:id,code,name')
            ->withCount('rooms')
            ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $i) => $i->whereLike('name', "%{$search}%")->orWhereLike('code', "%{$search}%")))
            ->orderBy('unit_id')->orderBy('code')->orderBy('id')
            ->paginate(25)->withQueryString();

        return Inertia::render('admin/academic/buildings', [
            'buildings' => $this->page($buildings, fn (Building $b): array => [
                'id' => $b->id, 'unit_id' => $b->unit_id, 'unit_name' => $b->unit->name,
                'code' => $b->code, 'name' => $b->name, 'rooms_count' => $b->rooms_count,
            ]),
            'filters' => ['search' => $search, 'unit' => $unitId],
            'unitOptions' => $this->units(),
        ]);
    }

    public function storeBuilding(Request $request): RedirectResponse
    {
        $this->service->saveBuilding(null, $this->buildingData($request, null), $this->actor($request));

        return $this->done('Gedung ditambahkan.');
    }

    public function updateBuilding(Request $request, Building $building): RedirectResponse
    {
        $this->service->saveBuilding($building, $this->buildingData($request, $building), $this->actor($request));

        return $this->done('Gedung diperbarui.');
    }

    public function destroyBuilding(Request $request, Building $building): RedirectResponse
    {
        $this->service->deleteBuilding($building, $this->actor($request));

        return $this->done('Gedung dihapus.');
    }

    public function rooms(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $buildingId = $request->integer('building') ?: null;

        $rooms = Room::query()
            ->with('building:id,code,name,unit_id', 'building.unit:id,code')
            ->when($buildingId !== null, fn (Builder $q) => $q->where('building_id', $buildingId))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $i) => $i->whereLike('name', "%{$search}%")->orWhereLike('code', "%{$search}%")))
            ->orderBy('building_id')->orderBy('code')->orderBy('id')
            ->paginate(25)->withQueryString();

        return Inertia::render('admin/academic/rooms', [
            'rooms' => $this->page($rooms, fn (Room $r): array => [
                'id' => $r->id, 'building_id' => $r->building_id,
                'building_label' => "{$r->building->code} · {$r->building->name} ({$r->building->unit->code})",
                'code' => $r->code, 'name' => $r->name, 'capacity' => $r->capacity,
            ]),
            'filters' => ['search' => $search, 'building' => $buildingId],
            'buildingOptions' => Building::query()->with('unit:id,code')->orderBy('unit_id')->orderBy('code')->get()
                ->map(fn (Building $b): array => ['value' => (string) $b->id, 'label' => "{$b->code} · {$b->name} ({$b->unit->code})"])->values()->all(),
        ]);
    }

    public function storeRoom(Request $request): RedirectResponse
    {
        $this->service->saveRoom(null, $this->roomData($request, null), $this->actor($request));

        return $this->done('Ruangan ditambahkan.');
    }

    public function updateRoom(Request $request, Room $room): RedirectResponse
    {
        $this->service->saveRoom($room, $this->roomData($request, $room), $this->actor($request));

        return $this->done('Ruangan diperbarui.');
    }

    public function destroyRoom(Request $request, Room $room): RedirectResponse
    {
        $this->service->deleteRoom($room, $this->actor($request));

        return $this->done('Ruangan dihapus.');
    }

    /**
     * @return array{unit_id: int, code: string, name: string}
     */
    private function buildingData(Request $request, ?Building $building): array
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'code' => ['required', 'string', 'max:20', Rule::unique('buildings', 'code')->where('unit_id', $request->integer('unit_id'))->ignore($building?->id)],
            'name' => ['required', 'string', 'max:100'],
        ], [], ['unit_id' => 'unit', 'code' => 'kode gedung', 'name' => 'nama gedung']);

        return ['unit_id' => (int) $data['unit_id'], 'code' => strtoupper(trim((string) $data['code'])), 'name' => trim((string) $data['name'])];
    }

    /**
     * @return array{building_id: int, code: string, name: string, capacity: int}
     */
    private function roomData(Request $request, ?Room $room): array
    {
        $data = $request->validate([
            'building_id' => ['required', 'integer', Rule::exists('buildings', 'id')],
            'code' => ['required', 'string', 'max:20', Rule::unique('rooms', 'code')->where('building_id', $request->integer('building_id'))->ignore($room?->id)],
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:0', 'max:1000'],
        ], [], ['building_id' => 'gedung induk', 'code' => 'kode ruang', 'name' => 'nama ruangan', 'capacity' => 'kapasitas kursi']);

        return [
            'building_id' => (int) $data['building_id'],
            'code' => strtoupper(trim((string) $data['code'])),
            'name' => trim((string) $data['name']),
            'capacity' => (int) $data['capacity'],
        ];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @param  callable(TModel): array<string, mixed>  $map
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|null>}
     */
    private function page($paginator, callable $map): array
    {
        return [
            'data' => array_values(array_map($map, $paginator->items())),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function units(): array
    {
        return array_values(Unit::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])
            ->map(fn (Unit $u): array => ['value' => (string) $u->id, 'label' => $u->name])->all());
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
