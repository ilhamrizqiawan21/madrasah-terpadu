<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\ReversePaymentRequest;
use App\Http\Requests\Admin\Billing\StoreManualPaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\Billing\ManualPaymentService;
use App\Services\Billing\PaymentReversalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CashierController extends Controller
{
    public function __construct(
        private readonly ManualPaymentService $service,
        private readonly PaymentReversalService $reversalService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('receive', Payment::class);

        $studentId = $request->filled('student_id') ? $request->integer('student_id') : null;
        $selectedStudent = $studentId !== null
            ? Student::query()->with('unit:id,code,name')->find($studentId)
            : null;

        $openInvoices = [];
        if ($selectedStudent !== null) {
            $todayWita = now()->timezone('Asia/Makassar')->toDateString();

            $openInvoices = Invoice::query()
                ->with('feeType:id,name')
                ->where('student_id', $selectedStudent->id)
                ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
                ->orderBy('due_date')
                ->orderBy('id')
                ->get()
                ->map(static fn (Invoice $inv): array => [
                    'id' => $inv->id,
                    'fee_type_name' => $inv->feeType->name,
                    'description' => $inv->description,
                    'due_date' => $inv->due_date->toDateString(),
                    'is_overdue' => $inv->due_date->toDateString() < $todayWita,
                    'amount' => $inv->amount,
                    'adjustment_total' => $inv->adjustment_total,
                    'paid_total' => $inv->paid_total,
                    'remaining' => $inv->remaining(),
                ])
                ->values()
                ->all();
        }

        $students = Student::query()
            ->with('unit:id,code')
            ->where('status', StudentStatus::Active)
            ->orderBy('name')
            ->get(['id', 'unit_id', 'nis', 'name'])
            ->map(static fn (Student $s): array => [
                'id' => $s->id,
                'nis' => $s->nis,
                'name' => $s->name,
                'unit_code' => $s->unit->code,
            ])
            ->values()
            ->all();

        $recentPayments = Payment::query()
            ->with(['student:id,nis,name', 'receiver:id,name'])
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(static fn (Payment $p): array => [
                'id' => $p->id,
                'receipt_number' => $p->receipt_number,
                'student_name' => $p->student->name,
                'student_nis' => $p->student->nis,
                'method' => $p->method->value,
                'method_label' => $p->method->label(),
                'amount' => $p->amount,
                'cashier_name' => $p->receiver->name ?? 'Pembayaran online',
                'paid_at' => $p->paid_at->toIso8601String(),
                'is_reversed' => $p->reversed_at !== null,
            ])
            ->values()
            ->all();

        $user = $request->user();

        return Inertia::render('admin/billing/cashier', [
            'students' => $students,
            'selectedStudent' => $selectedStudent !== null ? [
                'id' => $selectedStudent->id,
                'nis' => $selectedStudent->nis,
                'name' => $selectedStudent->name,
                'unit_name' => $selectedStudent->unit->name,
            ] : null,
            'openInvoices' => $openInvoices,
            'recentPayments' => $recentPayments,
            'methods' => [
                ['value' => PaymentMethod::Cash->value, 'label' => PaymentMethod::Cash->label()],
                ['value' => PaymentMethod::Transfer->value, 'label' => PaymentMethod::Transfer->label()],
            ],
            'canReverse' => $user instanceof User && $user->can('reverse', Payment::class),
        ]);
    }

    public function store(StoreManualPaymentRequest $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        $student = Student::query()->findOrFail((int) $validated['student_id']);

        /** @var list<array{invoice_id: int, amount: int}> $allocations */
        $allocations = array_map(
            static fn (array $row): array => [
                'invoice_id' => (int) $row['invoice_id'],
                'amount' => (int) $row['amount'],
            ],
            $validated['allocations'],
        );

        $payment = $this->service->receive(
            actor: $actor,
            student: $student,
            method: PaymentMethod::from((string) $validated['method']),
            allocations: $allocations,
            reference: isset($validated['reference']) ? (string) $validated['reference'] : null,
        );

        return back()->with('success', "Pembayaran tersimpan ({$payment->receipt_number}).");
    }

    public function reverse(ReversePaymentRequest $request, Payment $payment): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();

        $this->reversalService->reverse($actor, $payment, (string) $validated['reason']);

        return back()->with('success', "Pembayaran {$payment->receipt_number} berhasil dibalik.");
    }
}
