<?php

declare(strict_types=1);

namespace App\Http\Requests\Guardian;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Wali hanya mengirim id tagihan; nominal apa pun dari browser diabaikan (PAYMENTS §1.4).
 */
class StorePaymentOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_ids' => ['required', 'array', 'min:1', 'max:24'],
            'invoice_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'invoice_ids' => 'tagihan',
            'invoice_ids.*' => 'tagihan',
        ];
    }

    /**
     * @return list<int>
     */
    public function invoiceIds(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->validated('invoice_ids');

        return array_map(intval(...), $ids);
    }
}
