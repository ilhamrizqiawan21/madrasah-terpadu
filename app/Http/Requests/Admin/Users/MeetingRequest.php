<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Users;

use App\Enums\MeetingAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $minutes = $this->input('minutes');
        $this->merge([
            'unit_id' => $this->filled('unit_id') ? $this->integer('unit_id') : null,
            'minutes' => is_string($minutes) && trim($minutes) !== '' ? trim($minutes) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'held_on' => ['required', 'date_format:Y-m-d'],
            'unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'audience' => ['required', Rule::enum(MeetingAudience::class)],
            'minutes' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'agenda rapat',
            'held_on' => 'tanggal pelaksanaan',
            'unit_id' => 'unit',
            'audience' => 'sasaran peserta',
            'minutes' => 'notulen',
        ];
    }

    /**
     * @return array{unit_id: ?int, title: string, held_on: string, audience: string, minutes: ?string}
     */
    public function meetingData(): array
    {
        /** @var array{unit_id: ?int, title: string, held_on: string, audience: string, minutes: ?string} $data */
        $data = $this->safe()->only(['unit_id', 'title', 'held_on', 'audience', 'minutes']);

        return $data;
    }
}
