<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Behavior;

use App\Models\BehaviorRecord;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class VoidBehaviorRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var BehaviorRecord|null $record */
        $record = $this->route('behaviorRecord');
        $user = $this->user();

        return $record !== null && $user instanceof User && $user->can('void', $record);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'void_reason' => ['required', 'string', 'max:500'],
        ];
    }
}
