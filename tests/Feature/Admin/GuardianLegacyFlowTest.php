<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::Admin->value);
    $this->unit = Unit::factory()->create(['code' => 'MI']);
    $this->student = Student::factory()->create(['unit_id' => $this->unit->id]);
});

test('admin menetapkan username, kata sandi, email, dan No. KK wali', function (): void {
    $this->actingAs($this->admin)->post(route('admin.guardians.store'), [
        'name' => 'Ibu Fatimah', 'phone' => '081355556666', 'relation' => 'ibu',
        'family_card_number' => '7401010101010009', 'email' => 'fatimah@contoh.test',
        'username' => 'ibu.fatimah', 'password' => 'sandi-wali-123',
        'children' => [['student_id' => $this->student->id, 'is_primary' => true]],
    ])->assertSessionHasNoErrors();

    $guardian = Guardian::query()->with('user')->sole();
    expect($guardian->family_card_number)->toBe('7401010101010009')
        ->and($guardian->user->username)->toBe('ibu.fatimah')
        ->and($guardian->user->email)->toBe('fatimah@contoh.test')
        ->and(Hash::check('sandi-wali-123', $guardian->user->password))->toBeTrue();

    $this->actingAs($this->admin)->get(route('admin.guardians.index', ['unit_id' => $this->unit->id, 'search' => '7401010101010009']))
        ->assertInertia(fn (Assert $page) => $page->has('guardians.data', 1)
            ->where('guardians.data.0.family_card_number', '7401010101010009'));
});

test('tanpa username dan kata sandi, akun wali tetap memakai nomor HP dan sandi acak', function (): void {
    $this->actingAs($this->admin)->post(route('admin.guardians.store'), [
        'name' => 'Bapak Umar', 'phone' => '081377778888', 'relation' => 'ayah',
        'children' => [['student_id' => $this->student->id, 'is_primary' => true]],
    ])->assertSessionHasNoErrors();

    expect(Guardian::query()->with('user')->sole()->user->username)->toBe('081377778888');
});

test('ubah wali tanpa kata sandi baru mempertahankan kata sandi lama dan menolak No. KK salah', function (): void {
    $this->actingAs($this->admin)->post(route('admin.guardians.store'), [
        'name' => 'Ibu Aminah', 'phone' => '081399990000', 'relation' => 'ibu', 'password' => 'sandi-lama-123',
        'children' => [['student_id' => $this->student->id, 'is_primary' => true]],
    ]);
    $guardian = Guardian::query()->with('user')->sole();

    $payload = [
        'name' => 'Ibu Aminah', 'phone' => '081399990000', 'relation' => 'ibu',
        'children' => [['student_id' => $this->student->id, 'is_primary' => true]],
    ];

    $this->actingAs($this->admin)->put(route('admin.guardians.update', $guardian), $payload)->assertSessionHasNoErrors();
    expect(Hash::check('sandi-lama-123', $guardian->user->fresh()->password))->toBeTrue();

    $this->actingAs($this->admin)->put(route('admin.guardians.update', $guardian), [...$payload, 'family_card_number' => '12'])
        ->assertSessionHasErrors('family_card_number');
});
