<?php

declare(strict_types=1);

namespace App\Http\Requests\Guardian;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWalletLimitRequest extends FormRequest
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
        $max = (int) config('sekolah.wallet.max_daily_limit', 100_000);

        return [
            'daily_limit' => ['required', 'integer', 'min:0', "max:{$max}"],
        ];
    }
}
