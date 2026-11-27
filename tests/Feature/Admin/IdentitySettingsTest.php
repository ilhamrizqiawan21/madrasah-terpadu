<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Enums\SettingKey;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

function identityAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    return $admin;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function identityPayload(array $overrides = []): array
{
    return [
        'app_name' => 'Madrasah Terpadu',
        'foundation_name' => 'Yayasan Amal Jaya Berkah',
        'address' => 'Jl. Pendidikan No. 1, Kolaka',
        'phone' => '081234567890',
        'email' => 'tu@sekolah.test',
        ...$overrides,
    ];
}

test('tamu diarahkan ke halaman login saat membuka pengaturan identitas', function (): void {
    $this->get(route('admin.settings.identity.edit'))->assertRedirect(route('login'));
});

test('peran selain admin ditolak membuka dan menyimpan pengaturan identitas', function (RoleEnum $role): void {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)->get(route('admin.settings.identity.edit'))->assertForbidden();
    $this->actingAs($user)->put(route('admin.settings.identity.update'), identityPayload())->assertForbidden();

    expect(Setting::query()->count())->toBe(0);
})->with([
    RoleEnum::Treasurer,
    RoleEnum::Teacher,
    RoleEnum::Counselor,
    RoleEnum::UnitHead,
]);

test('peran selain admin ditolak membuka dan menyimpan pengaturan tampilan', function (RoleEnum $role): void {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role->value);

    $this->actingAs($user)->get(route('admin.settings.appearance.edit'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.settings.appearance.update'), [
        'logo' => UploadedFile::fake()->image('logo.png'),
    ])->assertForbidden();

    expect(Setting::query()->count())->toBe(0);
})->with([
    RoleEnum::Treasurer,
    RoleEnum::Teacher,
]);

test('admin membuka halaman tampilan berisi URL logo dan favicon saat ini', function (): void {
    $admin = identityAdmin();
    $this->actingAs($admin)->post(route('admin.settings.appearance.update'), [
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.settings.appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/appearance')
            ->where('appearance.logo_url', fn (string $url): bool => str_starts_with($url, '/branding/logo?v='))
            ->where('appearance.favicon_url', null));
});

test('menyimpan identitas tidak menyentuh logo yang sudah ada', function (): void {
    $admin = identityAdmin();
    $this->actingAs($admin)->post(route('admin.settings.appearance.update'), [
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);
    $logoPath = app(SettingsRepository::class)->identity()['logo_path'];

    $this->actingAs($admin)->put(route('admin.settings.identity.update'), identityPayload());

    expect(app(SettingsRepository::class)->identity()['logo_path'])->toBe($logoPath);
    Storage::disk('local')->assertExists($logoPath);
});

test('tanpa pengaturan tersimpan, identitas memakai nilai bawaan dari konfigurasi', function (): void {
    $identity = app(SettingsRepository::class)->identity();

    expect($identity['app_name'])->toBe(config('app.name'))
        ->and($identity['foundation_name'])->toBe(config('sekolah.foundation_name'))
        ->and($identity['address'])->toBeNull()
        ->and($identity['logo_path'])->toBeNull();
});

test('admin membuka halaman identitas dengan nilai tersimpan', function (): void {
    app(SettingsRepository::class)->setMany(
        [SettingKey::FoundationName->value => 'Yayasan Uji'],
        identityAdmin(),
    );

    $this->actingAs(identityAdmin())
        ->get(route('admin.settings.identity.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/identity')
            ->where('identity.foundation_name', 'Yayasan Uji'));
});

test('admin menyimpan identitas, perubahan diaudit, dan nomor HP dinormalisasi', function (): void {
    $admin = identityAdmin();

    $this->actingAs($admin)
        ->put(route('admin.settings.identity.update'), identityPayload(['phone' => '+62 812-3456-7890']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $identity = app(SettingsRepository::class)->identity();

    expect($identity['foundation_name'])->toBe('Yayasan Amal Jaya Berkah')
        ->and($identity['address'])->toBe('Jl. Pendidikan No. 1, Kolaka')
        ->and($identity['phone'])->toBe('081234567890');

    $setting = Setting::query()->where('key', SettingKey::Address->value)->firstOrFail();
    expect($setting->updated_by)->toBe($admin->id);

    $audit = AuditLog::query()->where('action', 'settings_updated')->sole();
    expect($audit->user_id)->toBe($admin->id)
        ->and($audit->after)->toHaveKey(SettingKey::Address->value)
        ->and($audit->before[SettingKey::Address->value])->toBeNull();
});

test('validasi menolak nama kosong dan email tidak valid', function (): void {
    $this->actingAs(identityAdmin())
        ->put(route('admin.settings.identity.update'), identityPayload([
            'app_name' => '',
            'foundation_name' => '',
            'email' => 'bukan-email',
        ]))
        ->assertSessionHasErrors(['app_name', 'foundation_name', 'email']);

    expect(Setting::query()->count())->toBe(0);
});

test('pembacaan berikutnya diambil dari cache tanpa query ke database', function (): void {
    $repository = app(SettingsRepository::class);
    $repository->setMany([SettingKey::AppName->value => 'Aplikasi Uji'], identityAdmin());

    $repository->identity();

    DB::enableQueryLog();
    $identity = $repository->identity();

    expect($identity['app_name'])->toBe('Aplikasi Uji')
        ->and(DB::getQueryLog())->toBe([]);
});

test('menyimpan pengaturan membuang cache sehingga nilai baru langsung terbaca', function (): void {
    $repository = app(SettingsRepository::class);
    $admin = identityAdmin();

    $repository->setMany([SettingKey::AppName->value => 'Nama Lama'], $admin);
    expect($repository->identity()['app_name'])->toBe('Nama Lama');

    $repository->setMany([SettingKey::AppName->value => 'Nama Baru'], $admin);
    expect($repository->identity()['app_name'])->toBe('Nama Baru');
});

test('setelah menyimpan, cache langsung berisi nilai baru tanpa query ke database', function (): void {
    $repository = app(SettingsRepository::class);
    $admin = identityAdmin();
    $repository->setMany([SettingKey::AppName->value => 'Nama Lama'], $admin);
    $repository->identity();

    $repository->setMany([SettingKey::AppName->value => 'Nama Baru'], $admin);

    DB::enableQueryLog();
    $identity = $repository->identity();

    expect($identity['app_name'])->toBe('Nama Baru')
        ->and(DB::getQueryLog())->toBe([]);
});

test('setMany mengembalikan nilai lama yang dibaca di bawah kunci baris', function (): void {
    $repository = app(SettingsRepository::class);
    $admin = identityAdmin();
    $repository->setMany([SettingKey::AppName->value => 'Nama Lama'], $admin);

    $before = $repository->setMany([
        SettingKey::AppName->value => 'Nama Baru',
        SettingKey::Phone->value => '081234567890',
    ], $admin);

    expect($before)->toBe([
        SettingKey::AppName->value => 'Nama Lama',
        SettingKey::Phone->value => null,
    ]);
});

test('kunci pengaturan yang tidak dikenal ditolak', function (): void {
    app(SettingsRepository::class)->setMany(['tidak.dikenal' => 'x'], identityAdmin());
})->throws(InvalidArgumentException::class);

test('admin mengunggah logo dan favicon yang disimpan di disk privat lalu disajikan publik', function (): void {
    $this->actingAs(identityAdmin())
        ->post(route('admin.settings.appearance.update'), [
            'logo' => UploadedFile::fake()->image('logo.png', 256, 256),
            'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
        ])
        ->assertSessionHasNoErrors();

    $identity = app(SettingsRepository::class)->identity();

    expect($identity['logo_path'])->toStartWith('branding/')
        ->and($identity['favicon_path'])->toStartWith('branding/');
    Storage::disk('local')->assertExists($identity['logo_path']);

    auth()->logout();

    $this->get(route('branding.show', ['asset' => 'logo']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('branding.show', ['asset' => 'favicon']))->assertOk();
});

test('logo baru menggantikan berkas lama dan logo dapat dihapus', function (): void {
    $admin = identityAdmin();

    $this->actingAs($admin)->post(route('admin.settings.appearance.update'), [
        'logo' => UploadedFile::fake()->image('lama.png'),
    ]);
    $oldPath = app(SettingsRepository::class)->identity()['logo_path'];

    $this->actingAs($admin)->post(route('admin.settings.appearance.update'), [
        'logo' => UploadedFile::fake()->image('baru.png'),
    ]);
    $newPath = app(SettingsRepository::class)->identity()['logo_path'];

    expect($newPath)->not->toBe($oldPath);
    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($newPath);

    $this->actingAs($admin)->post(route('admin.settings.appearance.update'), [
        'remove_logo' => true,
    ]);

    expect(app(SettingsRepository::class)->identity()['logo_path'])->toBeNull();
    Storage::disk('local')->assertMissing($newPath);
    $this->get(route('branding.show', ['asset' => 'logo']))->assertNotFound();
});

test('logo lama yang dihapus mengikuti nilai di database, bukan nilai cache yang basi', function (): void {
    $admin = identityAdmin();
    $this->actingAs($admin)->post(route('admin.settings.appearance.update'), [
        'logo' => UploadedFile::fake()->image('pertama.png'),
    ]);
    $firstPath = app(SettingsRepository::class)->identity()['logo_path'];

    // Unggahan lain sudah tersimpan di database, tetapi cache proses ini masih menyimpan jalur pertama.
    $concurrentPath = 'branding/serentak.png';
    Storage::disk('local')->put($concurrentPath, 'png');
    Setting::query()->where('key', SettingKey::LogoPath->value)->firstOrFail()
        ->forceFill(['value' => $concurrentPath])->save();

    $this->actingAs($admin)->post(route('admin.settings.appearance.update'), [
        'logo' => UploadedFile::fake()->image('ketiga.png'),
    ])->assertSessionHasNoErrors();

    $finalPath = app(SettingsRepository::class)->identity()['logo_path'];

    expect($finalPath)->not->toBe($concurrentPath)->not->toBe($firstPath);
    Storage::disk('local')->assertMissing($concurrentPath);
    Storage::disk('local')->assertExists($finalPath);
});

test('logo SVG, berkas bukan gambar, dan berkas terlalu besar ditolak', function (Closure $file): void {
    $this->actingAs(identityAdmin())
        ->post(route('admin.settings.appearance.update'), ['logo' => $file()])
        ->assertSessionHasErrors('logo');

    expect(app(SettingsRepository::class)->identity()['logo_path'])->toBeNull();
})->with([
    'svg' => [fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
    'pdf' => [fn () => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')],
    'terlalu besar' => [fn () => UploadedFile::fake()->image('logo.png')->size(1025)],
]);

test('aset branding yang tidak dikenal atau belum diunggah menghasilkan 404', function (): void {
    $this->get('/branding/lainnya')->assertNotFound();
    $this->get(route('branding.show', ['asset' => 'logo']))->assertNotFound();
});

test('halaman memakai favicon unggahan, dan favicon bawaan bila belum ada', function (): void {
    $this->get(route('login'))->assertSee('href="/favicon.ico"', false);

    $this->actingAs(identityAdmin())->post(route('admin.settings.appearance.update'), [
        'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
    ]);
    auth()->logout();

    $this->get(route('login'))
        ->assertSee('/branding/favicon?v=', false)
        ->assertDontSee('href="/favicon.ico"', false);
});

test('props branding bersama memakai identitas tersimpan', function (): void {
    app(SettingsRepository::class)->setMany([
        SettingKey::AppName->value => 'Aplikasi Uji',
        SettingKey::FoundationName->value => 'Yayasan Uji',
    ], identityAdmin());

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('branding.appName', 'Aplikasi Uji')
            ->where('branding.foundationName', 'Yayasan Uji')
            ->where('branding.logoUrl', null));
});
