<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ImportOpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('issue', Invoice::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,csv', 'max:2048'],
        ];
    }
}
