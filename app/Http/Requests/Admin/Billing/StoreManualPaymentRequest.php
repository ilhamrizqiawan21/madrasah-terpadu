<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('receive', Payment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'method' => ['required', Rule::in([PaymentMethod::Cash->value, PaymentMethod::Transfer->value])],
            'reference' => ['nullable', 'string', 'max:120'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'allocations.*.amount' => ['required', 'integer', 'min:1'],
        ];
    }
}
