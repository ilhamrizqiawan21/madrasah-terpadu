<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use App\Enums\InvoiceAdjustmentType;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Invoice|null $invoice */
        $invoice = $this->route('invoice');
        $user = $this->user();

        return $invoice !== null && $user instanceof User && $user->can('adjust', $invoice);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(InvoiceAdjustmentType::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
