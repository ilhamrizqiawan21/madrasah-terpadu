<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Behavior;

use App\Models\PointRule;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ImportPointRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', PointRule::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'file' => ['required', 'file', 'mimes:xlsx,csv', 'max:2048'],
        ];
    }
}
