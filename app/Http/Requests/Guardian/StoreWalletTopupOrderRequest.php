<?php

declare(strict_types=1);

namespace App\Http\Requests\Guardian;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Nominal isi saldo online dari wali (T-805). Rentang diperiksa ulang di PaymentOrderService.
 */
class StoreWalletTopupOrderRequest extends FormRequest
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
        $min = (int) config('sekolah.wallet.min_top_up', 10_000);
        $max = (int) config('sekolah.wallet.max_top_up', 1_000_000);

        return [
            'amount' => ['required', 'integer', "min:{$min}", "max:{$max}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['amount' => 'nominal isi saldo'];
    }
}
