<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Exceptions\StaffUserGuardException;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Users\StaffUserService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function staffAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    return $admin;
}

function staffWithRole(RoleEnum $role): User
{
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role->value);

    return $user;
}

function flashedPassword(): string
{
    $flash = session(SessionKey::FLASH_DATA);

    expect($flash)->toBeArray()->toHaveKey('credentials');

    return $flash['credentials']['password'];
}

test('tamu diarahkan ke halaman login saat membuka daftar pengguna staf', function (): void {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('peran selain admin ditolak mengelola pengguna staf', function (RoleEnum $role): void {
    $user = staffWithRole($role);
    $target = staffWithRole(RoleEnum::Teacher);

    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.users.store'), [])->assertForbidden();
    $this->actingAs($user)->put(route('admin.users.update', $target), [])->assertForbidden();
    $this->actingAs($user)->patch(route('admin.users.status.update', $target), ['is_active' => false])->assertForbidden();
    $this->actingAs($user)->post(route('admin.users.password.reset', $target))->assertForbidden();
})->with([
    RoleEnum::Treasurer,
    RoleEnum::Teacher,
    RoleEnum::Counselor,
    RoleEnum::UnitHead,
]);

test('admin melihat daftar staf tanpa akun wali dan tanpa kolom sensitif', function (): void {
    $admin = staffAdmin();
    $teacher = staffWithRole(RoleEnum::Teacher);
    staffWithRole(RoleEnum::Guardian);

    $expectedIds = collect([$admin->id, $teacher->id])->sort()->values()->all();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('users.data', 2)
            ->where('users.data', fn ($rows): bool => collect($rows)->pluck('id')->sort()->values()->all() === $expectedIds)
            ->missing('users.data.0.password')
            ->missing('users.data.0.two_factor_secret')
            ->missing('users.data.0.remember_token')
            ->has('roleOptions', count(RoleEnum::staff())),
        );
});

test('admin dapat mencari staf berdasarkan nama atau username', function (): void {
    $admin = staffAdmin();
    staffWithRole(RoleEnum::Teacher)->update(['name' => 'Guru Fikih', 'username' => 'gurufikih']);
    staffWithRole(RoleEnum::Teacher)->update(['name' => 'Guru Tahfidz', 'username' => 'gurutahfidz']);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['search' => 'fikih']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.username', 'gurufikih'),
        );
});

test('admin menambah staf dengan banyak peran dan menerima kata sandi acak sekali', function (): void {
    $admin = staffAdmin();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Staf Uji Baru',
            'username' => 'stafuji',
            'phone' => '+62 812-3456-7890',
            'email' => null,
            'roles' => [RoleEnum::Teacher->value, RoleEnum::Counselor->value],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user = User::query()->where('username', 'stafuji')->firstOrFail();
    $password = flashedPassword();

    expect($user->is_active)->toBeTrue()
        ->and($user->phone)->toBe('081234567890')
        ->and($user->getRoleNames()->sort()->values()->all())->toBe(['bk', 'guru'])
        ->and(strlen($password))->toBeGreaterThanOrEqual(12)
        ->and(Hash::check($password, $user->password))->toBeTrue();

    $audit = AuditLog::query()->where('action', 'created')->where('subject_id', $user->id)->firstOrFail();

    expect($audit->user_id)->toBe($admin->id)
        ->and($audit->after['roles'])->toBe(['guru', 'bk'])
        ->and(json_encode($audit->after))->not->toContain($password);
});

test('penambahan staf memvalidasi username unik, peran wajib, dan menolak peran wali', function (): void {
    $admin = staffAdmin();
    staffWithRole(RoleEnum::Teacher)->update(['username' => 'sudahada']);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Staf Uji',
            'username' => 'sudahada',
            'roles' => [],
        ])
        ->assertSessionHasErrors(['username', 'roles']);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Staf Uji',
            'username' => 'stafwali',
            'roles' => [RoleEnum::Guardian->value],
        ])
        ->assertSessionHasErrors('roles.0');

    expect(User::query()->where('username', 'stafwali')->exists())->toBeFalse();
});

test('admin mengubah data dan peran staf lalu tercatat di audit', function (): void {
    $admin = staffAdmin();
    $staff = staffWithRole(RoleEnum::Teacher);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $staff), [
            'name' => 'Nama Diperbarui',
            'username' => $staff->username,
            'phone' => '081200000001',
            'email' => 'staf@sekolah.test',
            'roles' => [RoleEnum::Treasurer->value],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $staff->refresh();

    expect($staff->name)->toBe('Nama Diperbarui')
        ->and($staff->getRoleNames()->all())->toBe(['bendahara']);

    $audit = AuditLog::query()->where('action', 'updated')->where('subject_id', $staff->id)->firstOrFail();

    expect($audit->before['roles'])->toBe(['guru'])
        ->and($audit->after['roles'])->toBe(['bendahara'])
        ->and($audit->user_id)->toBe($admin->id);
});

test('admin menonaktifkan dan mengaktifkan kembali staf dengan audit', function (): void {
    $admin = staffAdmin();
    $staff = staffWithRole(RoleEnum::Teacher);

    $this->actingAs($admin)
        ->patch(route('admin.users.status.update', $staff), ['is_active' => false])
        ->assertRedirect();

    expect($staff->refresh()->is_active)->toBeFalse()
        ->and(AuditLog::query()->where('action', 'deactivated')->where('subject_id', $staff->id)->exists())->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.users.status.update', $staff), ['is_active' => true])
        ->assertRedirect();

    expect($staff->refresh()->is_active)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'activated')->where('subject_id', $staff->id)->exists())->toBeTrue();
});

test('admin tidak dapat menonaktifkan akunnya sendiri', function (): void {
    $admin = staffAdmin();
    staffAdmin();

    $this->actingAs($admin)
        ->patch(route('admin.users.status.update', $admin), ['is_active' => false])
        ->assertRedirect();

    expect($admin->refresh()->is_active)->toBeTrue()
        ->and(session(SessionKey::FLASH_DATA)['toast']['type'])->toBe('error');
});

test('admin tidak dapat melepas peran admin dari akunnya sendiri', function (): void {
    $admin = staffAdmin();
    staffAdmin();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'username' => $admin->username,
            'roles' => [RoleEnum::Teacher->value],
        ])
        ->assertRedirect();

    expect($admin->refresh()->hasRole(RoleEnum::Admin->value))->toBeTrue();
});

test('admin aktif terakhir tidak dapat dinonaktifkan atau dilepas perannya', function (): void {
    $lastAdmin = staffAdmin();
    staffAdmin()->update(['is_active' => false]);
    $actor = staffWithRole(RoleEnum::Teacher);
    $service = app(StaffUserService::class);

    expect(fn () => $service->setActive($lastAdmin, false, $actor))
        ->toThrow(StaffUserGuardException::class);

    expect(fn () => $service->update($lastAdmin, [
        'name' => $lastAdmin->name,
        'username' => $lastAdmin->username,
        'phone' => null,
        'email' => null,
        'roles' => [RoleEnum::Teacher->value],
    ], $actor))->toThrow(StaffUserGuardException::class);

    expect($lastAdmin->refresh()->is_active)->toBeTrue()
        ->and($lastAdmin->hasRole(RoleEnum::Admin->value))->toBeTrue();
});

test('admin mereset kata sandi staf dan kata sandi lama tidak berlaku lagi', function (): void {
    $admin = staffAdmin();
    $staff = staffWithRole(RoleEnum::Teacher);
    $oldHash = $staff->password;

    $this->actingAs($admin)
        ->post(route('admin.users.password.reset', $staff))
        ->assertRedirect();

    $password = flashedPassword();
    $staff->refresh();

    expect($staff->password)->not->toBe($oldHash)
        ->and(Hash::check($password, $staff->password))->toBeTrue()
        ->and(Hash::check('password', $staff->password))->toBeFalse();

    $audit = AuditLog::query()->where('action', 'password_reset')->where('subject_id', $staff->id)->firstOrFail();

    expect(json_encode([$audit->before, $audit->after]))->not->toContain($password);
});

test('akun wali tidak dapat dikelola lewat halaman pengguna staf', function (): void {
    $admin = staffAdmin();
    $guardian = staffWithRole(RoleEnum::Guardian);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $guardian), [
            'name' => 'Ubah',
            'username' => $guardian->username,
            'roles' => [RoleEnum::Teacher->value],
        ])
        ->assertNotFound();

    $this->actingAs($admin)
        ->patch(route('admin.users.status.update', $guardian), ['is_active' => false])
        ->assertNotFound();

    $this->actingAs($admin)
        ->post(route('admin.users.password.reset', $guardian))
        ->assertNotFound();
});
