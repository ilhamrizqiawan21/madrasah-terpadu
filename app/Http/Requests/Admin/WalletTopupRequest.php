<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WalletTopupRequest extends FormRequest
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
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'amount' => ['required', 'integer', "min:{$min}", "max:{$max}"],
            'client_uuid' => ['required', 'uuid'],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }
}
