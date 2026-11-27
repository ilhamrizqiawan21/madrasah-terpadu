<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CancelInvoiceRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
