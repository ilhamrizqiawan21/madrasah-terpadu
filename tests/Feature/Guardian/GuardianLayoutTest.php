<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\GuardianRelation;
use App\Enums\Role as RoleEnum;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * @param  list<Student>  $students
 */
function makeGuardianUser(array $students = []): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::Guardian->value);

    $guardian = Guardian::query()->create([
        'user_id' => $user->id,
        'name' => $user->name,
        'phone' => '0812'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
        'relation' => GuardianRelation::Father,
    ]);

    foreach ($students as $index => $student) {
        $guardian->students()->attach($student->id, [
            'relation' => GuardianRelation::Father->value,
            'is_primary' => $index === 0,
        ]);
    }

    return $user;
}

test('tamu diarahkan ke login dan peran non-wali ditolak 403 di portal wali', function (): void {
    $this->get(route('guardian.home'))->assertRedirect(route('login'));
    $this->get(route('guardian.history'))->assertRedirect(route('login'));
    $this->get(route('guardian.account'))->assertRedirect(route('login'));

    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);

    $this->actingAs($teacher)->get(route('guardian.home'))->assertForbidden();
    $this->actingAs($teacher)->get(route('guardian.history'))->assertForbidden();
    $this->actingAs($teacher)->get(route('guardian.account'))->assertForbidden();
});

test('wali dengan dua anak dapat berpindah anak aktif dan pilihan diingat di sesi', function (): void {
    $unit = Unit::factory()->create(['code' => 'MI']);
    $anak1 = Student::factory()->for($unit)->create(['name' => 'Anak Pertama']);
    $anak2 = Student::factory()->for($unit)->create(['name' => 'Anak Kedua']);
    $wali = makeGuardianUser([$anak1, $anak2]);

    $this->actingAs($wali)
        ->get(route('guardian.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guardian/home')
            ->has('guardianContext.children', 2)
            ->where('guardianContext.activeChild.id', $anak1->id)
        );

    $this->actingAs($wali)
        ->post(route('guardian.child.update'), ['student_id' => $anak2->id])
        ->assertRedirect();

    $this->actingAs($wali)
        ->get(route('guardian.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('guardianContext.activeChild.id', $anak2->id)
        );
});

test('wali tidak dapat memilih anak milik wali lain (404)', function (): void {
    $unit = Unit::factory()->create(['code' => 'MI']);
    $anakSendiri = Student::factory()->for($unit)->create(['name' => 'Anak Sendiri']);
    $anakOrangLain = Student::factory()->for($unit)->create(['name' => 'Anak Orang Lain']);
    $wali = makeGuardianUser([$anakSendiri]);

    $this->actingAs($wali)
        ->post(route('guardian.child.update'), ['student_id' => $anakOrangLain->id])
        ->assertNotFound();
});

test('wali tanpa anak tetap bisa membuka beranda dan menerima daftar anak kosong', function (): void {
    $wali = makeGuardianUser([]);

    $this->actingAs($wali)
        ->get(route('guardian.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guardian/home')
            ->where('guardianContext.children', [])
            ->where('guardianContext.activeChild', null)
        );
});

test('halaman riwayat, akun, dan kunci fitur tagihan wali bekerja sesuai aturan', function (): void {
    $unit = Unit::factory()->create(['code' => 'MI']);
    $anak = Student::factory()->for($unit)->create();
    $wali = makeGuardianUser([$anak]);

    $this->actingAs($wali)->get(route('guardian.history'))->assertOk();
    $this->actingAs($wali)->get(route('guardian.account'))->assertOk();
    $this->actingAs($wali)->get(route('guardian.billing'))->assertNotFound();

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([Feature::Billing->value => true], $admin);

    $this->actingAs($wali)->get(route('guardian.billing'))->assertOk();
});
