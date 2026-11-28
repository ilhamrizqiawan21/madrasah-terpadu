<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use App\Models\TransferProof;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RejectTransferProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TransferProof|null $proof */
        $proof = $this->route('transferProof');
        $user = $this->user();

        return $proof !== null && $user instanceof User && $user->can('review', $proof);
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
