<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceAdjustmentType;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $search = trim((string) $request->string('search'));
        $unitId = $request->filled('unit_id') ? $request->integer('unit_id') : null;
        $classroomId = $request->filled('classroom_id') ? $request->integer('classroom_id') : null;
        $feeTypeId = $request->filled('fee_type_id') ? $request->integer('fee_type_id') : null;
        $status = $request->string('status')->toString();
        $period = $request->string('period')->toString();
        $dueFrom = $request->string('due_from')->toString();
        $dueTo = $request->string('due_to')->toString();

        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $todayWita = now()->timezone('Asia/Makassar')->toDateString();

        $query = Invoice::query()
            ->with([
                'student:id,unit_id,nis,name',
                'student.unit:id,code,name',
                'feeType:id,name,category',
            ])
            ->when($search !== '', function (Builder $q) use ($search): void {
                $q->whereHas('student', function (Builder $sq) use ($search): void {
                    $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%");
                });
            })
            ->when($unitId !== null, function (Builder $q) use ($unitId): void {
                $q->whereHas('student', fn (Builder $sq) => $sq->where('unit_id', $unitId));
            })
            ->when($classroomId !== null, function (Builder $q) use ($classroomId, $activeYear): void {
                $q->whereHas('student.enrollments', function (Builder $eq) use ($classroomId, $activeYear): void {
                    $eq->where('classroom_id', $classroomId);
                    if ($activeYear !== null) {
                        $eq->where('academic_year_id', $activeYear->id);
                    }
                });
            })
            ->when($feeTypeId !== null, fn (Builder $q) => $q->where('fee_type_id', $feeTypeId))
            ->when($status === 'overdue', function (Builder $q) use ($todayWita): void {
                $q->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
                    ->whereDate('due_date', '<', $todayWita);
            })
            ->when($status !== '' && $status !== 'overdue', fn (Builder $q) => $q->where('status', $status))
            ->when($period !== '' && preg_match('/^\d{4}-\d{2}$/', $period) === 1, function (Builder $q) use ($period): void {
                $q->whereDate('period', "{$period}-01");
            })
            ->when($dueFrom !== '', fn (Builder $q) => $q->whereDate('due_date', '>=', $dueFrom))
            ->when($dueTo !== '', fn (Builder $q) => $q->whereDate('due_date', '<=', $dueTo));

        $summaryRow = (clone $query)
            ->setEagerLoads([])
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->selectRaw('COALESCE(SUM(amount - adjustment_total), 0) as total_billed, COALESCE(SUM(paid_total), 0) as total_paid')
            ->first();

        $totalBilled = (int) ($summaryRow->total_billed ?? 0);
        $totalPaid = (int) ($summaryRow->total_paid ?? 0);

        $invoices = $query
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $items = $invoices->getCollection()
            ->map(function (Invoice $invoice) use ($todayWita): array {
                $isOverdue = in_array($invoice->status, [InvoiceStatus::Unpaid, InvoiceStatus::Partial], true)
                    && $invoice->due_date->toDateString() < $todayWita;

                return [
                    'id' => $invoice->id,
                    'student_id' => $invoice->student_id,
                    'student_name' => $invoice->student->name,
                    'student_nis' => $invoice->student->nis,
                    'unit_code' => $invoice->student->unit->code,
                    'fee_type_name' => $invoice->feeType->name,
                    'description' => $invoice->description,
                    'period' => $invoice->period?->toDateString(),
                    'due_date' => $invoice->due_date->toDateString(),
                    'amount' => $invoice->amount,
                    'adjustment_total' => $invoice->adjustment_total,
                    'paid_total' => $invoice->paid_total,
                    'remaining' => $invoice->remaining(),
                    'status' => $invoice->status->value,
                    'status_label' => $invoice->status->label(),
                    'is_overdue' => $isOverdue,
                ];
            })
            ->values()
            ->all();

        $classrooms = $activeYear !== null
            ? Classroom::query()
                ->where('academic_year_id', $activeYear->id)
                ->where('is_active', true)
                ->orderBy('grade')
                ->orderBy('name')
                ->get(['id', 'unit_id', 'name'])
            : collect();

        $user = $request->user();

        return Inertia::render('admin/billing/invoices', [
            'invoices' => [
                'data' => $items,
                'meta' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page' => $invoices->lastPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                    'from' => $invoices->firstItem(),
                    'to' => $invoices->lastItem(),
                ],
            ],
            'summary' => [
                'total_billed' => $totalBilled,
                'total_paid' => $totalPaid,
                'total_remaining' => max(0, $totalBilled - $totalPaid),
            ],
            'units' => Unit::query()->orderBy('name')->get(['id', 'code', 'name']),
            'classrooms' => $classrooms,
            'feeTypes' => FeeType::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => [
                ...array_map(
                    static fn (InvoiceStatus $st): array => ['value' => $st->value, 'label' => $st->label()],
                    InvoiceStatus::cases(),
                ),
                ['value' => 'overdue', 'label' => 'Menunggak'],
            ],
            'filters' => [
                'search' => $search,
                'unit_id' => $unitId !== null ? (string) $unitId : '',
                'classroom_id' => $classroomId !== null ? (string) $classroomId : '',
                'fee_type_id' => $feeTypeId !== null ? (string) $feeTypeId : '',
                'status' => $status,
                'period' => $period,
                'due_from' => $dueFrom,
                'due_to' => $dueTo,
            ],
            'canIssue' => $user instanceof User && $user->can('issue', Invoice::class),
        ]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['student.unit:id,code,name', 'feeType:id,name,category']);
        $todayWita = now()->timezone('Asia/Makassar')->toDateString();
        $isOverdue = in_array($invoice->status, [InvoiceStatus::Unpaid, InvoiceStatus::Partial], true)
            && $invoice->due_date->toDateString() < $todayWita;

        $adjustments = [];
        if (Schema::hasTable('invoice_adjustments')) {
            $adjustments = DB::table('invoice_adjustments')
                ->leftJoin('users', 'users.id', '=', 'invoice_adjustments.created_by')
                ->where('invoice_id', $invoice->id)
                ->orderByDesc('invoice_adjustments.id')
                ->get([
                    'invoice_adjustments.id',
                    'invoice_adjustments.type',
                    'invoice_adjustments.amount',
                    'invoice_adjustments.reason',
                    'invoice_adjustments.created_at',
                    'users.name as actor_name',
                ])
                ->map(static fn (object $row): array => [
                    'id' => (int) $row->id,
                    'type' => (string) $row->type,
                    'amount' => (int) $row->amount,
                    'reason' => (string) $row->reason,
                    'actor_name' => $row->actor_name !== null ? (string) $row->actor_name : 'Sistem',
                    'created_at' => (string) $row->created_at,
                ])
                ->values()
                ->all();
        }

        $payments = [];
        if (Schema::hasTable('payment_allocations') && Schema::hasTable('payments')) {
            $payments = DB::table('payment_allocations')
                ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
                ->leftJoin('users', 'users.id', '=', 'payments.received_by')
                ->where('payment_allocations.invoice_id', $invoice->id)
                ->orderByDesc('payments.paid_at')
                ->get([
                    'payments.id',
                    'payments.receipt_number',
                    'payments.method',
                    'payments.paid_at',
                    'payments.reversed_at',
                    'payment_allocations.amount as allocated_amount',
                    'users.name as cashier_name',
                ])
                ->map(static fn (object $row): array => [
                    'id' => (int) $row->id,
                    'receipt_number' => (string) $row->receipt_number,
                    'method' => (string) $row->method,
                    'allocated_amount' => (int) $row->allocated_amount,
                    'paid_at' => (string) $row->paid_at,
                    'reversed_at' => $row->reversed_at !== null ? (string) $row->reversed_at : null,
                    'cashier_name' => $row->cashier_name !== null ? (string) $row->cashier_name : 'Pembayaran online',
                ])
                ->values()
                ->all();
        }

        $user = $request->user();

        return Inertia::render('admin/billing/invoice-show', [
            'invoice' => [
                'id' => $invoice->id,
                'student_id' => $invoice->student_id,
                'student_name' => $invoice->student->name,
                'student_nis' => $invoice->student->nis,
                'unit_name' => $invoice->student->unit->name,
                'fee_type_name' => $invoice->feeType->name,
                'category' => $invoice->feeType->category,
                'description' => $invoice->description,
                'period' => $invoice->period?->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'amount' => $invoice->amount,
                'adjustment_total' => $invoice->adjustment_total,
                'paid_total' => $invoice->paid_total,
                'remaining' => $invoice->remaining(),
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'cancelled_reason' => $invoice->cancelled_reason,
                'is_overdue' => $isOverdue,
                'created_at' => $invoice->created_at->toIso8601String(),
            ],
            'adjustments' => $adjustments,
            'payments' => $payments,
            'adjustmentTypes' => array_map(
                static fn (InvoiceAdjustmentType $type): array => ['value' => $type->value, 'label' => $type->label()],
                InvoiceAdjustmentType::cases(),
            ),
            'canAdjust' => $user instanceof User && $user->can('adjust', $invoice),
        ]);
    }

    public function studentCard(Request $request, Student $student): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $student->load('unit:id,code,name');
        $todayWita = now()->timezone('Asia/Makassar')->toDateString();

        $invoices = Invoice::query()
            ->with('feeType:id,name,category')
            ->where('student_id', $student->id)
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->get();

        $totalBilled = 0;
        $totalPaid = 0;

        $rows = $invoices->map(function (Invoice $invoice) use ($todayWita, &$totalBilled, &$totalPaid): array {
            if ($invoice->status !== InvoiceStatus::Cancelled) {
                $totalBilled += max(0, $invoice->amount - $invoice->adjustment_total);
                $totalPaid += $invoice->paid_total;
            }

            $isOverdue = in_array($invoice->status, [InvoiceStatus::Unpaid, InvoiceStatus::Partial], true)
                && $invoice->due_date->toDateString() < $todayWita;

            return [
                'id' => $invoice->id,
                'fee_type_name' => $invoice->feeType->name,
                'description' => $invoice->description,
                'due_date' => $invoice->due_date->toDateString(),
                'amount' => $invoice->amount,
                'adjustment_total' => $invoice->adjustment_total,
                'paid_total' => $invoice->paid_total,
                'remaining' => $invoice->remaining(),
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'is_overdue' => $isOverdue,
            ];
        })->values()->all();

        return Inertia::render('admin/billing/student-card', [
            'student' => [
                'id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->name,
                'unit_name' => $student->unit->name,
            ],
            'summary' => [
                'total_billed' => $totalBilled,
                'total_paid' => $totalPaid,
                'total_remaining' => max(0, $totalBilled - $totalPaid),
            ],
            'invoices' => $rows,
        ]);
    }
}
