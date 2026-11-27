<?php

declare(strict_types=1);

namespace App\Services\Students;

use App\Enums\GuardianRelation;
use App\Models\Student;

/**
 * Hasil pemeriksaan satu baris berkas impor siswa (T-207).
 *
 * @phpstan-import-type StudentData from StudentService
 *
 * @phpstan-type GuardianContact array{name: string, phone: string, relation: GuardianRelation}
 */
final readonly class StudentImportRow
{
    public const STATUS_NEW = 'new';

    public const STATUS_UPDATE = 'update';

    public const STATUS_UNCHANGED = 'unchanged';

    public const STATUS_ERROR = 'error';

    public const GUARDIAN_NONE = 'none';

    public const GUARDIAN_NEW_ACCOUNT = 'new_account';

    public const GUARDIAN_LINK_EXISTING = 'link_existing';

    public const GUARDIAN_STAFF_ACCOUNT = 'staff_account';

    public const GUARDIAN_ALREADY_LINKED = 'already_linked';

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $changes  nama isian yang berubah untuk siswa terdaftar
     * @param  StudentData|null  $studentData  data lengkap untuk StudentService (null bila galat)
     * @param  GuardianContact|null  $guardian  null bila kolom wali dikosongkan
     */
    public function __construct(
        public int $row,
        public string $status,
        public array $errors,
        public array $changes,
        public string $nis,
        public string $name,
        public string $unit,
        public string $classroom,
        public string $guardianName,
        public string $guardianPhone,
        public string $guardianAction,
        public ?Student $student = null,
        public ?int $unitId = null,
        public ?array $studentData = null,
        public ?array $guardian = null,
    ) {}

    /**
     * Bentuk yang dikirim ke halaman pratinjau; tanpa alamat dan tanggal lahir.
     *
     * @return array<string, mixed>
     */
    public function toPreview(): array
    {
        return [
            'row' => $this->row,
            'status' => $this->status,
            'nis' => $this->nis,
            'name' => $this->name,
            'unit' => $this->unit,
            'classroom' => $this->classroom,
            'guardian_name' => $this->guardianName,
            'guardian_phone' => $this->guardianPhone,
            'guardian_action' => $this->guardianAction,
            'changes' => $this->changes,
            'errors' => $this->errors,
        ];
    }
}
