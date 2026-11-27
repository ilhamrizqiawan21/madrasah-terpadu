<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Enums\GuardianRelation;
use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Students\StudentImportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

function importAdmin(RoleEnum $role = RoleEnum::Admin): User
{
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role->value);

    return $user;
}

/**
 * Satu baris berkas impor dengan nilai bawaan yang sah; kunci = judul kolom template.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function importRow(string $nis, array $overrides = []): array
{
    return [
        'NIS' => $nis,
        'NISN' => null,
        'Nama lengkap' => "Siswa Uji {$nis}",
        'Nama panggilan' => null,
        'Jenis kelamin' => 'L',
        'Tempat lahir' => 'Kolaka',
        'Tanggal lahir' => '2019-05-01',
        'Alamat' => null,
        'Unit' => 'MI',
        'Rombel' => '1-A',
        'Nama wali' => "Wali Uji {$nis}",
        'Hubungan wali' => 'ayah',
        'No. HP wali' => '0800000'.str_pad($nis, 5, '0', STR_PAD_LEFT),
        ...$overrides,
    ];
}

/**
 * Berkas .xlsx sungguhan yang ditulis dengan OpenSpout.
 *
 * @param  list<array<string, mixed>>  $rows
 * @param  list<string>|null  $headers
 */
function importXlsx(array $rows, ?array $headers = null): UploadedFile
{
    $headers ??= StudentImportService::HEADERS;
    $path = tempnam(sys_get_temp_dir(), 'impor').'.xlsx';

    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues($headers));

    foreach ($rows as $row) {
        // Sel tanggal diberi format tanggal seperti yang dibuat Excel saat mengetik tanggal.
        $writer->addRow(new Row(array_map(function (string $header) use ($row): Cell {
            $value = $row[$header] ?? null;

            return $value instanceof DateTimeInterface
                ? Cell::fromValue($value, (new Style)->withFormat('yyyy-mm-dd'))
                : Cell::fromValue($value);
        }, $headers)));
    }

    $writer->close();

    return new UploadedFile($path, 'siswa.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

/**
 * @return array{mi: Unit, year: AcademicYear, classroom: Classroom}
 */
function importSchool(): array
{
    $mi = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($mi)->for($year)->create(['name' => '1-A']);

    return ['mi' => $mi, 'year' => $year, 'classroom' => $classroom];
}

/**
 * @return array<string, int>
 */
function importTableCounts(): array
{
    return [
        'students' => Student::withTrashed()->count(),
        'enrollments' => Enrollment::query()->count(),
        'guardians' => Guardian::withTrashed()->count(),
        'links' => GuardianStudent::query()->count(),
        'users' => User::withTrashed()->count(),
        'audit' => AuditLog::query()->count(),
    ];
}

test('tamu diarahkan ke halaman login', function (): void {
    $this->get(route('admin.students.import.create'))->assertRedirect(route('login'));
    $this->get(route('admin.students.import.template'))->assertRedirect(route('login'));
    $this->post(route('admin.students.import.store'), ['file' => importXlsx([])])->assertRedirect(route('login'));
    $this->post(route('admin.students.import.commit'))->assertRedirect(route('login'));
});

test('peran tanpa izin kelola siswa dan wali ditolak', function (RoleEnum $role): void {
    importSchool();
    $user = importAdmin($role);

    $this->actingAs($user)->get(route('admin.students.import.create'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.students.import.template'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.students.import.store'), ['file' => importXlsx([importRow('1001')])])->assertForbidden();
    $this->actingAs($user)->get(route('admin.students.import.preview'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.students.import.commit'))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.students.import.destroy'))->assertForbidden();

    expect(Student::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('imports'))->toBe([]);
})->with([RoleEnum::Treasurer, RoleEnum::Teacher, RoleEnum::UnitHead]);

test('template berisi judul kolom impor', function (): void {
    $response = $this->actingAs(importAdmin())->get(route('admin.students.import.template'));

    $response->assertOk()->assertDownload('template-impor-siswa.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    $reader = new Reader;
    $reader->open($path);
    $firstRow = null;

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $firstRow = $row->toArray();

            break 2;
        }
    }

    $reader->close();

    expect($firstRow)->toBe(StudentImportService::HEADERS);
});

test('unggahan selain xlsx/csv atau lebih dari 2 MB ditolak', function (): void {
    $admin = importAdmin();

    $this->actingAs($admin)
        ->post(route('admin.students.import.store'), ['file' => UploadedFile::fake()->create('siswa.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('file');

    $this->actingAs($admin)
        ->post(route('admin.students.import.store'), ['file' => UploadedFile::fake()->create('siswa.xlsx', 2049, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')])
        ->assertSessionHasErrors('file');

    expect(Storage::disk('local')->allFiles('imports'))->toBe([]);
});

test('berkas lebih dari 1.500 baris ditolak', function (): void {
    importSchool();
    $rows = array_map(fn (int $i): array => importRow((string) (10000 + $i)), range(1, 1501));

    $this->actingAs(importAdmin())
        ->post(route('admin.students.import.store'), ['file' => importXlsx($rows)])
        ->assertSessionHasErrors(['file' => 'Berkas berisi 1.501 baris data. Maksimal 1.500 baris per impor; pecah berkas lalu impor bertahap.']);

    expect(Storage::disk('local')->allFiles('imports'))->toBe([]);
});

test('berkas tanpa kolom wajib atau tanpa baris data ditolak', function (): void {
    $admin = importAdmin();

    $this->actingAs($admin)
        ->post(route('admin.students.import.store'), ['file' => importXlsx([['Nama' => 'Siswa Uji']], ['Nama', 'Kelas'])])
        ->assertSessionHasErrors(['file' => 'Kolom NIS, Nama lengkap, Jenis kelamin, Unit tidak ditemukan. Unduh template lalu salin data ke dalamnya.']);

    $this->actingAs($admin)
        ->post(route('admin.students.import.store'), ['file' => importXlsx([])])
        ->assertSessionHasErrors(['file' => 'Berkas tidak berisi baris data.']);
});

test('pratinjau menandai baris baru, ubah, tanpa perubahan, dan galat tanpa menulis apa pun', function (): void {
    ['mi' => $mi, 'classroom' => $classroom] = importSchool();
    $existing = Student::factory()->for($mi)->create(['nis' => '2001', 'name' => 'Siswa Uji Lama', 'gender' => Gender::Male]);
    $same = Student::factory()->for($mi)->create([
        'nis' => '2002', 'name' => 'Siswa Uji 2002', 'gender' => Gender::Male, 'birth_place' => 'Kolaka', 'birth_date' => '2019-05-01',
    ]);
    Enrollment::factory()->for($same)->for($classroom)->create();

    $admin = importAdmin();
    $file = importXlsx([
        importRow('2000'),
        importRow('2001', ['Nama wali' => null, 'Hubungan wali' => null, 'No. HP wali' => null]),
        importRow('2002', ['Nama wali' => null, 'Hubungan wali' => null, 'No. HP wali' => null]),
        importRow('2003', ['Unit' => 'SMP']),
        importRow('2004', ['Rombel' => '9-Z']),
        importRow('2005', ['Jenis kelamin' => 'X', 'No. HP wali' => '12345']),
        importRow('2000', ['Nama lengkap' => 'Siswa Uji Ganda']),
    ]);

    $before = importTableCounts();

    $this->actingAs($admin)
        ->post(route('admin.students.import.store'), ['file' => $file])
        ->assertRedirect(route('admin.students.import.preview'));

    $this->actingAs($admin)
        ->get(route('admin.students.import.preview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/students/import')
            ->where('step', 'preview')
            ->where('preview.summary', ['total' => 7, 'new' => 1, 'update' => 1, 'unchanged' => 1, 'error' => 4])
            ->where('preview.rows.0.row', 2)
            ->where('preview.rows.0.status', 'new')
            ->where('preview.rows.0.guardian_action', 'new_account')
            ->where('preview.rows.1.status', 'update')
            ->where('preview.rows.1.changes', ['nama lengkap', 'tempat lahir', 'tanggal lahir', 'rombel'])
            ->where('preview.rows.2.status', 'unchanged')
            ->where('preview.rows.3.status', 'error')
            ->where('preview.rows.3.errors', ['Unit "SMP" tidak ditemukan atau nonaktif.'])
            ->where('preview.rows.4.errors', ['Rombel "9-Z" tidak ada di unit MI pada tahun ajaran aktif.'])
            ->where('preview.rows.5.errors', [
                'Jenis kelamin yang dipilih tidak valid.',
                'Nomor HP wali harus nomor seluler Indonesia, mis. 0812xxxxxxxx.',
            ])
            ->where('preview.rows.6.errors', ['NIS 2000 muncul lebih dari sekali di berkas (pertama di baris 2).'])
        );

    expect(importTableCounts())->toBe($before)
        ->and($existing->fresh()->name)->toBe('Siswa Uji Lama')
        ->and(Unit::query()->count())->toBe(1)
        ->and(Classroom::query()->count())->toBe(1);
});

test('simpan mengimpor baris sah, melaporkan baris galat, menautkan wali lewat nomor HP, dan mencatat audit ringkas', function (): void {
    ['mi' => $mi, 'year' => $year, 'classroom' => $classroom] = importSchool();
    $existing = Student::factory()->for($mi)->create(['nis' => '3001', 'name' => 'Siswa Uji Lama', 'gender' => Gender::Female]);

    // Wali terdaftar dengan nomor HP yang ditulis berbeda di berkas (+62 ...).
    $guardianUser = User::factory()->create(['name' => 'Wali Uji Terdaftar', 'username' => '080000000777', 'phone' => '080000000777']);
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $registered = Guardian::factory()->for($guardianUser)->create(['relation' => GuardianRelation::Mother]);

    $admin = importAdmin();
    $file = importXlsx([
        importRow('3000', ['No. HP wali' => '0800-0000-0555', 'NISN' => '0012345678']),
        importRow('3001', ['Jenis kelamin' => 'P', 'Nama wali' => 'Ibu Uji', 'Hubungan wali' => 'ibu', 'No. HP wali' => '+62 800 0000 0777']),
        importRow('3002', ['Rombel' => '9-Z']),
        importRow('3003', ['Nama lengkap' => null, 'Unit' => 'SMP']),
        importRow('3004', ['Rombel' => null, 'No. HP wali' => '080000000555']),
    ]);

    $this->actingAs($admin)->post(route('admin.students.import.store'), ['file' => $file]);
    $stored = Storage::disk('local')->allFiles('imports');
    expect($stored)->toHaveCount(1);

    $this->actingAs($admin)
        ->post(route('admin.students.import.commit'))
        ->assertRedirect(route('admin.students.import.result'));

    $this->actingAs($admin)
        ->get(route('admin.students.import.result'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/students/import')
            ->where('step', 'result')
            ->where('result.created', 2)
            ->where('result.updated', 1)
            ->where('result.unchanged', 0)
            ->where('result.failed', [
                ['row' => 4, 'error' => 'Rombel "9-Z" tidak ada di unit MI pada tahun ajaran aktif.'],
                ['row' => 5, 'error' => 'Bidang nama lengkap wajib diisi. Unit "SMP" tidak ditemukan atau nonaktif.'],
            ])
            ->where('result.new_guardians', [['name' => 'Wali Uji 3000', 'phone' => '080000000555']])
        );

    $new = Student::query()->where('nis', '3000')->firstOrFail();
    $sibling = Student::query()->where('nis', '3004')->firstOrFail();
    $newGuardian = Guardian::query()->where('phone', '080000000555')->firstOrFail();

    expect($new->unit_id)->toBe($mi->id)
        ->and($new->nisn)->toBe('0012345678')
        ->and($new->birth_date?->toDateString())->toBe('2019-05-01')
        ->and(Enrollment::query()->where('student_id', $new->id)->where('academic_year_id', $year->id)->value('classroom_id'))->toBe($classroom->id)
        ->and(Enrollment::query()->where('student_id', $sibling->id)->exists())->toBeFalse()
        // Satu akun wali untuk kakak-adik dengan nomor HP sama.
        ->and(Guardian::query()->where('phone', '080000000555')->count())->toBe(1)
        ->and($newGuardian->user->username)->toBe('080000000555')
        ->and($newGuardian->user->hasRole(RoleEnum::Guardian->value))->toBeTrue()
        ->and($newGuardian->students()->pluck('students.id')->sort()->values()->all())->toBe([$new->id, $sibling->id])
        ->and(GuardianStudent::query()->where('student_id', $new->id)->value('is_primary'))->toBeTrue()
        // Nomor +62 ditautkan ke wali terdaftar, tanpa akun ganda.
        ->and($existing->fresh()->name)->toBe('Siswa Uji 3001')
        ->and($existing->fresh()->gender)->toBe(Gender::Female)
        ->and($registered->students()->pluck('students.id')->all())->toBe([$existing->id])
        ->and(User::query()->where('phone', '080000000777')->count())->toBe(1)
        ->and(Student::query()->whereIn('nis', ['3002', '3003'])->exists())->toBeFalse()
        ->and(Unit::query()->count())->toBe(1)
        ->and(Classroom::query()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('imports'))->toBe([]);

    $audit = AuditLog::query()->where('action', 'students_imported')->sole();

    expect($audit->user_id)->toBe($admin->id)
        ->and($audit->after)->toBe([
            'rows' => 5,
            'created' => 2,
            'updated' => 1,
            'unchanged' => 0,
            'failed' => 2,
            'guardians_created' => 1,
        ]);
});

test('impor ulang berkas yang sama idempoten: tanpa duplikat dan semua baris tercatat tanpa perubahan', function (): void {
    importSchool();
    $admin = importAdmin();
    $rows = [
        importRow('4000'),
        importRow('4001', ['No. HP wali' => '080000004000', 'Nama wali' => 'Wali Uji 4000']),
        importRow('4002', ['Rombel' => null, 'Nama wali' => null, 'Hubungan wali' => null, 'No. HP wali' => null]),
    ];

    $this->actingAs($admin)->post(route('admin.students.import.store'), ['file' => importXlsx($rows)]);
    $this->actingAs($admin)->post(route('admin.students.import.commit'));

    $after = importTableCounts();

    $this->actingAs($admin)->post(route('admin.students.import.store'), ['file' => importXlsx($rows)]);
    $this->actingAs($admin)
        ->get(route('admin.students.import.preview'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('preview.summary', ['total' => 3, 'new' => 0, 'update' => 0, 'unchanged' => 3, 'error' => 0])
        );

    $this->actingAs($admin)->post(route('admin.students.import.commit'));

    $this->actingAs($admin)
        ->get(route('admin.students.import.result'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.created', 0)
            ->where('result.updated', 0)
            ->where('result.unchanged', 3)
            ->where('result.failed', [])
            ->where('result.new_guardians', [])
        );

    // Hanya bertambah satu entri audit ringkasan impor kedua.
    expect(importTableCounts())->toBe([...$after, 'audit' => $after['audit'] + 1])
        ->and(Student::query()->count())->toBe(3)
        ->and(Guardian::query()->count())->toBe(1)
        ->and(GuardianStudent::query()->where('is_primary', true)->count())->toBe(2);
});

test('sel kosong tidak mengosongkan data siswa yang sudah ada', function (): void {
    ['mi' => $mi, 'classroom' => $classroom] = importSchool();
    $student = Student::factory()->for($mi)->create([
        'nis' => '5000', 'name' => 'Siswa Uji 5000', 'gender' => Gender::Male, 'nickname' => 'Uji',
        'birth_place' => 'Kolaka', 'birth_date' => '2019-05-01', 'address' => 'Jl. Uji',
    ]);
    Enrollment::factory()->for($student)->for($classroom)->create();
    $admin = importAdmin();

    $this->actingAs($admin)->post(route('admin.students.import.store'), ['file' => importXlsx([
        importRow('5000', ['Tempat lahir' => null, 'Tanggal lahir' => null, 'Rombel' => null, 'Nama wali' => null, 'Hubungan wali' => null, 'No. HP wali' => null]),
    ])]);

    $this->actingAs($admin)
        ->get(route('admin.students.import.preview'))
        ->assertInertia(fn (Assert $page) => $page->where('preview.rows.0.status', 'unchanged'));

    $this->actingAs($admin)->post(route('admin.students.import.commit'));

    $fresh = $student->fresh();

    expect($fresh->nickname)->toBe('Uji')
        ->and($fresh->address)->toBe('Jl. Uji')
        ->and($fresh->birth_place)->toBe('Kolaka')
        ->and(Enrollment::query()->where('student_id', $student->id)->value('classroom_id'))->toBe($classroom->id);
});

test('NIS milik siswa unit lain atau siswa terarsip dan wali tidak lengkap ditolak per baris', function (): void {
    ['mi' => $mi] = importSchool();
    $ra = Unit::factory()->create(['code' => 'RA']);
    Student::factory()->for($ra)->create(['nis' => '6000']);
    Student::factory()->for($mi)->create(['nis' => '6001'])->delete();
    Student::factory()->for($mi)->create(['nis' => '6099', 'nisn' => '1234567890']);
    $admin = importAdmin();

    $this->actingAs($admin)->post(route('admin.students.import.store'), ['file' => importXlsx([
        importRow('6000'),
        importRow('6001'),
        importRow('6002', ['Nama wali' => null]),
        importRow('6003', ['NISN' => '1234567890']),
    ])]);

    $this->actingAs($admin)
        ->get(route('admin.students.import.preview'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('preview.rows.0.errors', ['NIS 6000 sudah terdaftar di unit RA. Unit siswa tidak bisa diubah lewat impor.'])
            ->where('preview.rows.1.errors', ['NIS 6001 milik siswa yang sudah diarsipkan.'])
            ->where('preview.rows.2.errors', ['Data wali harus lengkap: nama wali, hubungan wali, dan No. HP wali, atau kosongkan ketiganya.'])
            ->where('preview.rows.3.errors', ['NISN ini sudah dipakai siswa lain.'])
        );
});

test('sel tanggal Excel dan NIS berupa angka dibaca dengan benar', function (): void {
    importSchool();
    $admin = importAdmin();

    $this->actingAs($admin)->post(route('admin.students.import.store'), ['file' => importXlsx([
        importRow('7000', ['NIS' => 7000, 'Tanggal lahir' => new DateTimeImmutable('2018-02-03'), 'No. HP wali' => 80000007000]),
    ])]);
    $this->actingAs($admin)->post(route('admin.students.import.commit'));

    $student = Student::query()->where('nis', '7000')->firstOrFail();

    expect($student->birth_date?->toDateString())->toBe('2018-02-03')
        ->and(Guardian::query()->where('phone', '080000007000')->exists())->toBeTrue();
});

test('berkas CSV dengan pemisah titik koma juga bisa diimpor', function (): void {
    importSchool();
    $admin = importAdmin();
    $path = tempnam(sys_get_temp_dir(), 'impor').'.csv';
    $row = importRow('8000');
    file_put_contents($path, implode(';', StudentImportService::HEADERS)."\n".implode(';', array_map(
        fn (string $header): string => (string) ($row[$header] ?? ''),
        StudentImportService::HEADERS,
    ))."\n");

    $this->actingAs($admin)->post(route('admin.students.import.store'), [
        'file' => new UploadedFile($path, 'siswa.csv', 'text/csv', null, true),
    ])->assertRedirect(route('admin.students.import.preview'));

    $this->actingAs($admin)->post(route('admin.students.import.commit'));

    expect(Student::query()->where('nis', '8000')->exists())->toBeTrue();
});

test('kata sandi awal wali hasil impor tidak tampil dan tidak tersimpan polos', function (): void {
    importSchool();
    $admin = importAdmin();

    $this->actingAs($admin)->post(route('admin.students.import.store'), ['file' => importXlsx([importRow('9000')])]);
    $this->actingAs($admin)->post(route('admin.students.import.commit'));

    $user = User::query()->where('username', '080000009000')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.students.import.result'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.new_guardians.0', ['name' => 'Wali Uji 9000', 'phone' => '080000009000'])
        );

    expect(Hash::isHashed($user->password))->toBeTrue()
        ->and(json_encode(session()->all()))->not->toContain('password');
});

test('pratinjau dan simpan tanpa unggahan aktif dialihkan ke langkah unggah', function (): void {
    $admin = importAdmin();

    $this->actingAs($admin)->get(route('admin.students.import.preview'))->assertRedirect(route('admin.students.import.create'));
    $this->actingAs($admin)->post(route('admin.students.import.commit'))->assertRedirect(route('admin.students.import.create'));
    $this->actingAs($admin)->get(route('admin.students.import.result'))->assertRedirect(route('admin.students.import.create'));
});

test('batal menghapus berkas unggahan', function (): void {
    importSchool();
    $admin = importAdmin();

    $this->actingAs($admin)->post(route('admin.students.import.store'), ['file' => importXlsx([importRow('9100')])]);
    expect(Storage::disk('local')->allFiles('imports'))->toHaveCount(1);

    $this->actingAs($admin)
        ->delete(route('admin.students.import.destroy'))
        ->assertRedirect(route('admin.students.import.create'));

    expect(Storage::disk('local')->allFiles('imports'))->toBe([])
        ->and(Student::query()->count())->toBe(0);

    $this->actingAs($admin)->get(route('admin.students.import.preview'))->assertRedirect(route('admin.students.import.create'));
});

test('halaman unggah tersedia untuk admin', function (): void {
    $this->actingAs(importAdmin())
        ->get(route('admin.students.import.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/students/import')
            ->where('step', 'upload')
            ->where('maxRows', 1500)
        );
});

test('tombol impor hanya ditawarkan kepada peran yang boleh mengimpor', function (): void {
    importSchool();

    $this->actingAs(importAdmin())
        ->get(route('admin.students.index'))
        ->assertInertia(fn (Assert $page) => $page->where('canImport', true));

    $this->actingAs(importAdmin(RoleEnum::Treasurer))
        ->get(route('admin.students.index'))
        ->assertInertia(fn (Assert $page) => $page->where('canImport', false));
});
