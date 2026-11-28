<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\DiscountKind;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\StoreStudentDiscountRequest;
use App\Http\Requests\Admin\Billing\UpdateStudentDiscountRequest;
use App\Models\FeeType;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\User;
use App\Services\Billing\StudentDiscountService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StudentDiscountController extends Controller
{
    public function __construct(
        private readonly StudentDiscountService $service,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', StudentDiscount::class);

        $search = trim((string) $request->string('search'));
        $feeTypeId = $request->filled('fee_type_id') ? $request->integer('fee_type_id') : null;
        $status = $request->string('status')->toString();

        $discounts = StudentDiscount::query()
            ->with(['student:id,nis,name,unit_id', 'student.unit:id,code', 'feeType:id,name'])
            ->when($search !== '', function (Builder $q) use ($search): void {
                $q->whereHas('student', function (Builder $sq) use ($search): void {
                    $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%");
                });
            })
            ->when($feeTypeId !== null, fn (Builder $q) => $q->where('fee_type_id', $feeTypeId))
            ->when($status === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $q) => $q->where('is_active', false))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $items = $discounts->getCollection()
            ->map(static fn (StudentDiscount $d): array => [
                'id' => $d->id,
                'student_id' => $d->student_id,
                'student_name' => $d->student->name,
                'student_nis' => $d->student->nis,
                'unit_code' => $d->student->unit->code,
                'fee_type_id' => $d->fee_type_id,
                'fee_type_name' => $d->feeType->name,
                'kind' => $d->kind->value,
                'kind_label' => $d->kind->label(),
                'value' => $d->value,
                'valid_from' => $d->valid_from->toDateString(),
                'valid_until' => $d->valid_until?->toDateString(),
                'reason' => $d->reason,
                'is_active' => $d->is_active,
            ])
            ->values()
            ->all();

        $user = $request->user();

        return Inertia::render('admin/billing/discounts', [
            'discounts' => [
                'data' => $items,
                'meta' => [
                    'current_page' => $discounts->currentPage(),
                    'last_page' => $discounts->lastPage(),
                    'per_page' => $discounts->perPage(),
                    'total' => $discounts->total(),
                    'from' => $discounts->firstItem(),
                    'to' => $discounts->lastItem(),
                ],
            ],
            'students' => Student::query()
                ->where('status', StudentStatus::Active)
                ->orderBy('name')
                ->get(['id', 'nis', 'name'])
                ->map(static fn (Student $s): array => ['id' => $s->id, 'nis' => $s->nis, 'name' => $s->name])
                ->values()
                ->all(),
            'feeTypes' => FeeType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'kinds' => array_map(
                static fn (DiscountKind $k): array => ['value' => $k->value, 'label' => $k->label()],
                DiscountKind::cases(),
            ),
            'filters' => [
                'search' => $search,
                'fee_type_id' => $feeTypeId !== null ? (string) $feeTypeId : '',
                'status' => $status,
            ],
            'canManage' => $user instanceof User && $user->can('create', StudentDiscount::class),
        ]);
    }

    public function store(StoreStudentDiscountRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->service->create($actor, $request->validated());

        return back()->with('success', 'Potongan berulang berhasil ditambahkan.');
    }

    public function update(UpdateStudentDiscountRequest $request, StudentDiscount $studentDiscount): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->service->update($actor, $studentDiscount, $request->validated());

        return back()->with('success', 'Potongan berulang berhasil diperbarui.');
    }

    public function toggle(Request $request, StudentDiscount $studentDiscount): RedirectResponse
    {
        Gate::authorize('toggle', $studentDiscount);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $isActive = (bool) $validated['is_active'];
        $this->service->toggleActive($actor, $studentDiscount, $isActive);

        return back()->with('success', $isActive ? 'Potongan diaktifkan.' : 'Potongan dinonaktifkan.');
    }
}
