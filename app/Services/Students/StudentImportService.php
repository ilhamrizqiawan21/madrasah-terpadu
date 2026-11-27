<?php

declare(strict_types=1);

namespace App\Services\Students;

use App\Enums\GuardianRelation;
use App\Enums\StudentStatus;
use App\Exceptions\GuardianAccountException;
use App\Exceptions\StudentImportException;
use App\Http\Requests\Admin\GuardianRequest;
use App\Http\Requests\Admin\StudentRequest;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\GuardianStudent;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Guardians\GuardianService;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Options as WriterOptions;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * Impor siswa + wali dari Excel/CSV (T-207).
 *
 * Alur: unggah (berkas disimpan sementara di disk privat `local`, folder `imports/`) →
 * pratinjau (membaca dan memeriksa, tanpa menulis apa pun) → simpan (memeriksa ulang,
 * lalu menulis lewat StudentService dan GuardianService per 100 baris dalam satu transaksi).
 *
 * NIS adalah kunci: NIS baru membuat siswa, NIS terdaftar memperbarui siswa itu. Sel kosong
 * pada siswa terdaftar tidak mengosongkan data lama. Baris yang tidak mengubah apa pun
 * dihitung "tanpa perubahan" dan tidak ditulis, sehingga impor ulang berkas yang sama idempoten.
 *
 * @phpstan-import-type StudentData from StudentService
 *
 * @phpstan-type ImportResult array{
 *     created: int,
 *     updated: int,
 *     unchanged: int,
 *     failed: list<array{row: int, error: string}>,
 *     new_guardians: list<array{name: string, phone: string}>
 * }
 */
final class StudentImportService
{
    public const DISK = 'local';

    public const DIRECTORY = 'imports';

    public const MAX_ROWS = 1500;

    public const MAX_KILOBYTES = 2048;

    private const CHUNK_SIZE = 100;

    /** Judul kolom template, berurutan. */
    public const HEADERS = [
        'NIS',
        'NISN',
        'Nama lengkap',
        'Nama panggilan',
        'Jenis kelamin',
        'Tempat lahir',
        'Tanggal lahir',
        'Alamat',
        'Unit',
        'Rombel',
        'Nama wali',
        'Hubungan wali',
        'No. HP wali',
    ];

    /** Isian → judul kolom. */
    private const FIELDS = [
        'nis' => 'NIS',
        'nisn' => 'NISN',
        'name' => 'Nama lengkap',
        'nickname' => 'Nama panggilan',
        'gender' => 'Jenis kelamin',
        'birth_place' => 'Tempat lahir',
        'birth_date' => 'Tanggal lahir',
        'address' => 'Alamat',
        'unit' => 'Unit',
        'classroom' => 'Rombel',
        'guardian_name' => 'Nama wali',
        'guardian_relation' => 'Hubungan wali',
        'guardian_phone' => 'No. HP wali',
    ];

    private const REQUIRED_HEADERS = ['NIS', 'Nama lengkap', 'Jenis kelamin', 'Unit'];

    /** Isian data diri siswa yang dibandingkan untuk menentukan baris "ubah". */
    private const COMPARED_FIELDS = ['nisn', 'name', 'nickname', 'gender', 'birth_place', 'birth_date', 'address'];

    /** Kolom yang diformat teks di template agar angka 0 di depan tidak hilang. */
    private const TEXT_COLUMNS = ['NIS', 'NISN', 'Tanggal lahir', 'No. HP wali'];

    public function __construct(
        private readonly StudentService $students,
        private readonly GuardianService $guardians,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Memeriksa struktur berkas lalu menyimpannya sementara. Mengembalikan jalur relatif di disk privat.
     */
    public function stage(UploadedFile $file): string
    {
        $extension = Str::lower($file->getClientOriginalExtension()) === 'csv' ? 'csv' : 'xlsx';

        $this->read((string) $file->getRealPath(), $extension);

        $path = self::DIRECTORY.'/'.Str::random(40).'.'.$extension;

        if (Storage::disk(self::DISK)->putFileAs(self::DIRECTORY, $file, basename($path)) === false) {
            throw new RuntimeException('Berkas impor gagal disimpan.');
        }

        return $path;
    }

    public function exists(?string $path): bool
    {
        return $path !== null && Storage::disk(self::DISK)->exists($path);
    }

    public function discard(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /**
     * Hanya membaca: tidak ada baris yang ditulis.
     *
     * @return list<StudentImportRow>
     */
    public function preview(string $path): array
    {
        return $this->analyze($this->readStored($path));
    }

    /**
     * Data diperiksa ulang saat disimpan karena bisa berubah sejak pratinjau.
     *
     * @return ImportResult
     */
    public function commit(string $path, User $actor): array
    {
        $rows = $this->analyze($this->readStored($path));
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $failed = [];
        $newGuardians = [];
        $writable = [];

        foreach ($rows as $row) {
            match ($row->status) {
                StudentImportRow::STATUS_ERROR => $failed[$row->row] = implode(' ', $row->errors),
                StudentImportRow::STATUS_UNCHANGED => $unchanged++,
                default => $writable[] = $row,
            };
        }

        foreach (array_chunk($writable, self::CHUNK_SIZE) as $chunk) {
            $outcomes = DB::transaction(function () use ($chunk, $actor): array {
                $outcomes = [];

                foreach ($chunk as $row) {
                    try {
                        // Transaksi bersarang = savepoint: baris yang gagal dibatalkan sendiri, baris lain di chunk tetap masuk.
                        $outcomes[$row->row] = DB::transaction(fn (): array => $this->write($row, $actor));
                    } catch (GuardianAccountException $exception) {
                        $outcomes[$row->row] = ['error' => $exception->getMessage()];
                    } catch (QueryException $exception) {
                        report($exception);
                        $outcomes[$row->row] = ['error' => 'Baris gagal disimpan karena bentrok dengan data lain. Periksa NIS, NISN, dan No. HP wali.'];
                    } catch (RuntimeException $exception) {
                        $outcomes[$row->row] = ['error' => $exception->getMessage()];
                    }
                }

                return $outcomes;
            });

            foreach ($outcomes as $rowNumber => $outcome) {
                if (isset($outcome['error'])) {
                    $failed[$rowNumber] = $outcome['error'];

                    continue;
                }

                $outcome['created'] ? $created++ : $updated++;

                if ($outcome['new_guardian'] !== null) {
                    $newGuardians[] = $outcome['new_guardian'];
                }
            }
        }

        ksort($failed);

        DB::transaction(fn () => $this->auditLogger->record(
            action: 'students_imported',
            subject: Student::class,
            after: [
                'rows' => count($rows),
                'created' => $created,
                'updated' => $updated,
                'unchanged' => $unchanged,
                'failed' => count($failed),
                'guardians_created' => count($newGuardians),
            ],
            userId: $actor->id,
        ));

        return [
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'failed' => array_map(
                fn (int $row, string $error): array => ['row' => $row, 'error' => $error],
                array_keys($failed),
                array_values($failed),
            ),
            'new_guardians' => $newGuardians,
        ];
    }

    /**
     * Template .xlsx: lembar data (judul kolom saja, kolom angka diformat teks) + lembar petunjuk.
     * Mengembalikan jalur berkas sementara.
     */
    public function writeTemplate(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl');

        if ($path === false) {
            throw new RuntimeException('Template gagal dibuat.');
        }

        $options = new WriterOptions;
        $options->setColumnWidth(16, 1, 2, 5, 9, 10, 12);
        $options->setColumnWidth(28, 3, 4, 6, 8, 11);
        $options->setColumnWidth(18, 7, 13);

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Data siswa');

        $bold = (new Style)->withFontBold(true);
        $text = (new Style)->withFormat('@');
        $writer->addRow(Row::fromValuesWithStyle(self::HEADERS, $bold));

        $blankRow = new Row(array_map(
            fn (string $header): Cell => Cell::fromValue(null, in_array($header, self::TEXT_COLUMNS, true) ? $text : null),
            self::HEADERS,
        ));

        for ($i = 0; $i < self::MAX_ROWS; $i++) {
            $writer->addRow($blankRow);
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Petunjuk');
        $writer->addRow(Row::fromValuesWithStyle(['Kolom', 'Wajib', 'Keterangan'], $bold));
        $writer->addRows(array_map(
            fn (array $line): Row => Row::fromValues($line),
            $this->instructions(),
        ));
        $writer->close();

        return $path;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function instructions(): array
    {
        return [
            ['NIS', 'Ya', 'Kunci impor. NIS yang sudah terdaftar memperbarui data siswa itu. Huruf, angka, titik, garis miring, atau tanda hubung; maks. 20 karakter.'],
            ['NISN', 'Tidak', '10 digit angka.'],
            ['Nama lengkap', 'Ya', 'Maks. 100 karakter.'],
            ['Nama panggilan', 'Tidak', 'Maks. 30 karakter; dipakai di notifikasi ke wali.'],
            ['Jenis kelamin', 'Ya', 'L atau P.'],
            ['Tempat lahir', 'Tidak', 'Maks. 60 karakter.'],
            ['Tanggal lahir', 'Tidak', 'Format TTTT-BB-HH, mis. 2019-05-01, atau sel tanggal Excel.'],
            ['Alamat', 'Tidak', 'Maks. 500 karakter.'],
            ['Unit', 'Ya', 'Kode unit aktif, mis. MI. Unit siswa yang sudah terdaftar tidak bisa diubah lewat impor.'],
            ['Rombel', 'Tidak', 'Nama rombel aktif unit itu pada tahun ajaran aktif, mis. 1-A. Rombel tidak dibuat otomatis.'],
            ['Nama wali', 'Bersyarat', 'Isi ketiga kolom wali atau kosongkan ketiganya.'],
            ['Hubungan wali', 'Bersyarat', 'ayah, ibu, atau wali.'],
            ['No. HP wali', 'Bersyarat', 'Nomor seluler, mis. 0812xxxxxxxx; menjadi username akun wali. Nomor yang sudah terdaftar ditautkan ke wali itu.'],
            ['', '', 'Sel kosong pada siswa yang sudah terdaftar tidak mengubah data lama. Maksimal '.number_format(self::MAX_ROWS, 0, ',', '.').' baris dan 2 MB per berkas.'],
        ];
    }

    /**
     * @return array{created: bool, new_guardian: array{name: string, phone: string}|null}
     */
    private function write(StudentImportRow $row, User $actor): array
    {
        $data = $row->studentData;

        if ($data === null || $row->unitId === null) {
            throw new RuntimeException('Baris tidak lengkap.');
        }

        $student = $row->student === null
            ? $this->students->create($row->unitId, $data, null, $actor)
            : $this->students->update($row->student, $data, null, false, $actor);

        $newGuardian = null;

        if ($row->guardian !== null && $row->guardianAction !== StudentImportRow::GUARDIAN_ALREADY_LINKED) {
            // Tanpa tanda wali utama: GuardianService menjadikannya utama bila siswa belum punya wali utama.
            $result = $this->guardians->create([
                ...$row->guardian,
                'address' => null,
                'children' => [['student_id' => $student->id, 'is_primary' => false]],
            ], $actor);

            // Kata sandi awal sengaja dibuang: dicetak lewat "Reset kata sandi" di halaman Wali.
            if ($result['outcome'] === GuardianService::OUTCOME_CREATED) {
                $newGuardian = ['name' => $result['guardian']->name, 'phone' => $result['guardian']->phone];
            }
        }

        return ['created' => $row->student === null, 'new_guardian' => $newGuardian];
    }

    /**
     * @return list<array{row: int, values: array<string, string|null>}>
     */
    private function readStored(string $path): array
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        return $this->read(Storage::disk(self::DISK)->path($path), $extension);
    }

    /**
     * Membaca lembar pertama. Nomor baris mengikuti Excel (judul kolom = baris 1).
     *
     * @return list<array{row: int, values: array<string, string|null>}>
     */
    private function read(string $absolutePath, string $extension): array
    {
        $reader = $extension === 'csv'
            ? new CsvReader(new CsvOptions(SHOULD_PRESERVE_EMPTY_ROWS: true, FIELD_DELIMITER: $this->csvDelimiter($absolutePath)))
            : new XlsxReader(new XlsxOptions(SHOULD_PRESERVE_EMPTY_ROWS: true));

        $rows = [];
        $dataRows = 0;
        $columns = null;

        try {
            $reader->open($absolutePath);

            foreach ($reader->getSheetIterator() as $sheet) {
                $rowNumber = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $cells = $row->toArray();

                    if ($columns === null) {
                        $columns = $this->mapColumns($cells);

                        continue;
                    }

                    $values = [];

                    foreach ($columns as $field => $index) {
                        $values[$field] = $this->cellValue($field, $cells[$index] ?? null);
                    }

                    if (array_filter($values, fn (?string $value): bool => $value !== null) === []) {
                        continue;
                    }

                    $dataRows++;

                    if ($dataRows <= self::MAX_ROWS) {
                        $rows[] = ['row' => $rowNumber, 'values' => $values];
                    }
                }

                break;
            }
        } catch (OpenSpoutException) {
            throw StudentImportException::unreadable();
        } finally {
            $reader->close();
        }

        if ($columns === null || $dataRows === 0) {
            throw StudentImportException::empty();
        }

        if ($dataRows > self::MAX_ROWS) {
            throw StudentImportException::tooManyRows($dataRows, self::MAX_ROWS);
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $cells
     * @return array<string, int>
     */
    private function mapColumns(array $cells): array
    {
        $byHeader = [];

        foreach ($cells as $index => $cell) {
            $header = is_scalar($cell) ? Str::lower(Str::squish((string) $cell)) : '';

            if ($header !== '' && ! isset($byHeader[$header])) {
                $byHeader[$header] = $index;
            }
        }

        $columns = [];

        foreach (self::FIELDS as $field => $header) {
            $index = $byHeader[Str::lower($header)] ?? null;

            if ($index !== null) {
                $columns[$field] = $index;
            }
        }

        $missing = array_values(array_filter(
            self::REQUIRED_HEADERS,
            fn (string $header): bool => ! isset($byHeader[Str::lower($header)]),
        ));

        if ($missing !== []) {
            throw StudentImportException::missingColumns($missing);
        }

        return $columns;
    }

    private function cellValue(string $field, mixed $cell): ?string
    {
        if ($cell instanceof DateTimeInterface) {
            return $cell->format('Y-m-d');
        }

        $numeric = is_int($cell) || is_float($cell);

        if ($numeric) {
            // Angka bulat dari Excel (NIS, No. HP) ditulis tanpa ".0" atau notasi ilmiah.
            $value = is_float($cell) && floor($cell) === $cell && abs($cell) < 1e15
                ? sprintf('%.0f', $cell)
                : (string) $cell;
        } elseif (is_string($cell)) {
            $value = trim($cell);
        } elseif (is_bool($cell)) {
            $value = $cell ? '1' : '0';
        } else {
            $value = '';
        }

        if ($value === '') {
            return null;
        }

        return match ($field) {
            // Sel angka tanpa format tanggal = nomor seri tanggal Excel (sistem 1900).
            'birth_date' => $numeric && ctype_digit($value)
                ? CarbonImmutable::create(1899, 12, 30)?->addDays((int) $value)->toDateString()
                : $value,
            // NISN 10 digit yang diketik sebagai angka kehilangan 0 di depan.
            'nisn' => $numeric && ctype_digit($value) ? str_pad($value, 10, '0', STR_PAD_LEFT) : $value,
            'gender' => match (Str::upper($value)) {
                'L', 'LAKI-LAKI', 'LAKI LAKI' => 'L',
                'P', 'PEREMPUAN' => 'P',
                default => $value,
            },
            'guardian_relation' => Str::lower($value),
            'guardian_phone' => PhoneNumber::normalize($value),
            'unit' => Str::upper($value),
            default => $value,
        };
    }

    private function csvDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $line = $handle === false ? false : fgets($handle);

        if ($handle !== false) {
            fclose($handle);
        }

        // Excel berbahasa Indonesia menyimpan CSV dengan titik koma.
        return is_string($line) && substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    /**
     * Memeriksa semua baris terhadap data yang dimuat sekaligus (tanpa query per baris).
     *
     * @param  list<array{row: int, values: array<string, string|null>}>  $rows
     * @return list<StudentImportRow>
     */
    private function analyze(array $rows): array
    {
        $values = array_column($rows, 'values');
        $nisList = array_values(array_unique(array_filter(array_column($values, 'nis'))));
        $nisnList = array_values(array_unique(array_filter(array_column($values, 'nisn'))));
        $phones = array_values(array_unique(array_filter(array_column($values, 'guardian_phone'))));

        $units = Unit::query()->where('is_active', true)->get(['id', 'code'])
            ->keyBy(fn (Unit $unit): string => Str::upper($unit->code));
        $year = AcademicYear::query()->where('is_active', true)->first();
        $classrooms = $year === null
            ? new Collection
            : Classroom::query()
                ->where('academic_year_id', $year->id)
                ->where('is_active', true)
                ->get(['id', 'unit_id', 'name'])
                ->keyBy(fn (Classroom $classroom): string => $classroom->unit_id.'|'.Str::lower($classroom->name));

        // NIS dicocokkan tanpa beda huruf besar/kecil, mengikuti collation MariaDB.
        $students = Student::withTrashed()
            ->with('unit:id,code')
            ->whereIn('nis', $nisList)
            ->get()
            ->keyBy(fn (Student $student): string => Str::lower($student->nis));
        $studentIds = $students->modelKeys();

        $enrollments = $year === null
            ? []
            : Enrollment::query()
                ->whereIn('student_id', $studentIds)
                ->where('academic_year_id', $year->id)
                ->pluck('classroom_id', 'student_id')
                ->all();

        $nisnOwners = Student::withTrashed()->whereIn('nisn', $nisnList)->pluck('id', 'nisn')->all();

        $users = [];

        foreach (User::withTrashed()
            ->with(['guardian' => fn ($query) => $query->withCount('students')])
            ->where(fn (Builder $query) => $query->whereIn('phone', $phones)->orWhereIn('username', $phones))
            ->orderBy('id')
            ->get() as $user) {
            foreach ([$user->phone, $user->username] as $key) {
                if ($key !== null && ! isset($users[$key])) {
                    $users[$key] = $user;
                }
            }
        }

        $links = GuardianStudent::query()
            ->whereIn('student_id', $studentIds)
            ->get(['guardian_id', 'student_id'])
            ->mapWithKeys(fn (GuardianStudent $link): array => [$link->guardian_id.'|'.$link->student_id => true])
            ->all();

        $seenNis = [];
        $seenNisn = [];
        $plannedPhones = [];
        $result = [];

        foreach ($rows as ['row' => $rowNumber, 'values' => $row]) {
            $nisKey = Str::lower((string) $row['nis']);

            if ($row['nis'] !== null && isset($seenNis[$nisKey])) {
                $result[] = $this->errorRow($rowNumber, $row, [
                    "NIS {$row['nis']} muncul lebih dari sekali di berkas (pertama di baris {$seenNis[$nisKey]}).",
                ]);

                continue;
            }

            if ($row['nis'] !== null) {
                $seenNis[$nisKey] = $rowNumber;
            }

            [$errors, $guardian] = $this->validate($row);

            $unit = $row['unit'] === null ? null : $units->get($row['unit']);

            if ($row['unit'] !== null && $unit === null) {
                $errors[] = "Unit \"{$row['unit']}\" tidak ditemukan atau nonaktif.";
            }

            $classroom = null;

            if ($row['classroom'] !== null && $unit !== null) {
                $classroom = $classrooms->get($unit->id.'|'.Str::lower($row['classroom']));

                if ($year === null) {
                    $errors[] = 'Belum ada tahun ajaran aktif. Kosongkan kolom Rombel atau aktifkan tahun ajaran.';
                } elseif ($classroom === null) {
                    $errors[] = "Rombel \"{$row['classroom']}\" tidak ada di unit {$unit->code} pada tahun ajaran aktif.";
                }
            }

            $student = $row['nis'] === null ? null : $students->get($nisKey);

            if ($student?->trashed() === true) {
                $errors[] = "NIS {$row['nis']} milik siswa yang sudah diarsipkan.";
            } elseif ($student !== null && $unit !== null && $student->unit_id !== $unit->id) {
                $errors[] = "NIS {$row['nis']} sudah terdaftar di unit {$student->unit->code}. Unit siswa tidak bisa diubah lewat impor.";
            }

            if ($row['nisn'] !== null) {
                $owner = $nisnOwners[$row['nisn']] ?? null;

                if (isset($seenNisn[$row['nisn']])) {
                    $errors[] = "NISN {$row['nisn']} muncul lebih dari sekali di berkas (pertama di baris {$seenNisn[$row['nisn']]}).";
                } elseif ($owner !== null && $owner !== $student?->id) {
                    $errors[] = 'NISN ini sudah dipakai siswa lain.';
                }

                $seenNisn[$row['nisn']] ??= $rowNumber;
            }

            $guardianAction = StudentImportRow::GUARDIAN_NONE;

            if ($guardian !== null) {
                $user = $users[$guardian['phone']] ?? null;
                $linked = $student !== null && $user?->guardian !== null && isset($links[$user->guardian->id.'|'.$student->id]);
                $planned = $plannedPhones[$guardian['phone']] ?? 0;

                $guardianAction = match (true) {
                    $user?->trashed() === true => null,
                    $linked => StudentImportRow::GUARDIAN_ALREADY_LINKED,
                    // Akun dibuat oleh baris sebelumnya di berkas ini (kakak-adik).
                    $planned > 0 => StudentImportRow::GUARDIAN_LINK_EXISTING,
                    $user === null => StudentImportRow::GUARDIAN_NEW_ACCOUNT,
                    $user->guardian !== null => StudentImportRow::GUARDIAN_LINK_EXISTING,
                    default => StudentImportRow::GUARDIAN_STAFF_ACCOUNT,
                };

                if ($guardianAction === null) {
                    $errors[] = GuardianAccountException::phoneHeldByArchivedAccount()->getMessage();
                    $guardianAction = StudentImportRow::GUARDIAN_NONE;
                } elseif (! $linked && (int) ($user?->guardian?->getAttribute('students_count') ?? 0) + $planned + 1 > GuardianRequest::MAX_CHILDREN) {
                    $errors[] = "Wali dengan No. HP {$guardian['phone']} sudah terhubung dengan ".GuardianRequest::MAX_CHILDREN.' anak.';
                } elseif (! $linked && $errors === []) {
                    $plannedPhones[$guardian['phone']] = $planned + 1;
                }
            }

            if ($errors !== [] || $unit === null) {
                $result[] = $this->errorRow($rowNumber, $row, $errors, $guardianAction);

                continue;
            }

            $data = $this->studentData($row, $student, $classroom?->id);
            $changes = $student === null ? [] : $this->changes($row, $student, $enrollments[$student->id] ?? null, $classroom?->id, $guardianAction, $guardian !== null);

            $status = match (true) {
                $student === null => StudentImportRow::STATUS_NEW,
                $changes === [] => StudentImportRow::STATUS_UNCHANGED,
                default => StudentImportRow::STATUS_UPDATE,
            };

            $result[] = new StudentImportRow(
                row: $rowNumber,
                status: $status,
                errors: [],
                changes: $changes,
                nis: (string) $row['nis'],
                name: (string) $row['name'],
                unit: $unit->code,
                classroom: (string) $row['classroom'],
                guardianName: (string) $row['guardian_name'],
                guardianPhone: (string) $row['guardian_phone'],
                guardianAction: $guardianAction,
                student: $student,
                unitId: $unit->id,
                studentData: $data,
                guardian: $guardian,
            );
        }

        return $result;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  list<string>  $errors
     */
    private function errorRow(int $rowNumber, array $row, array $errors, string $guardianAction = StudentImportRow::GUARDIAN_NONE): StudentImportRow
    {
        return new StudentImportRow(
            row: $rowNumber,
            status: StudentImportRow::STATUS_ERROR,
            errors: $errors,
            changes: [],
            nis: (string) $row['nis'],
            name: (string) $row['name'],
            unit: (string) $row['unit'],
            classroom: (string) $row['classroom'],
            guardianName: (string) $row['guardian_name'],
            guardianPhone: (string) $row['guardian_phone'],
            guardianAction: $guardianAction,
        );
    }

    /**
     * Aturan sama dengan form Siswa dan Wali; keunikan diperiksa terpisah untuk seluruh berkas.
     *
     * @param  array<string, string|null>  $row
     * @return array{0: list<string>, 1: array{name: string, phone: string, relation: GuardianRelation}|null}
     */
    private function validate(array $row): array
    {
        $rules = [...StudentRequest::fieldRules(), 'unit' => ['required', 'string']];
        $guardianFields = ['guardian_name', 'guardian_relation', 'guardian_phone'];
        $filled = array_filter($guardianFields, fn (string $field): bool => $row[$field] !== null);
        $partial = $filled !== [] && count($filled) < count($guardianFields);

        if (count($filled) === count($guardianFields)) {
            $contact = GuardianRequest::contactRules();
            $rules['guardian_name'] = $contact['name'];
            $rules['guardian_relation'] = $contact['relation'];
            $rules['guardian_phone'] = $contact['phone'];
        }

        $validator = Validator::make(
            $row,
            $rules,
            [
                ...StudentRequest::fieldMessages(),
                'guardian_phone.regex' => 'Nomor HP wali harus nomor seluler Indonesia, mis. 0812xxxxxxxx.',
            ],
            [
                ...StudentRequest::fieldAttributes(),
                'unit' => 'unit',
                'guardian_name' => 'nama wali',
                'guardian_relation' => 'hubungan wali',
                'guardian_phone' => 'No. HP wali',
            ],
        );

        $errors = array_values(array_map(fn (string $message): string => Str::ucfirst($message), $validator->errors()->all()));

        if ($partial) {
            $errors[] = 'Data wali harus lengkap: nama wali, hubungan wali, dan No. HP wali, atau kosongkan ketiganya.';
        }

        $guardian = null;

        if ($errors === [] && count($filled) === count($guardianFields)) {
            $guardian = [
                'name' => (string) $row['guardian_name'],
                'phone' => (string) $row['guardian_phone'],
                'relation' => GuardianRelation::from((string) $row['guardian_relation']),
            ];
        }

        return [$errors, $guardian];
    }

    /**
     * Siswa terdaftar: sel kosong mempertahankan nilai lama, status tidak diubah.
     *
     * @param  array<string, string|null>  $row
     * @return StudentData
     */
    private function studentData(array $row, ?Student $student, ?int $classroomId): array
    {
        return [
            'nis' => $student !== null ? $student->nis : (string) $row['nis'],
            'nisn' => $row['nisn'] ?? $student?->nisn,
            'name' => (string) $row['name'],
            'nickname' => $row['nickname'] ?? $student?->nickname,
            'gender' => (string) $row['gender'],
            'birth_place' => $row['birth_place'] ?? $student?->birth_place,
            'birth_date' => $row['birth_date'] ?? $student?->birth_date?->toDateString(),
            'address' => $row['address'] ?? $student?->address,
            'status' => $student === null ? StudentStatus::Active->value : $student->status->value,
            'classroom_id' => $classroomId,
        ];
    }

    /**
     * @param  array<string, string|null>  $row
     * @return list<string>
     */
    private function changes(array $row, Student $student, ?int $enrolledClassroomId, ?int $classroomId, string $guardianAction, bool $hasGuardian): array
    {
        $current = [
            'nisn' => $student->nisn,
            'name' => $student->name,
            'nickname' => $student->nickname,
            'gender' => $student->gender->value,
            'birth_place' => $student->birth_place,
            'birth_date' => $student->birth_date?->toDateString(),
            'address' => $student->address,
        ];
        $labels = StudentRequest::fieldAttributes();
        $changes = [];

        foreach (self::COMPARED_FIELDS as $field) {
            if ($row[$field] !== null && $row[$field] !== $current[$field]) {
                $changes[] = $labels[$field];
            }
        }

        if ($classroomId !== null && $classroomId !== $enrolledClassroomId) {
            $changes[] = 'rombel';
        }

        if ($hasGuardian && $guardianAction !== StudentImportRow::GUARDIAN_ALREADY_LINKED) {
            $changes[] = 'wali';
        }

        return $changes;
    }
}
