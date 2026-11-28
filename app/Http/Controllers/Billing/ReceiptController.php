<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Student;
use App\Services\Billing\ReceiptPdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ReceiptController extends Controller
{
    public function __construct(
        private readonly ReceiptPdfService $pdf,
    ) {}

    public function staff(Payment $payment): Response
    {
        Gate::authorize('viewAny', Payment::class);

        return $this->pdf->download($payment);
    }

    public function guardian(Student $student, Payment $payment): Response
    {
        if ($payment->student_id !== $student->id) {
            abort(404);
        }

        return $this->pdf->download($payment);
    }
}
