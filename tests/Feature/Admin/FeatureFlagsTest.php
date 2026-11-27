<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role as RoleEnum;
use App\Models\AuditLog;
use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use App\Support\Navigation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function featureAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    return $admin;
}

/**
 * Semua kunci dengan nilai bawaan, ditimpa sebagian.
 *
 * @param  array<string, bool>  $overrides
 * @return array{features: array<string, bool>}
 */
function featurePayload(array $overrides = []): array
{
    $features = [];

    foreach (Feature::cases() as $feature) {
        $features[$feature->value] = $feature->defaultEnabled();
    }

    return ['features' => [...$features, ...$overrides]];
}

/**
 * @return list<string>
 */
function navigationRoutes(User $user): array
{
    $routes = [];

    foreach (Navigation::forUser($user) as $group) {
        foreach ($group['items'] as $item) {
            $routes[] = $item['route'];
        }
    }

    return $routes;
}

test('tanpa baris tersimpan, modul uang dan kelayakan ujian nonaktif sedangkan modul lain aktif', function (): void {
    $flags = app(FeatureFlags::class);

    expect($flags->enabled(Feature::Billing))->toBeFalse()
        ->and($flags->enabled(Feature::Midtrans))->toBeFalse()
        ->and($flags->enabled(Feature::Wallet))->toBeFalse()
        ->and($flags->enabled(Feature::ExamEligibility))->toBeFalse()
        ->and($flags->enabled(Feature::Attendance))->toBeTrue()
        ->and($flags->enabled(Feature::Behavior))->toBeTrue()
        ->and($flags->enabled(Feature::Quran))->toBeTrue()
        ->and($flags->enabled(Feature::Cbt))->toBeTrue();
});

test('tamu diarahkan ke halaman login saat membuka kunci fitur', function (): void {
    $this->get(route('admin.settings.features.edit'))->assertRedirect(route('login'));
    $this->put(route('admin.settings.features.update'), featurePayload())->assertRedirect(route('login'));
});

test('peran selain admin ditolak membuka dan menyimpan kunci fitur', function (RoleEnum $role): void {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)->get(route('admin.settings.features.edit'))->assertForbidden();
    $this->actingAs($user)
        ->put(route('admin.settings.features.update'), featurePayload([Feature::Billing->value => true]))
        ->assertForbidden();

    expect(FeatureFlag::query()->count())->toBe(0);
})->with([
    RoleEnum::Treasurer,
    RoleEnum::Teacher,
    RoleEnum::Counselor,
    RoleEnum::UnitHead,
]);

test('admin melihat semua modul beserta status dan catatan masa paralel', function (): void {
    $this->actingAs(featureAdmin())
        ->get(route('admin.settings.features.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/features')
            ->has('features', count(Feature::cases()))
            ->where('features', fn ($features): bool => collect($features)->contains(
                fn (array $feature): bool => $feature['key'] === Feature::Billing->value
                    && $feature['enabled'] === false
                    && $feature['held_until_cutover'] === true,
            )));
});

test('admin menyalakan dan mematikan modul, perubahan tersimpan dan diaudit', function (): void {
    $admin = featureAdmin();

    $this->actingAs($admin)
        ->put(route('admin.settings.features.update'), featurePayload([
            Feature::Billing->value => true,
            Feature::Behavior->value => false,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $flags = app(FeatureFlags::class);
    expect($flags->enabled(Feature::Billing))->toBeTrue()
        ->and($flags->enabled(Feature::Behavior))->toBeFalse();

    $row = FeatureFlag::query()->where('key', Feature::Billing->value)->sole();
    expect($row->enabled)->toBeTrue()
        ->and($row->updated_by)->toBe($admin->id);

    // Hanya kunci yang berubah yang ditulis dan dicatat.
    expect(FeatureFlag::query()->count())->toBe(2);

    $audit = AuditLog::query()->where('action', 'feature_flags_updated')->sole();
    expect($audit->user_id)->toBe($admin->id)
        ->and($audit->before)->toBe([Feature::Behavior->value => true, Feature::Billing->value => false])
        ->and($audit->after)->toBe([Feature::Behavior->value => false, Feature::Billing->value => true]);
});

test('menyimpan tanpa perubahan tidak menulis audit', function (): void {
    $this->actingAs(featureAdmin())
        ->put(route('admin.settings.features.update'), featurePayload())
        ->assertSessionHasNoErrors();

    expect(AuditLog::query()->where('action', 'feature_flags_updated')->count())->toBe(0)
        ->and(FeatureFlag::query()->count())->toBe(0);
});

test('validasi menolak kunci yang hilang, nilai bukan boolean, dan kunci tidak dikenal', function (): void {
    $payload = featurePayload([Feature::Billing->value => 'ya']);
    unset($payload['features'][Feature::Quran->value]);
    $payload['features']['tidak_dikenal'] = true;

    $this->actingAs(featureAdmin())
        ->put(route('admin.settings.features.update'), $payload)
        ->assertSessionHasErrors([
            'features',
            'features.'.Feature::Billing->value,
            'features.'.Feature::Quran->value,
        ]);

    expect(FeatureFlag::query()->count())->toBe(0);
});

test('rute modul nonaktif menghasilkan 404 walau pengguna punya izin', function (): void {
    $admin = featureAdmin();

    $this->actingAs($admin)->get(route('admin.invoices.index'))->assertNotFound();

    app(FeatureFlags::class)->update([Feature::Behavior->value => false], $admin);

    $this->actingAs($admin)->get(route('admin.behavior.index'))->assertNotFound();
    $this->actingAs($admin)->get(route('admin.quran.index'))->assertOk();
});

test('rute modul yang dinyalakan kembali dapat dibuka', function (): void {
    $admin = featureAdmin();

    app(FeatureFlags::class)->update([Feature::Billing->value => true], $admin);

    $this->actingAs($admin)->get(route('admin.invoices.index'))->assertOk();
});

test('menu dari server tidak memuat modul nonaktif', function (): void {
    $admin = featureAdmin();

    expect(navigationRoutes($admin))
        ->not->toContain('admin.invoices.index')
        ->toContain('admin.attendance.index', 'admin.behavior.index', 'admin.settings.features.edit');

    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
        Feature::Attendance->value => false,
    ], $admin);

    expect(navigationRoutes($admin))
        ->toContain('admin.invoices.index')
        ->not->toContain('admin.attendance.index');
});

test('grup menu tanpa item aktif tidak dikirim', function (): void {
    $this->actingAs(featureAdmin())
        ->get(route('admin.dashboard'))
        ->assertInertia(function (Assert $page): void {
            $keys = array_column($page->toArray()['props']['navigation'], 'key');

            expect($keys)->not->toContain('keuangan');
        });
});

test('status kunci fitur dibaca dari cache dan cache dibuang setelah perubahan', function (): void {
    $flags = app(FeatureFlags::class);
    $flags->enabled(Feature::Billing);

    DB::enableQueryLog();
    $flags->enabled(Feature::Wallet);
    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();

    $flags->update([Feature::Wallet->value => true], featureAdmin());

    expect($flags->enabled(Feature::Wallet))->toBeTrue();
});

test('kunci fitur yang tidak dikenal ditolak oleh layanan', function (): void {
    app(FeatureFlags::class)->update(['tidak_dikenal' => true], featureAdmin());
})->throws(InvalidArgumentException::class);
