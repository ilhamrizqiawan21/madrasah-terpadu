<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeachingAssignmentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('is_active', true),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $user = is_numeric($value) ? User::query()->find((int) $value) : null;

                    if (! $user instanceof User || ! $user->hasRole(Role::Teacher->value)) {
                        $fail('Pengguna terpilih bukan guru aktif.');
                    }
                },
            ],
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')],
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('subjects', 'id')->where('is_active', true),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $subject = is_numeric($value) ? Subject::query()->find((int) $value) : null;
                    $classroom = Classroom::query()->find($this->integer('classroom_id'));

                    if ($subject instanceof Subject && $classroom instanceof Classroom && $subject->unit_id !== $classroom->unit_id) {
                        $fail('Mapel dan rombel harus berasal dari unit yang sama.');
                    }
                },
            ],
            'classroom_id' => [
                'required',
                'integer',
                Rule::exists('classrooms', 'id')->where('is_active', true),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $classroom = is_numeric($value) ? Classroom::query()->find((int) $value) : null;

                    if ($classroom instanceof Classroom && $classroom->academic_year_id !== $this->integer('academic_year_id')) {
                        $fail('Rombel tidak berada pada tahun ajaran yang dipilih.');
                    }

                    $exists = TeachingAssignment::query()
                        ->where('user_id', $this->integer('user_id'))
                        ->where('subject_id', $this->integer('subject_id'))
                        ->where('classroom_id', $this->integer('classroom_id'))
                        ->where('academic_year_id', $this->integer('academic_year_id'))
                        ->exists();

                    if ($exists) {
                        $fail('Penugasan guru untuk mapel dan rombel ini sudah ada.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'guru',
            'academic_year_id' => 'tahun ajaran',
            'subject_id' => 'mata pelajaran',
            'classroom_id' => 'rombel',
        ];
    }

    /**
     * @return array{user_id: int, subject_id: int, classroom_id: int, academic_year_id: int}
     */
    public function assignmentData(): array
    {
        $validated = $this->validated();

        return [
            'user_id' => (int) $validated['user_id'],
            'subject_id' => (int) $validated['subject_id'],
            'classroom_id' => (int) $validated['classroom_id'],
            'academic_year_id' => (int) $validated['academic_year_id'],
        ];
    }
}
