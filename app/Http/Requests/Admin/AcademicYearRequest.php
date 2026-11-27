<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Enums\Semester;
use App\Models\AcademicYear;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::AcademicYearsManage->value) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $year = $this->route('academicYear');

        return [
            'name' => [
                'bail', 'required', 'string', 'regex:/^\d{4}\/\d{4}$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    [$first, $second] = array_map('intval', explode('/', (string) $value));

                    if ($second !== $first + 1) {
                        $fail('Tahun ajaran harus dua tahun berurutan, misalnya 2026/2027.');
                    }
                },
            ],
            'semester' => [
                'required', Rule::enum(Semester::class),
                Rule::unique('academic_years', 'semester')
                    ->where('name', (string) $this->input('name'))
                    ->ignore($year instanceof AcademicYear ? $year->id : null),
            ],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'tahun ajaran',
            'semester' => 'semester',
            'starts_on' => 'tanggal mulai',
            'ends_on' => 'tanggal selesai',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Tulis tahun ajaran dengan format 2026/2027.',
            'semester.unique' => 'Semester ini sudah ada untuk tahun ajaran tersebut.',
            'ends_on.after' => 'Tanggal selesai harus setelah tanggal mulai.',
        ];
    }

    /**
     * @return array{name: string, semester: string, starts_on: string, ends_on: string}
     */
    public function yearData(): array
    {
        /** @var array{name: string, semester: string, starts_on: string, ends_on: string} */
        return $this->validated();
    }
}
