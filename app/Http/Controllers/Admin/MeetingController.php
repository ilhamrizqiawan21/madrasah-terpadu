<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\MeetingAudience;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\MeetingRequest;
use App\Models\Meeting;
use App\Models\Unit;
use App\Models\User;
use App\Services\Users\MeetingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Undangan rapat & notulen (Pengguna > Undangan Rapat, alur aplikasi lama).
 */
class MeetingController extends Controller
{
    private const array SORTABLE = ['held_on', 'title'];

    public function __construct(private readonly MeetingService $service) {}

    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $unitId = $request->integer('unit') ?: null;
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? (string) $request->query('sort') : 'held_on';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $meetings = Meeting::query()
            ->with('unit:id,code,name')
            ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
            ->when($search !== '', fn (Builder $q) => $q->whereLike('title', "%{$search}%"))
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/users/meetings', [
            'meetings' => [
                'data' => $meetings->getCollection()->map(fn (Meeting $m): array => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'held_on' => $m->held_on->toDateString(),
                    'unit_id' => $m->unit_id,
                    'unit_name' => $m->unit?->name,
                    'audience' => $m->audience->value,
                    'audience_label' => $m->audience->label(),
                    'minutes' => $m->minutes,
                ])->values()->all(),
                'meta' => [
                    'current_page' => $meetings->currentPage(),
                    'last_page' => $meetings->lastPage(),
                    'per_page' => $meetings->perPage(),
                    'total' => $meetings->total(),
                    'from' => $meetings->firstItem(),
                    'to' => $meetings->lastItem(),
                ],
            ],
            'filters' => ['search' => $search, 'unit' => $unitId, 'sort' => $sort, 'direction' => $direction],
            'unitOptions' => Unit::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])
                ->map(fn (Unit $u): array => ['value' => (string) $u->id, 'label' => $u->name])->values()->all(),
            'audienceOptions' => array_map(
                fn (MeetingAudience $a): array => ['value' => $a->value, 'label' => $a->label()],
                MeetingAudience::cases(),
            ),
        ]);
    }

    public function store(MeetingRequest $request): RedirectResponse
    {
        $this->service->create($request->meetingData(), $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agenda rapat dijadwalkan.']);

        return back();
    }

    public function update(MeetingRequest $request, Meeting $meeting): RedirectResponse
    {
        $this->service->update($meeting, $request->meetingData(), $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agenda rapat diperbarui.']);

        return back();
    }

    public function destroy(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->service->delete($meeting, $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agenda rapat dihapus.']);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
