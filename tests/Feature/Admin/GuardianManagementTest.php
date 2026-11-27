<?php

declare(strict_types=1);

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
use App\Services\Guardians\GuardianService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

function guardianAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    return $admin;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function guardianPayload(array $children, array $overrides = []): array
{
    return [
        'name' => 'Wali Uji 001',
        'phone' => '+62 800-0000-0001',
        'relation' => GuardianRelation::Father->value,
        'address' => 'Jl. Uji No. 1',
        'children' => $children,
        ...$overrides,
    ];
}

/**
 * @return array{student_id: int, is_primary: bool}
 */
function child(Student $student, bool $primary = true): array
{
    return ['student_id' => $student->id, 'is_primary' => $primary];
}

/**
 * Wali dengan akun login seperti buatan GuardianService.
 */
function linkedGuardian(Student $student, bool $primary = true, string $phone = '080000000099'): Guardian
{
    $user = User::factory()->create(['phone' => $phone, 'username' => $phone]);

    return Guardian::factory()->for($user)->withGuardianAccount()->linkedTo($student, $primary)->create();
}

function primaryGuardianIds(Student $student): array
{
    return GuardianStudent::query()
        ->where('student_id', $student->id)
        ->where('is_primary', true)
        ->pluck('guardian_id')
        ->all();
}

test('tamu diarahkan ke halaman login', function (): void {
    $student = Student::factory()->create(['photo_path' => 'students/photos/x.jpg']);
    $guardian = linkedGuardian($student);

    $this->get(route('admin.guardians.index'))->assertRedirect(route('login'));
    $this->get(route('admin.guardians.students.search', ['q' => 'Uji']))->assertRedirect(route('login'));
    $this->post(route('admin.guardians.store'), guardianPayload([child($student)]))->assertRedirect(route('login'));
    $this->post(route('admin.guardians.password.reset', $guardian))->assertRedirect(route('login'));
    $this->get(route('guardian.students.photo', $student))->assertRedirect(route('login'));
});

test('peran selain admin ditolak mengelola wali', function (RoleEnum $role): void {
    $student = Student::factory()->create();
    $guardian = linkedGuardian($student);
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role->value);
    $password = $guardian->user->password;

    $this->actingAs($user)->get(route('admin.guardians.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.guardians.students.search', ['q' => 'Siswa']))->assertForbidden();
    $this->actingAs($user)->post(route('admin.guardians.store'), guardianPayload([child($student)]))->assertForbidden();
    $this->actingAs($user)->put(route('admin.guardians.update', $guardian), guardianPayload([child($student)], ['phone' => '080000000099']))->assertForbidden();
    $this->actingAs($user)->patch(route('admin.guardians.status.update', $guardian), ['is_active' => false])->assertForbidden();
    $this->actingAs($user)->post(route('admin.guardians.password.reset', $guardian))->assertForbidden();

    expect(Guardian::query()->count())->toBe(1)
        ->and($guardian->user->fresh()->is_active)->toBeTrue()
        ->and($guardian->user->fresh()->password)->toBe($password);
})->with([
    RoleEnum::Treasurer,
    RoleEnum::Teacher,
    RoleEnum::Counselor,
]);

test('admin melihat daftar wali beranak di unit topbar beserta anak dan rombelnya', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI']);
    $ra = Unit::factory()->create(['code' => 'RA']);
    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($mi)->for($year)->create(['name' => '1-A']);
    $miStudent = Student::factory()->for($mi)->create(['name' => 'Siswa Uji 001']);
    Enrollment::factory()->for($miStudent)->for($classroom)->create();
    $raStudent = Student::factory()->for($ra)->create(['name' => 'Siswa Uji 002']);
    $guardian = linkedGuardian($miStudent, phone: '080000000001');
    $guardian->update(['name' => 'Wali Uji 001']);
    linkedGuardian($raStudent, phone: '080000000002');

    $this->actingAs(guardianAdmin())
        ->get(route('admin.guardians.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/guardians/index')
            ->where('filters.unit_id', $mi->id)
            ->has('guardians.data', 1)
            ->where('guardians.data.0.name', 'Wali Uji 001')
            ->where('guardians.data.0.username', '080000000001')
            ->where('guardians.data.0.relation.label', 'Ayah')
            ->where('guardians.data.0.is_active', true)
            ->where('guardians.data.0.children.0.name', 'Siswa Uji 001')
            ->where('guardians.data.0.children.0.classroom', '1-A')
            ->where('guardians.data.0.children.0.is_primary', true)
            ->missing('guardians.data.0.password'));
});

test('daftar wali dapat dicari dengan nama anak atau nomor HP berformat apa pun dan disaring status akun', function (): void {
    $unit = Unit::factory()->create(['code' => 'MI']);
    $first = Student::factory()->for($unit)->create(['name' => 'Siswa Uji 001', 'nis' => '9001']);
    $second = Student::factory()->for($unit)->create(['name' => 'Siswa Uji 002', 'nis' => '9002']);
    linkedGuardian($first, phone: '080000000001');
    $inactive = linkedGuardian($second, phone: '080000000002');
    $inactive->user->forceFill(['is_active' => false])->save();
    $admin = guardianAdmin();

    $this->actingAs($admin)->get(route('admin.guardians.index', ['search' => '9001']))
        ->assertInertia(fn (Assert $page) => $page->has('guardians.data', 1)->where('guardians.data.0.phone', '080000000001'));

    $this->actingAs($admin)->get(route('admin.guardians.index', ['search' => '+62 800-0000-0001']))
        ->assertInertia(fn (Assert $page) => $page->has('guardians.data', 1));

    $this->actingAs($admin)->get(route('admin.guardians.index', ['status' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page->has('guardians.data', 1)->where('guardians.data.0.phone', '080000000002'));

    $this->actingAs($admin)->get(route('admin.guardians.index', ['status' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->has('guardians.data', 2));
});

test('pencarian siswa di dialog wali mengembalikan siswa aktif semua unit beserta rombel', function (): void {
    [$mi, $ra] = [Unit::factory()->create(['code' => 'MI']), Unit::factory()->create(['code' => 'RA'])];
    $year = AcademicYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($mi)->for($year)->create(['name' => '2-B']);
    $student = Student::factory()->for($mi)->create(['name' => 'Siswa Uji 001', 'nis' => '7001']);
    Enrollment::factory()->for($student)->for($classroom)->create();
    Student::factory()->for($ra)->create(['name' => 'Siswa Uji 002']);
    Student::factory()->for($mi)->create(['name' => 'Siswa Uji 003', 'status' => 'inactive']);

    $this->actingAs(guardianAdmin())
        ->getJson(route('admin.guardians.students.search', ['q' => 'Siswa Uji']))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Siswa Uji 001')
        ->assertJsonPath('data.0.unit', 'MI')
        ->assertJsonPath('data.0.classroom', '2-B')
        ->assertJsonPath('data.1.unit', 'RA');

    $this->actingAs(guardianAdmin())
        ->getJson(route('admin.guardians.students.search', ['q' => '7001']))
        ->assertJsonCount(1, 'data');
});

test('penambahan wali memvalidasi nama, nomor HP, hubungan, dan anak', function (array $overrides, string $field): void {
    $student = Student::factory()->create();

    $this->actingAs(guardianAdmin())
        ->post(route('admin.guardians.store'), guardianPayload([child($student)], $overrides))
        ->assertSessionHasErrors($field);

    expect(Guardian::query()->count())->toBe(0)
        ->and(User::query()->role(RoleEnum::Guardian->value)->count())->toBe(0);
})->with([
    'nama kosong' => [['name' => ''], 'name'],
    'nomor bukan HP' => [['phone' => '12345'], 'phone'],
    'nomor kosong' => [['phone' => ''], 'phone'],
    'hubungan asing' => [['relation' => 'paman'], 'relation'],
    'tanpa anak' => [['children' => []], 'children'],
    'siswa tidak ada' => [['children' => [['student_id' => 999999, 'is_primary' => true]]], 'children.0.student_id'],
]);

test('anak yang sama tidak dapat dipilih dua kali', function (): void {
    $student = Student::factory()->create();

    $this->actingAs(guardianAdmin())
        ->post(route('admin.guardians.store'), guardianPayload([child($student), child($student, false)]))
        ->assertSessionHasErrors('children.1.student_id');
});

test('admin menambah wali: akun ber-username nomor HP ternormalisasi, peran wali, anak terhubung, dan audit', function (): void {
    $first = Student::factory()->create(['name' => 'Siswa Uji 001']);
    $second = Student::factory()->create(['name' => 'Siswa Uji 002']);
    $admin = guardianAdmin();

    $this->actingAs($admin)
        ->post(route('admin.guardians.store'), guardianPayload([child($first), child($second)]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $guardian = Guardian::query()->with('user', 'students')->firstOrFail();
    $flash = session(SessionKey::FLASH_DATA);

    expect($guardian->name)->toBe('Wali Uji 001')
        ->and($guardian->phone)->toBe('080000000001')
        ->and($guardian->relation)->toBe(GuardianRelation::Father)
        ->and($guardian->user->username)->toBe('080000000001')
        ->and($guardian->user->phone)->toBe('080000000001')
        ->and($guardian->user->is_active)->toBeTrue()
        ->and($guardian->user->getRoleNames()->all())->toBe([RoleEnum::Guardian->value])
        ->and($guardian->students->pluck('id')->sort()->values()->all())->toBe([$first->id, $second->id])
        ->and($guardian->students->every(fn (Student $student): bool => $student->link->is_primary))->toBeTrue()
        ->and($guardian->students->every(fn (Student $student): bool => $student->link->relation === GuardianRelation::Father))->toBeTrue()
        ->and($flash['credentials']['username'])->toBe('080000000001')
        ->and($flash['credentials']['children'])->toHaveCount(2);

    $audit = AuditLog::query()->where('action', 'created')->where('subject_type', Guardian::class)->firstOrFail();

    expect($audit->user_id)->toBe($admin->id)
        ->and($audit->subject_id)->toBe($guardian->id)
        ->and($audit->after['children'])->toHaveCount(2)
        ->and($audit->after)->not->toHaveKey('address');
});

test('kata sandi awal wali di-hash, ditampilkan sekali, dan tidak tersimpan polos di basis data, audit, maupun log', function (): void {
    $student = Student::factory()->create();
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.json_encode($event->context);
    });

    $admin = guardianAdmin();

    $this->actingAs($admin)
        ->post(route('admin.guardians.store'), guardianPayload([child($student)]))
        ->assertSessionHasNoErrors();

    $password = session(SessionKey::FLASH_DATA)['credentials']['password'];
    $user = User::query()->where('username', '080000000001')->firstOrFail();

    expect(strlen($password))->toBeGreaterThanOrEqual(12)
        ->and($user->password)->not->toBe($password)
        ->and(Hash::check($password, $user->password))->toBeTrue();

    foreach (['users', 'guardians', 'guardian_student', 'audit_logs'] as $table) {
        foreach (DB::table($table)->get() as $row) {
            expect(json_encode($row))->not->toContain($password);
        }
    }

    expect(implode("\n", $logged))->not->toContain($password);

    // Tampil sekali: kunjungan berikutnya tidak membawa kata sandi lagi.
    $this->actingAs($admin)
        ->get(route('admin.guardians.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('flash.credentials'));

    expect(json_encode(session()->all()))->not->toContain($password);
});

test('nomor HP wali yang sudah terdaftar menautkan anak baru ke wali itu tanpa akun ganda', function (): void {
    $first = Student::factory()->create();
    $second = Student::factory()->create();
    $guardian = linkedGuardian($first, phone: '080000000001');
    $password = $guardian->user->password;

    $this->actingAs(guardianAdmin())
        ->post(route('admin.guardians.store'), guardianPayload([child($second)], ['phone' => '0800 0000 0001', 'name' => 'Nama Lain']))
        ->assertSessionHasNoErrors();

    expect(Guardian::query()->count())->toBe(1)
        ->and(User::query()->where('phone', '080000000001')->count())->toBe(1)
        ->and($guardian->students()->pluck('students.id')->sort()->values()->all())->toBe([$first->id, $second->id])
        ->and($guardian->fresh()->name)->not->toBe('Nama Lain')
        ->and($guardian->user->fresh()->password)->toBe($password)
        ->and(session(SessionKey::FLASH_DATA))->not->toHaveKey('credentials')
        ->and(AuditLog::query()->where('action', 'children_linked')->where('subject_id', $guardian->id)->exists())->toBeTrue();
});

test('nomor HP milik akun staf memberi akun itu peran wali tanpa mengganti kata sandinya', function (): void {
    $student = Student::factory()->create();
    $teacher = User::factory()->create(['phone' => '080000000001', 'username' => 'guru.uji']);
    $teacher->assignRole(RoleEnum::Teacher->value);
    $password = $teacher->password;

    $this->actingAs(guardianAdmin())
        ->post(route('admin.guardians.store'), guardianPayload([child($student)]))
        ->assertSessionHasNoErrors();

    $teacher->refresh();

    expect(User::query()->count())->toBe(2)
        ->and($teacher->guardian)->not->toBeNull()
        ->and($teacher->username)->toBe('guru.uji')
        ->and($teacher->password)->toBe($password)
        ->and($teacher->getRoleNames()->sort()->values()->all())->toBe(['guru', 'wali'])
        ->and(session(SessionKey::FLASH_DATA))->not->toHaveKey('credentials');
});

test('akun staf yang juga wali tidak dapat direset atau dinonaktifkan dari menu wali', function (): void {
    $student = Student::factory()->create();
    $guardian = linkedGuardian($student);
    $guardian->user->assignRole(RoleEnum::Teacher->value);
    $password = $guardian->user->password;
    $admin = guardianAdmin();

    $this->actingAs($admin)->post(route('admin.guardians.password.reset', $guardian))->assertRedirect();
    $this->actingAs($admin)->patch(route('admin.guardians.status.update', $guardian), ['is_active' => false])->assertRedirect();

    expect($guardian->user->fresh()->password)->toBe($password)
        ->and($guardian->user->fresh()->is_active)->toBeTrue()
        ->and(session(SessionKey::FLASH_DATA)['toast']['type'])->toBe('error');
});

test('nomor HP yang dipakai akun lain ditolak saat mengubah wali', function (): void {
    $student = Student::factory()->create();
    $guardian = linkedGuardian($student, phone: '080000000001');
    User::factory()->create(['phone' => '080000000002']);

    $this->actingAs(guardianAdmin())
        ->put(route('admin.guardians.update', $guardian), guardianPayload([child($student)], ['phone' => '0800-0000-0002']))
        ->assertSessionHasErrors('phone');

    expect($guardian->fresh()->phone)->toBe('080000000001');
});

test('mengubah wali memperbarui akun login, mengganti daftar anak, dan mencatat audit', function (): void {
    $first = Student::factory()->create();
    $second = Student::factory()->create();
    $guardian = linkedGuardian($first, phone: '080000000001');

    $this->actingAs(guardianAdmin())
        ->put(route('admin.guardians.update', $guardian), guardianPayload([child($second)], [
            'name' => 'Wali Uji 009',
            'phone' => '+62 800 0000 0009',
            'relation' => GuardianRelation::Mother->value,
        ]))
        ->assertSessionHasNoErrors();

    $guardian->refresh();

    expect($guardian->name)->toBe('Wali Uji 009')
        ->and($guardian->phone)->toBe('080000000009')
        ->and($guardian->user->username)->toBe('080000000009')
        ->and($guardian->user->phone)->toBe('080000000009')
        ->and($guardian->user->name)->toBe('Wali Uji 009')
        ->and($guardian->students()->pluck('students.id')->all())->toBe([$second->id])
        ->and(GuardianStudent::query()->where('student_id', $second->id)->value('relation'))->toBe(GuardianRelation::Mother);

    $audit = AuditLog::query()->where('action', 'updated')->where('subject_id', $guardian->id)->firstOrFail();

    expect($audit->before['phone'])->toBe('080000000001')
        ->and($audit->after['phone'])->toBe('080000000009')
        ->and($audit->after['children'])->toBe([['student_id' => $second->id, 'is_primary' => true]]);
});

test('setiap siswa tepat punya satu wali utama', function (): void {
    $student = Student::factory()->create();
    $father = linkedGuardian($student, phone: '080000000001');
    $admin = guardianAdmin();

    // Wali kedua tanpa tanda utama: wali utama lama tetap.
    $this->actingAs($admin)
        ->post(route('admin.guardians.store'), guardianPayload([child($student, false)], ['phone' => '080000000002', 'relation' => 'ibu']))
        ->assertSessionHasNoErrors();
    $mother = Guardian::query()->where('phone', '080000000002')->firstOrFail();

    expect(primaryGuardianIds($student))->toBe([$father->id]);

    // Menandai ibu sebagai utama mencabut tanda utama ayah.
    $this->actingAs($admin)
        ->put(route('admin.guardians.update', $mother), guardianPayload([child($student, true)], ['phone' => '080000000002', 'relation' => 'ibu']))
        ->assertSessionHasNoErrors();

    expect(primaryGuardianIds($student))->toBe([$mother->id]);

    // Mencabut tanda utama ibu menyerahkannya ke wali lain, bukan membiarkan siswa tanpa wali utama.
    $this->actingAs($admin)
        ->put(route('admin.guardians.update', $mother), guardianPayload([child($student, false)], ['phone' => '080000000002', 'relation' => 'ibu']))
        ->assertSessionHasNoErrors();

    expect(primaryGuardianIds($student))->toBe([$father->id]);

    // Melepas anak dari wali utama mengangkat wali yang tersisa.
    $other = Student::factory()->create();
    $this->actingAs($admin)
        ->put(route('admin.guardians.update', $father), guardianPayload([child($other)], ['phone' => '080000000001']))
        ->assertSessionHasNoErrors();

    expect(primaryGuardianIds($student))->toBe([$mother->id])
        ->and(primaryGuardianIds($other))->toBe([$father->id]);
});

test('wali utama yang diminta tetap tunggal walau layanan dipanggil langsung', function (): void {
    $student = Student::factory()->create();
    $first = linkedGuardian($student, phone: '080000000001');
    $second = linkedGuardian($student, primary: true, phone: '080000000002');

    // Data rusak (dua utama) diperbaiki saat hubungan siswa itu disentuh lagi.
    app(GuardianService::class)->update($second, [
        'name' => $second->name,
        'phone' => $second->phone,
        'relation' => GuardianRelation::Mother,
        'address' => null,
        'children' => [['student_id' => $student->id, 'is_primary' => true]],
    ], guardianAdmin());

    expect(primaryGuardianIds($student))->toBe([$second->id])
        ->and($first->students()->first()?->link->is_primary)->toBeFalse();
});

test('admin menonaktifkan akun wali alih-alih menghapus dan dapat mengaktifkannya kembali', function (): void {
    $student = Student::factory()->create();
    $guardian = linkedGuardian($student);
    $admin = guardianAdmin();

    $this->actingAs($admin)
        ->patch(route('admin.guardians.status.update', $guardian), ['is_active' => false])
        ->assertRedirect();

    expect($guardian->user->fresh()->is_active)->toBeFalse()
        ->and(Guardian::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'deactivated')->where('subject_id', $guardian->id)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.guardians.status.update', $guardian), ['is_active' => true])
        ->assertRedirect();

    expect($guardian->user->fresh()->is_active)->toBeTrue();
    expect(fn () => route('admin.guardians.destroy', $guardian))->toThrow(RouteNotFoundException::class);
});

test('reset kata sandi wali memberi kata sandi baru sekali tampil, merotasi remember token, tanpa kata sandi di audit', function (): void {
    $student = Student::factory()->create();
    $guardian = linkedGuardian($student);
    $token = $guardian->user->remember_token;

    $this->actingAs(guardianAdmin())
        ->post(route('admin.guardians.password.reset', $guardian))
        ->assertRedirect();

    $password = session(SessionKey::FLASH_DATA)['credentials']['password'];
    $user = $guardian->user->fresh();

    expect(Hash::check($password, $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe($token);

    $audit = AuditLog::query()->where('action', 'password_reset')->where('subject_id', $guardian->id)->firstOrFail();

    expect(json_encode($audit->toArray()))->not->toContain($password);
});

test('wali hanya melihat foto anak yang terhubung; anak lain 404', function (): void {
    $student = Student::factory()->create(['photo_path' => 'students/photos/anak.jpg']);
    $other = Student::factory()->create(['photo_path' => 'students/photos/lain.jpg']);
    Storage::disk('local')->put('students/photos/anak.jpg', 'jpeg-bytes');
    Storage::disk('local')->put('students/photos/lain.jpg', 'jpeg-bytes');
    $guardian = linkedGuardian($student, phone: '080000000001');
    $otherGuardian = linkedGuardian($other, phone: '080000000002');

    $this->actingAs($guardian->user)->get(route('guardian.students.photo', $student))->assertOk();
    $this->actingAs($guardian->user)->get(route('guardian.students.photo', $other))->assertNotFound();
    $this->actingAs($otherGuardian->user)->get(route('guardian.students.photo', $student))->assertNotFound();

    // Rute staf tetap hanya untuk staf berizin.
    $this->actingAs($guardian->user)->get(route('admin.students.photo', $student))->assertForbidden();

    expect($guardian->user->can('view', $student))->toBeTrue()
        ->and($guardian->user->can('view', $other))->toBeFalse();
});

test('relasi wali hanya memuat anak yang terhubung', function (): void {
    $student = Student::factory()->create();
    Student::factory()->count(2)->create();
    $guardian = linkedGuardian($student);

    expect($guardian->user->guardian?->students->pluck('id')->all())->toBe([$student->id]);
});

test('daftar siswa menampilkan wali utama tanpa N+1', function (): void {
    $unit = Unit::factory()->create(['code' => 'MI']);
    $students = Student::factory()->for($unit)->count(3)->sequence(
        ['name' => 'Siswa Uji 001'],
        ['name' => 'Siswa Uji 002'],
        ['name' => 'Siswa Uji 003'],
    )->create();
    linkedGuardian($students[0], phone: '080000000001')->update(['name' => 'Wali Uji 001']);
    linkedGuardian($students[0], primary: false, phone: '080000000002');
    linkedGuardian($students[1], phone: '080000000003');

    $this->actingAs(guardianAdmin())
        ->get(route('admin.students.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('students.data.0.primary_guardian', ['name' => 'Wali Uji 001', 'phone' => '080000000001'])
            ->whereNot('students.data.1.primary_guardian', null)
            ->where('students.data.2.primary_guardian', null));
});

test('login NIS menolak wali utama yang akunnya nonaktif', function (): void {
    $student = Student::factory()->create(['nis' => '20269999']);
    $guardian = linkedGuardian($student);
    $guardian->user->forceFill(['is_active' => false])->save();

    $this->post(route('login.store'), ['email' => '20269999', 'password' => 'password']);

    $this->assertGuest();
});
