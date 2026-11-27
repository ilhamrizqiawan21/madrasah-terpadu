<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use App\Enums\Permission;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::StudentsManage->value) === true;
    }

    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach (['nis', 'nisn', 'name', 'nickname', 'birth_place', 'address'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
                $trimmed[$field] = $value === '' && $field !== 'nis' && $field !== 'name' ? null : $value;
            }
        }

        $this->merge($trimmed);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $student = $this->editedStudent();
        $unitId = $student !== null ? $student->unit_id : $this->integer('unit_id');

        $rules = self::fieldRules();
        // Unik terhadap semua baris, termasuk siswa yang diarsipkan, sesuai indeks unik tabel.
        $rules['nis'][] = Rule::unique('students', 'nis')->ignore($student?->id);
        $rules['nisn'][] = Rule::unique('students', 'nisn')->ignore($student?->id);

        $rules = [
            ...$rules,
            'status' => ['required', Rule::enum(StudentStatus::class)],
            'classroom_id' => [
                'nullable', 'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($unitId): void {
                    $this->validateClassroom($value, $unitId, $fail);
                },
            ],
            // `image` tidak menerima SVG (bisa memuat skrip).
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
            'remove_photo' => ['sometimes', 'boolean'],
        ];

        if ($student === null) {
            $rules['unit_id'] = ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...self::fieldAttributes(),
            'unit_id' => 'unit',
            'status' => 'status',
            'classroom_id' => 'rombel',
            'photo' => 'foto',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...self::fieldMessages(),
            'nis.unique' => 'NIS ini sudah dipakai siswa lain.',
            'nisn.unique' => 'NISN ini sudah dipakai siswa lain.',
            'photo.image' => 'Foto harus berupa gambar JPG, PNG, atau WebP.',
            'photo.mimes' => 'Foto harus berupa gambar JPG, PNG, atau WebP.',
            'photo.max' => 'Ukuran foto paling besar 2 MB.',
            'photo.dimensions' => 'Resolusi foto terlalu besar (maksimal 6000 piksel per sisi).',
        ];
    }

    /**
     * Aturan isian data diri siswa tanpa cek unik; dipakai juga oleh impor Excel (T-207),
     * yang memeriksa keunikan NIS/NISN sekaligus untuk seluruh berkas.
     *
     * @return array<string, list<mixed>>
     */
    public static function fieldRules(): array
    {
        return [
            'nis' => ['required', 'string', 'max:20', 'regex:/^[0-9A-Za-z.\-\/]+$/'],
            'nisn' => ['nullable', 'string', 'digits:10'],
            'name' => ['required', 'string', 'max:100'],
            'nickname' => ['nullable', 'string', 'max:30'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'birth_place' => ['nullable', 'string', 'max:60'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fieldAttributes(): array
    {
        return [
            'nis' => 'NIS',
            'nisn' => 'NISN',
            'name' => 'nama lengkap',
            'nickname' => 'nama panggilan',
            'gender' => 'jenis kelamin',
            'birth_place' => 'tempat lahir',
            'birth_date' => 'tanggal lahir',
            'address' => 'alamat',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fieldMessages(): array
    {
        return [
            'nis.regex' => 'NIS hanya boleh berisi huruf, angka, titik, garis miring, atau tanda hubung.',
            'nisn.digits' => 'NISN harus 10 digit angka.',
            'birth_date.before_or_equal' => 'Tanggal lahir tidak boleh setelah hari ini.',
        ];
    }

    /**
     * @return array{nis: string, nisn: string|null, name: string, nickname: string|null, gender: string, birth_place: string|null, birth_date: string|null, address: string|null, status: string, classroom_id: int|null}
     */
    public function studentData(): array
    {
        $optional = fn (string $key): ?string => $this->validated($key) === null ? null : (string) $this->validated($key);
        $classroomId = $this->validated('classroom_id');

        return [
            'nis' => (string) $this->validated('nis'),
            'nisn' => $optional('nisn'),
            'name' => (string) $this->validated('name'),
            'nickname' => $optional('nickname'),
            'gender' => (string) $this->validated('gender'),
            'birth_place' => $optional('birth_place'),
            'birth_date' => $optional('birth_date'),
            'address' => $optional('address'),
            'status' => (string) $this->validated('status'),
            'classroom_id' => $classroomId === null ? null : (int) $classroomId,
        ];
    }

    public function photo(): ?UploadedFile
    {
        $file = $this->file('photo');

        return $file instanceof UploadedFile ? $file : null;
    }

    private function editedStudent(): ?Student
    {
        $student = $this->route('student');

        return $student instanceof Student ? $student : null;
    }

    /**
     * Aturan closure tidak dijalankan untuk nilai kosong, jadi larangan mengosongkan
     * rombel yang sudah terisi diperiksa di sini.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $student = $this->editedStudent();

                if ($student === null || $this->input('classroom_id') !== null || $validator->errors()->has('classroom_id')) {
                    return;
                }

                $enrolled = Enrollment::query()
                    ->where('student_id', $student->id)
                    ->whereHas('academicYear', fn (Builder $query) => $query->where('is_active', true))
                    ->exists();

                if ($enrolled) {
                    $validator->errors()->add('classroom_id', 'Siswa sudah punya rombel di tahun ajaran aktif. Pilih rombel penggantinya.');
                }
            },
        ];
    }

    private function validateClassroom(mixed $value, int $unitId, Closure $fail): void
    {
        $year = AcademicYear::query()->where('is_active', true)->first();

        if ($year === null) {
            $fail('Belum ada tahun ajaran aktif. Aktifkan tahun ajaran sebelum memilih rombel.');

            return;
        }

        $valid = Classroom::query()
            ->whereKey((int) $value)
            ->where('unit_id', $unitId)
            ->where('academic_year_id', $year->id)
            ->where('is_active', true)
            ->exists();

        if (! $valid) {
            $fail('Pilih rombel aktif milik unit siswa pada tahun ajaran aktif.');
        }
    }
}
