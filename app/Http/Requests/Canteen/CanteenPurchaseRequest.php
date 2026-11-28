<?php

declare(strict_types=1);

namespace App\Http\Requests\Canteen;

use Illuminate\Foundation\Http\FormRequest;

class CanteenPurchaseRequest extends FormRequest
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
            'student_id' => ['required', 'integer', 'exists:students,id'],
            // Mode keranjang (menu): total dihitung server dari harga menu + nominal lain.
            'lines' => ['nullable', 'array', 'max:30'],
            'lines.*.product_id' => ['required', 'integer', 'distinct'],
            'lines.*.qty' => ['required', 'integer', 'min:1', 'max:50'],
            'manual_amount' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'manual_note' => ['nullable', 'string', 'max:60'],
            // Mode nominal saja (lama): kasir mengetik total.
            'total' => ['required_without_all:lines,manual_amount', 'nullable', 'integer', 'min:100', 'max:5000000'],
            'client_uuid' => ['required', 'uuid'],
            'items' => ['nullable', 'array', 'max:30'],
            'items.*.name' => ['required_with:items', 'string', 'max:100'],
            'items.*.amount' => ['required_with:items', 'integer', 'min:1'],
        ];
    }
}
