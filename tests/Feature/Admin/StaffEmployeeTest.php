<?php

declare(strict_types=1);

use App\Enums\EmploymentStatus;
use App\Enums\Role as RoleEnum;
use App\Enums\StaffAssignmentType;
use App\Models\StaffProfile;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::Admin->value);
    $this->unit = Unit::factory()->create(['code' => 'MI']);
});

/**
 * @return array<string, mixed>
 */
function employeePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Siti Aminah',
        'username' => 'siti.aminah',
        'phone' => '081311112222',
        'email' => 'siti@contoh.test',
        'roles' => ['guru'],
        'unit_id' => test()->unit->id,
        'title_prefix' => 'Dra.',
        'title_suffix' => 'S.Pd',
        'nik' => '7401011234560001',
        'nuptk' => '1234567890123456',
        'nip' => '19800101',
        'gender' => 'P',
        'birth_place' => 'Kolaka',
        'birth_date' => '1990-05-17',
        'address' => 'Jl. Pemuda 1',
        'assignment_type' => 'guru',
        'employment_status' => 'gty',
        'base_salary' => '1400000',
        'allowance' => '250000',
        'hourly_rate' => '25000',
        'password' => 'sandi-pilihan-admin',
        'photo' => UploadedFile::fake()->image('foto.jpg', 300, 400),
    ], $overrides);
}

test('admin menambah pegawai lengkap dengan kata sandi pilihan dan foto di disk privat', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), employeePayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $user = User::query()->where('username', 'siti.aminah')->sole();
    $profile = StaffProfile::query()->where('user_id', $user->id)->sole();

    expect(Hash::check('sandi-pilihan-admin', $user->password))->toBeTrue()
        ->and($user->hasRole('guru'))->toBeTrue()
        ->and($profile->assignment_type)->toBe(StaffAssignmentType::Teacher)
        ->and($profile->employment_status)->toBe(EmploymentStatus::Gty)
        ->and($profile->base_salary)->toBe(1_400_000)
        ->and($profile->allowance)->toBe(250_000)
        ->and($profile->hourly_rate)->toBe(25_000)
        ->and($profile->birth_date?->toDateString())->toBe('1990-05-17')
        ->and($profile->photo_path)->not->toBeNull();

    Storage::disk('local')->assertExists($profile->photo_path);

    $this->actingAs($this->admin)->get(route('admin.users.photo', $user))->assertOk();

    $teacher = User::factory()->create();
    $teacher->assignRole('guru');
    $this->actingAs($teacher)->get(route('admin.users.photo', $user))->assertForbidden();
});

test('validasi biodata: NIK 16 digit, tanggal lahir lampau, gaji tidak negatif', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), employeePayload([
            'nik' => '123', 'birth_date' => '2999-01-01', 'base_salary' => '-5', 'assignment_type' => 'aneh',
        ]))
        ->assertSessionHasErrors(['nik', 'birth_date', 'base_salary', 'assignment_type']);
});

test('daftar dapat disaring per unit, tipe penugasan, dan dicari dengan NIK', function (): void {
    $this->actingAs($this->admin)->post(route('admin.users.store'), employeePayload());
    $this->actingAs($this->admin)->post(route('admin.users.store'), employeePayload([
        'username' => 'staf.tu', 'name' => 'Staf TU', 'phone' => '081399998888', 'email' => null,
        'nik' => '7401019999990001', 'nuptk' => null, 'assignment_type' => 'staff', 'unit_id' => null, 'photo' => null,
    ]));

    $this->actingAs($this->admin)->get(route('admin.users.index', ['assignment' => 'staff']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.username', 'staf.tu'));

    $this->actingAs($this->admin)->get(route('admin.users.index', ['unit' => $this->unit->id]))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.username', 'siti.aminah')
            ->where('users.data.0.profile.employment_label', 'GTY'));

    $this->actingAs($this->admin)->get(route('admin.users.index', ['search' => '7401019999990001']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.username', 'staf.tu'));
});

test('hapus pegawai satuan dan terpilih: akun sendiri dilewati, data tetap terarsip', function (): void {
    $a = User::factory()->create();
    $a->assignRole('guru');
    $b = User::factory()->create();
    $b->assignRole('bendahara');

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $a))->assertRedirect();
    expect(User::query()->find($a->id))->toBeNull()
        ->and(User::withTrashed()->find($a->id)?->trashed())->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'deleted', 'subject_id' => $a->id]);

    $this->actingAs($this->admin)->post(route('admin.users.bulk-destroy'), ['ids' => [$b->id, $this->admin->id]])->assertRedirect();
    expect(User::query()->find($b->id))->toBeNull()
        ->and(User::query()->find($this->admin->id))->not->toBeNull();

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin))->assertRedirect();
    expect(User::query()->find($this->admin->id))->not->toBeNull();

    $treasurer = User::factory()->create();
    $treasurer->assignRole('bendahara');
    $this->actingAs($treasurer)->post(route('admin.users.bulk-destroy'), ['ids' => [$a->id]])->assertForbidden();
});

test('ubah pegawai tanpa kata sandi baru mempertahankan kata sandi lama', function (): void {
    $this->actingAs($this->admin)->post(route('admin.users.store'), employeePayload());
    $user = User::query()->where('username', 'siti.aminah')->sole();

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $user), [...employeePayload(['password' => null, 'photo' => null, 'base_salary' => '1500000']), '_method' => 'put'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('sandi-pilihan-admin', $user->fresh()->password))->toBeTrue()
        ->and(StaffProfile::query()->where('user_id', $user->id)->sole()->base_salary)->toBe(1_500_000);
});
