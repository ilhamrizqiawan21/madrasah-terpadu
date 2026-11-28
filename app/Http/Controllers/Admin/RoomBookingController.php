<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\Unit;
use App\Models\User;
use App\Services\Academic\RoomBookingService;
use App\Support\AppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akademik > Peminjaman Ruangan & Lab. Bawaan menampilkan booking hari ini dan sesudahnya.
 */
class RoomBookingController extends Controller
{
    public function __construct(private readonly RoomBookingService $service) {}

    public function index(Request $request): Response
    {
        $unitId = $request->integer('unit') ?: null;
        $search = trim($request->string('search')->toString());
        $date = $request->date('date')?->toDateString();
        $today = now(AppTimezone::name())->toDateString();
        $user = $this->actor($request);

        $paginator = RoomBooking::query()
            ->with(['room.building:id,name,unit_id', 'user:id,name'])
            ->when($date !== null, fn (Builder $q) => $q->whereDate('used_on', $date), fn (Builder $q) => $q->whereDate('used_on', '>=', $today))
            ->when($unitId !== null, fn (Builder $q) => $q->whereHas('room.building', fn (Builder $b) => $b->where('unit_id', $unitId)))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $i) => $i
                ->whereHas('room', fn (Builder $r) => $r->whereLike('name', "%{$search}%"))
                ->orWhereHas('user', fn (Builder $u) => $u->whereLike('name', "%{$search}%"))))
            ->orderBy('used_on')->orderBy('starts_at')->orderBy('id')
            ->paginate(25)->withQueryString();

        $canManage = $user->can(Permission::ClassroomsManage->value);

        return Inertia::render('admin/academic/room-bookings', [
            'rows' => [
                'data' => array_values(array_map(fn (RoomBooking $b): array => [
                    'id' => $b->id, 'room' => $b->room->name, 'building' => $b->room->building->name,
                    'used_on' => $b->used_on->toDateString(), 'starts_at' => $b->starts_at, 'ends_at' => $b->ends_at,
                    'purpose' => $b->purpose, 'user' => $b->user->name,
                    'can_cancel' => $canManage || $b->user_id === $user->id,
                ], $paginator->items())),
                'meta' => [
                    'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
                ],
            ],
            'filters' => ['search' => $search, 'unit' => $unitId, 'date' => $date],
            'today' => $today,
            'unitOptions' => array_values(Unit::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])
                ->map(fn (Unit $u): array => ['value' => (string) $u->id, 'label' => $u->name])->all()),
            'roomOptions' => array_values(Room::query()->with('building:id,name,unit_id')->orderBy('building_id')->orderBy('name')->get()
                ->map(fn (Room $r): array => [
                    'value' => (string) $r->id, 'unit_id' => (string) $r->building->unit_id,
                    'label' => "{$r->building->name} → {$r->name} (kapasitas {$r->capacity})",
                ])->all()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $today = now(AppTimezone::name())->toDateString();
        $data = $request->validate([
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')],
            'used_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'purpose' => ['nullable', 'string', 'max:150'],
        ], [], ['room_id' => 'ruangan', 'used_on' => 'tanggal penggunaan', 'starts_at' => 'jam mulai', 'ends_at' => 'jam selesai', 'purpose' => 'keperluan']);

        $purpose = isset($data['purpose']) ? trim((string) $data['purpose']) : '';
        $this->service->book(
            Room::query()->findOrFail((int) $data['room_id']),
            (string) $data['used_on'], (string) $data['starts_at'], (string) $data['ends_at'],
            $purpose === '' ? null : $purpose,
            $this->actor($request),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ruangan berhasil dibooking.']);

        return back();
    }

    public function destroy(Request $request, RoomBooking $booking): RedirectResponse
    {
        $user = $this->actor($request);
        abort_unless($booking->user_id === $user->id || $user->can(Permission::ClassroomsManage->value), 404);
        $this->service->cancel($booking, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Booking dibatalkan.']);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
