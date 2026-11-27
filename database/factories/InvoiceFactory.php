<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'fee_type_id' => FeeType::factory(),
            'billing_run_id' => null,
            'period' => now()->timezone('Asia/Makassar')->startOfMonth()->toDateString(),
            'description' => 'SPP Bulanan',
            'amount' => 150000,
            'adjustment_total' => 0,
            'paid_total' => 0,
            'due_date' => now()->timezone('Asia/Makassar')->startOfMonth()->addDays(9)->toDateString(),
            'status' => InvoiceStatus::Unpaid,
            'cancelled_reason' => null,
        ];
    }
}
