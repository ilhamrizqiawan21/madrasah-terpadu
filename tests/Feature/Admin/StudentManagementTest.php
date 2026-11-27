<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Enums\Role as RoleEnum;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Support\SchoolContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

function studentStaff(RoleEnum $role = RoleEnum::Admin): User
{
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role->value);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function studentPayload(Unit $unit, array $overrides = []): array
{
    return [
        'unit_id' => $unit->id,
        'nis' => '2026001',
        'nisn' => null,
        'name' => 'Siswa Uji 001',
        'nickname' => 'Uji',
        'gender' => Gender::Male->value,
        'birth_place' => 'Kolaka',
        'birth_date' => '2019-05-01',
        'address' => null,
        'status' => StudentStatus::Active->value,
        'classroom_id' => null,
        ...$overrides,
    ];
}

/**
 * JPEG berderau (sulit dikompres) agar kompresi foto benar-benar teruji.
 */
function noisyPhoto(int $width = 1000, int $height = 800): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    mt_srand(205);

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            imagesetpixel($image, $x, $y, mt_rand(0, 0xFFFFFF));
        }
    }

    ob_start();
    imagejpeg($image, null, 90);
    $binary = (string) ob_get_clean();

    $path = tempnam(sys_get_temp_dir(), 'foto').'.jpg';
    file_put_contents($path, $binary);

    return new UploadedFile($path, 'foto.jpg', 'image/jpeg', null, true);
}

/**
 * @return array{0: Unit, 1: AcademicYear, 2: Classroom}
 */
function activeScope(): array
{
    $unit = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($unit)->for($year)->create(['grade' => '1', 'name' => '1-A']);

    return [$unit, $year, $classroom];
}

test('tamu diarahkan ke halaman login saat membuka siswa atau fotonya', function (): void {
    $student = Student::factory()->create(['photo_path' => 'students/photos/x.jpg']);

    $this->get(route('admin.students.index'))->assertRedirect(route('login'));
    $this->get(route('admin.students.photo', $student))->assertRedirect(route('login'));
});

test('staf dengan izin lihat siswa dapat membuka daftar tetapi tidak dapat mengubah', function (RoleEnum $role): void {
    [$unit, , $classroom] = activeScope();
    $student = Student::factory()->for($unit)->create();
    $user = studentStaff($role);

    $this->actingAs($user)->get(route('admin.students.index'))->assertOk();
    $this->actingAs($user)->post(route('admin.students.store'), studentPayload($unit))->assertForbidden();
    $this->actingAs($user)->put(route('admin.students.update', $student), studentPayload($unit, ['classroom_id' => $classroom->id]))->assertForbidden();
    $this->actingAs($user)->patch(route('admin.students.status.update', $student), ['status' => 'inactive'])->assertForbidden();

    expect(Student::query()->count())->toBe(1)
        ->and(Enrollment::query()->count())->toBe(0)
        ->and($student->fresh()->status)->toBe(StudentStatus::Active);
})->with([
    RoleEnum::Treasurer,
    RoleEnum::Teacher,
    RoleEnum::Counselor,
    RoleEnum::UnitHead,
]);

test('peran tanpa izin lihat siswa ditolak', function (RoleEnum $role): void {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)->get(route('admin.students.index'))->assertForbidden();
})->with([
    RoleEnum::GateOfficer,
    RoleEnum::CanteenCashier,
    RoleEnum::Guardian,
]);

test('daftar siswa memakai unit dari topbar dan menampilkan rombel tahun ajaran aktif', function (): void {
    [$unit, $year, $classroom] = activeScope();
    $tpq = Unit::factory()->create(['code' => 'TPQ']);
    $old = AcademicYear::factory()->create();
    $oldClassroom = Classroom::factory()->for($unit)->for($old)->create(['name' => 'TK-B']);

    $student = Student::factory()->for($unit)->create(['name' => 'Siswa Uji 001', 'gender' => Gender::Female]);
    Enrollment::factory()->for($student)->for($oldClassroom)->create();
    Enrollment::factory()->for($student)->for($classroom)->create();
    Student::factory()->for($tpq)->create(['name' => 'Siswa Uji TPQ']);

    $this->actingAs(studentStaff())
        ->withSession([SchoolContext::SESSION_UNIT => 'MI'])
        ->get(route('admin.students.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/students/index')
            ->where('filters.unit_id', $unit->id)
            ->where('filters.academic_year_id', $year->id)
            ->where('filters.status', 'active')
            ->has('students.data', 1)
            ->where('students.data.0.name', 'Siswa Uji 001')
            ->where('students.data.0.gender.value', 'P')
            ->where('students.data.0.classroom.name', '1-A')
            ->where('students.data.0.active_classroom_id', $classroom->id)
            ->has('students.data.0.enrollments', 2)
            ->where('students.data.0.photo_url', null)
            ->has('classroomOptions', 1)
            ->has('enrollmentClassroomOptions', 1)
            ->where('activeAcademicYear.id', $year->id));
});

test('daftar siswa dapat dicari nama atau NIS dan disaring rombel serta status', function (): void {
    [$unit, $year, $classroom] = activeScope();
    $other = Classroom::factory()->for($unit)->for($year)->create(['name' => '1-B']);

    $a = Student::factory()->for($unit)->create(['name' => 'Siswa Uji Alfa', 'nis' => '1001']);
    $b = Student::factory()->for($unit)->create(['name' => 'Siswa Uji Beta', 'nis' => '1002']);
    Student::factory()->for($unit)->create(['name' => 'Siswa Uji Gama', 'nis' => '1003']);
    Student::factory()->for($unit)->status(StudentStatus::Graduated)->create(['name' => 'Siswa Uji Lulus', 'nis' => '1004']);
    Enrollment::factory()->for($a)->for($classroom)->create();
    Enrollment::factory()->for($b)->for($other)->create();

    $admin = studentStaff();
    $base = ['unit_id' => $unit->id];

    $this->actingAs($admin)->get(route('admin.students.index', $base))
        ->assertInertia(fn (Assert $page) => $page
            ->has('students.data', 3)
            ->where('students.data.0.name', 'Siswa Uji Alfa')
            ->where('students.data.2.name', 'Siswa Uji Gama'));

    $this->actingAs($admin)->get(route('admin.students.index', [...$base, 'search' => 'beta']))
        ->assertInertia(fn (Assert $page) => $page->has('students.data', 1)->where('students.data.0.nis', '1002'));

    $this->actingAs($admin)->get(route('admin.students.index', [...$base, 'search' => '1003']))
        ->assertInertia(fn (Assert $page) => $page->has('students.data', 1)->where('students.data.0.name', 'Siswa Uji Gama'));

    $this->actingAs($admin)->get(route('admin.students.index', [...$base, 'classroom_id' => $classroom->id]))
        ->assertInertia(fn (Assert $page) => $page->has('students.data', 1)->where('students.data.0.name', 'Siswa Uji Alfa'));

    $this->actingAs($admin)->get(route('admin.students.index', [...$base, 'classroom_id' => 'none']))
        ->assertInertia(fn (Assert $page) => $page->has('students.data', 1)->where('students.data.0.name', 'Siswa Uji Gama'));

    $this->actingAs($admin)->get(route('admin.students.index', [...$base, 'status' => 'graduated']))
        ->assertInertia(fn (Assert $page) => $page->has('students.data', 1)->where('students.data.0.status.label', 'Lulus'));

    $this->actingAs($admin)->get(route('admin.students.index', [...$base, 'status' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->has('students.data', 4));

    // Saringan tanpa hasil tetap memberi tahu bahwa unit ini sudah punya siswa.
    $this->actingAs($admin)->get(route('admin.students.index', [...$base, 'status' => 'moved']))
        ->assertInertia(fn (Assert $page) => $page->has('students.data', 0)->where('unitHasStudents', true));
});

test('daftar siswa dipaginasi 25 per halaman', function (): void {
    [$unit] = activeScope();
    Student::factory()->for($unit)->count(26)->create();

    $this->actingAs(studentStaff())
        ->get(route('admin.students.index', ['unit_id' => $unit->id, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('students.data', 1)
            ->where('students.meta.total', 26)
            ->where('students.meta.per_page', 25));
});

test('admin menambah siswa beserta rombel tahun ajaran aktif dengan audit', function (): void {
    [$unit, $year, $classroom] = activeScope();

    $this->actingAs(studentStaff())
        ->post(route('admin.students.store'), studentPayload($unit, ['classroom_id' => $classroom->id, 'nisn' => '0123456789']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $student = Student::query()->sole();

    expect($student->name)->toBe('Siswa Uji 001')
        ->and($student->nickname)->toBe('Uji')
        ->and($student->nisn)->toBe('0123456789')
        ->and($student->birth_date?->toDateString())->toBe('2019-05-01')
        ->and($student->unit_id)->toBe($unit->id)
        ->and(Enrollment::query()->sole()->only(['student_id', 'classroom_id', 'academic_year_id']))
        ->toBe(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id])
        ->and(AuditLog::query()->where('subject_type', Student::class)->where('action', 'created')->count())->toBe(1)
        ->and(AuditLog::query()->where('subject_type', Enrollment::class)->where('action', 'enrolled')->count())->toBe(1);
});

test('NIS dan NISN harus unik termasuk terhadap siswa yang diarsipkan', function (): void {
    [$unit] = activeScope();
    $existing = Student::factory()->for($unit)->create(['nis' => '2026001', 'nisn' => '0123456789']);
    Student::factory()->for($unit)->create(['nis' => '2026009'])->delete();
    $admin = studentStaff();

    $this->actingAs($admin)
        ->post(route('admin.students.store'), studentPayload($unit, ['nisn' => '0123456789']))
        ->assertSessionHasErrors(['nis', 'nisn']);

    $this->actingAs($admin)
        ->post(route('admin.students.store'), studentPayload($unit, ['nis' => '2026009']))
        ->assertSessionHasErrors('nis');

    // Menyimpan ulang siswa yang sama tidak dianggap bentrok dengan dirinya sendiri.
    $this->actingAs($admin)
        ->put(route('admin.students.update', $existing), studentPayload($unit, ['nisn' => '0123456789', 'name' => 'Siswa Uji Ubah']))
        ->assertSessionHasNoErrors();

    expect(Student::withTrashed()->count())->toBe(2)
        ->and($existing->fresh()->name)->toBe('Siswa Uji Ubah');
});

test('validasi siswa menolak data yang tidak lengkap atau tidak sah', function (): void {
    [$unit] = activeScope();

    $this->actingAs(studentStaff())
        ->post(route('admin.students.store'), studentPayload($unit, [
            'nis' => '',
            'name' => '',
            'gender' => 'X',
            'status' => 'hilang',
            'nisn' => '12',
            'birth_date' => now()->addDay()->toDateString(),
        ]))
        ->assertSessionHasErrors(['nis', 'name', 'gender', 'status', 'nisn', 'birth_date']);

    expect(Student::query()->count())->toBe(0);
});

test('pindah rombel di tahun ajaran aktif memperbarui enrollment yang sama dan mencatat audit sebelum-sesudah', function (): void {
    [$unit, $year, $classroom] = activeScope();
    $target = Classroom::factory()->for($unit)->for($year)->create(['name' => '1-B']);
    $old = AcademicYear::factory()->create();
    $oldClassroom = Classroom::factory()->for($unit)->for($old)->create(['name' => 'TK-B']);
    $student = Student::factory()->for($unit)->create();
    $history = Enrollment::factory()->for($student)->for($oldClassroom)->create();
    $current = Enrollment::factory()->for($student)->for($classroom)->create();

    $this->actingAs(studentStaff())
        ->put(route('admin.students.update', $student), studentPayload($unit, [
            'nis' => $student->nis,
            'classroom_id' => $target->id,
        ]))
        ->assertSessionHasNoErrors();

    $audit = AuditLog::query()->where('action', 'enrollment_moved')->sole();

    expect(Enrollment::query()->where('student_id', $student->id)->count())->toBe(2)
        ->and($current->fresh()->classroom_id)->toBe($target->id)
        ->and($history->fresh()->classroom_id)->toBe($oldClassroom->id)
        ->and($audit->subject_type)->toBe(Enrollment::class)
        ->and($audit->subject_id)->toBe($current->id)
        ->and($audit->before)->toMatchArray(['classroom_id' => $classroom->id, 'classroom' => '1-A'])
        ->and($audit->after)->toMatchArray(['classroom_id' => $target->id, 'classroom' => '1-B']);
});

test('satu siswa hanya punya satu enrollment per tahun ajaran', function (): void {
    [$unit, $year, $classroom] = activeScope();
    $other = Classroom::factory()->for($unit)->for($year)->create(['name' => '1-B']);
    $student = Student::factory()->for($unit)->create();
    Enrollment::factory()->for($student)->for($classroom)->create();

    expect(fn () => Enrollment::query()->create([
        'student_id' => $student->id,
        'classroom_id' => $other->id,
        'academic_year_id' => $year->id,
    ]))->toThrow(QueryException::class);
});

test('rombel harus milik unit siswa dan tahun ajaran aktif', function (): void {
    [$unit, $year] = activeScope();
    $otherUnit = Unit::factory()->create();
    $foreign = Classroom::factory()->for($otherUnit)->for($year)->create();
    $past = Classroom::factory()->for($unit)->for(AcademicYear::factory()->create())->create();
    $inactive = Classroom::factory()->for($unit)->for($year)->inactive()->create();
    $admin = studentStaff();

    foreach ([$foreign, $past, $inactive] as $classroom) {
        $this->actingAs($admin)
            ->post(route('admin.students.store'), studentPayload($unit, ['classroom_id' => $classroom->id]))
            ->assertSessionHasErrors('classroom_id');
    }

    expect(Student::query()->count())->toBe(0);
});

test('rombel tidak dapat dipilih saat belum ada tahun ajaran aktif', function (): void {
    $unit = Unit::factory()->create();
    $classroom = Classroom::factory()->for($unit)->for(AcademicYear::factory()->create())->create();

    $this->actingAs(studentStaff())
        ->post(route('admin.students.store'), studentPayload($unit, ['classroom_id' => $classroom->id]))
        ->assertSessionHasErrors(['classroom_id' => 'Belum ada tahun ajaran aktif. Aktifkan tahun ajaran sebelum memilih rombel.']);

    $this->actingAs(studentStaff())
        ->post(route('admin.students.store'), studentPayload($unit))
        ->assertSessionHasNoErrors();

    expect(Student::query()->count())->toBe(1)
        ->and(Enrollment::query()->count())->toBe(0);
});

test('rombel tahun ajaran aktif tidak dapat dikosongkan setelah terisi', function (): void {
    [$unit, , $classroom] = activeScope();
    $student = Student::factory()->for($unit)->create();
    Enrollment::factory()->for($student)->for($classroom)->create();

    $this->actingAs(studentStaff())
        ->put(route('admin.students.update', $student), studentPayload($unit, ['nis' => $student->nis, 'classroom_id' => null]))
        ->assertSessionHasErrors('classroom_id');

    expect(Enrollment::query()->sole()->classroom_id)->toBe($classroom->id);
});

test('unit siswa tidak berubah saat data siswa diubah', function (): void {
    [$unit] = activeScope();
    $student = Student::factory()->for($unit)->create();

    $this->actingAs(studentStaff())
        ->put(route('admin.students.update', $student), studentPayload(Unit::factory()->create(), ['nis' => $student->nis]))
        ->assertSessionHasNoErrors();

    expect($student->fresh()->unit_id)->toBe($unit->id);
});

test('admin menonaktifkan siswa alih-alih menghapus dan dapat mengaktifkannya kembali', function (): void {
    [$unit] = activeScope();
    $student = Student::factory()->for($unit)->create();
    $admin = studentStaff();

    $this->actingAs($admin)
        ->patch(route('admin.students.status.update', $student), ['status' => 'inactive'])
        ->assertRedirect();

    expect($student->fresh()->status)->toBe(StudentStatus::Inactive)
        ->and(Student::withTrashed()->count())->toBe(1)
        ->and(Student::onlyTrashed()->count())->toBe(0);

    $this->actingAs($admin)
        ->patch(route('admin.students.status.update', $student), ['status' => 'active'])
        ->assertRedirect();

    expect($student->fresh()->status)->toBe(StudentStatus::Active)
        ->and(AuditLog::query()->where('subject_type', Student::class)->where('action', 'status_changed')->count())->toBe(2);

    $this->actingAs($admin)
        ->delete('/admin/siswa/'.$student->id)
        ->assertStatus(405);
});

test('foto siswa dikompres ke JPEG paling besar 300 KB dan 600 px di disk privat', function (): void {
    [$unit] = activeScope();

    $this->actingAs(studentStaff())
        ->post(route('admin.students.store'), studentPayload($unit, ['photo' => noisyPhoto()]))
        ->assertSessionHasNoErrors();

    $student = Student::query()->sole();
    $disk = Storage::disk('local');

    expect($student->photo_path)->toStartWith('students/photos/')
        ->and($student->photo_path)->toEndWith('.jpg')
        ->and($disk->exists((string) $student->photo_path))->toBeTrue()
        ->and($disk->size((string) $student->photo_path))->toBeLessThanOrEqual(300 * 1024);

    $size = getimagesizefromstring((string) $disk->get((string) $student->photo_path));

    expect($size)->not->toBeFalse()
        ->and(max($size[0], $size[1]))->toBe(600)
        ->and($size['mime'])->toBe('image/jpeg');
});

test('mengganti foto menghapus berkas lama dan foto dapat dihapus', function (): void {
    [$unit] = activeScope();
    $admin = studentStaff();

    $this->actingAs($admin)
        ->post(route('admin.students.store'), studentPayload($unit, ['photo' => UploadedFile::fake()->image('a.png', 300, 400)]));

    $student = Student::query()->sole();
    $first = (string) $student->photo_path;

    $this->actingAs($admin)
        ->put(route('admin.students.update', $student), studentPayload($unit, [
            'nis' => $student->nis,
            'photo' => UploadedFile::fake()->image('b.webp', 200, 200),
        ]))
        ->assertSessionHasNoErrors();

    $second = (string) $student->fresh()->photo_path;

    expect($second)->not->toBe($first)
        ->and(Storage::disk('local')->exists($first))->toBeFalse()
        ->and(Storage::disk('local')->exists($second))->toBeTrue();

    $this->actingAs($admin)
        ->put(route('admin.students.update', $student), studentPayload($unit, ['nis' => $student->nis, 'remove_photo' => true]))
        ->assertSessionHasNoErrors();

    expect($student->fresh()->photo_path)->toBeNull()
        ->and(Storage::disk('local')->exists($second))->toBeFalse();
});

test('foto siswa menolak SVG, berkas bukan gambar, dan ukuran di atas 2 MB', function (UploadedFile $file): void {
    [$unit] = activeScope();

    $this->actingAs(studentStaff())
        ->post(route('admin.students.store'), studentPayload($unit, ['photo' => $file]))
        ->assertSessionHasErrors('photo');

    expect(Student::query()->count())->toBe(0);
})->with([
    'svg' => fn () => UploadedFile::fake()->createWithContent('foto.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'pdf' => fn () => UploadedFile::fake()->create('foto.pdf', 100, 'application/pdf'),
    'besar' => fn () => UploadedFile::fake()->image('foto.jpg', 600, 600)->size(2049),
]);

test('foto siswa hanya dapat dibuka staf dengan izin lihat siswa', function (): void {
    [$unit] = activeScope();
    $student = Student::factory()->for($unit)->create(['photo_path' => 'students/photos/uji.jpg']);
    Storage::disk('local')->put('students/photos/uji.jpg', 'jpeg-bytes');

    $this->actingAs(studentStaff(RoleEnum::Teacher))
        ->get(route('admin.students.photo', $student))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=3600, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    foreach ([RoleEnum::Guardian, RoleEnum::GateOfficer, RoleEnum::CanteenCashier] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        $this->actingAs($user)->get(route('admin.students.photo', $student))->assertForbidden();
    }
});

test('foto siswa yang belum diunggah menghasilkan 404', function (): void {
    [$unit] = activeScope();
    $student = Student::factory()->for($unit)->create();

    $this->actingAs(studentStaff())
        ->get(route('admin.students.photo', $student))
        ->assertNotFound();
});
