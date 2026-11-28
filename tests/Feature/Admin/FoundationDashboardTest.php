<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role as RoleEnum;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Billing\ManualPaymentService;
use App\Services\Features\FeatureFlags;
use App\Services\Wallet\WalletService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->travelTo(now('Asia/Makassar')->setDate(2026, 11, 20)->setTime(10, 0));

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([Feature::Billing->value => true, Feature::Wallet->value => true], $this->admin);

    $this->mi = Unit::factory()->create(['code' => 'MI', 'name' => 'MI Darul Istiqamah']);
    $this->ra = Unit::factory()->create(['code' => 'RA', 'name' => 'RA Darul Istiqamah']);
    $this->studentMi = Student::factory()->create(['unit_id' => $this->mi->id, 'name' => 'Siswa MI']);
    $this->studentRa = Student::factory()->create(['unit_id' => $this->ra->id, 'name' => 'Siswa RA']);

    $guardianUser = User::factory()->create(['phone' => '081234567890']);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id, 'phone' => '081234567890']);
    $guardian->students()->attach($this->studentMi->id, ['relation' => 'ibu', 'is_primary' => true]);

    $fee = FeeType::factory()->create(['unit_id' => $this->mi->id, 'category' => 'SPP']);
    $feeRa = FeeType::factory()->create(['unit_id' => $this->ra->id, 'category' => 'Uang Pangkal']);

    $this->sppMi = Invoice::factory()->create([
        'student_id' => $this->studentMi->id, 'fee_type_id' => $fee->id, 'description' => 'SPP November 2026',
        'amount' => 200_000, 'status' => InvoiceStatus::Unpaid, 'due_date' => '2026-11-10',
    ]);
    Invoice::factory()->create([
        'student_id' => $this->studentRa->id, 'fee_type_id' => $feeRa->id, 'description' => 'Uang Pangkal RA',
        'amount' => 500_000, 'status' => InvoiceStatus::Unpaid, 'due_date' => '2026-12-15', 'period' => '2026-07-01',
    ]);

    app(ManualPaymentService::class)->receive($this->admin, $this->studentMi, PaymentMethod::Cash, [
        ['invoice_id' => $this->sppMi->id, 'amount' => 50_000],
    ]);
    app(WalletService::class)->topUp($this->studentRa, 30_000, 'uji-topup-1', $this->admin);
});

test('dasbor yayasan hanya untuk admin dan bendahara', function (): void {
    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->whereNot('yayasan', null));

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);
    $this->actingAs($treasurer)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->whereNot('yayasan', null));

    foreach ([RoleEnum::Teacher, RoleEnum::UnitHead, RoleEnum::Counselor] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $this->actingAs($user)->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('yayasan', null));
    }
});

test('angka kas masuk, piutang, komposisi, dan unit sesuai data nyata pada periode bulan ini', function (): void {
    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('yayasan.filters.unit', 'all')
            ->where('yayasan.filters.period', 'bulan_ini')
            ->where('yayasan.cash_in.total', 80_000)
            ->where('yayasan.cash_in.invoices', 50_000)
            ->where('yayasan.cash_in.topups', 30_000)
            ->where('yayasan.receivables.total', 650_000)
            ->where('yayasan.receivables.overdue', 150_000)
            ->where('yayasan.receivables.due_in_period', 200_000)
            ->where('yayasan.receivables.collection_rate', 25)
            ->where('yayasan.composition', [
                ['label' => 'SPP', 'amount' => 50_000],
                ['label' => 'Isi saldo kantin', 'amount' => 30_000],
            ])
            ->where('yayasan.trend.10.invoices', 50_000)
            ->where('yayasan.trend.10.topups', 30_000)
            ->where('yayasan.units.0.code', 'MI')
            ->where('yayasan.units.0.cash_in', 50_000)
            ->where('yayasan.wallet_flow.balance', 30_000)
            ->where('yayasan.wallet_flow.all_time', 30_000)
            ->where('yayasan.recent_cash.0.amount', fn ($v): bool => in_array($v, [30_000, 50_000], true))
            ->has('yayasan.recent_cash', 2));
});

test('filter unit dan periode mengubah angka; bulan lalu kosong', function (): void {
    $this->actingAs($this->admin)->get(route('admin.dashboard', ['unit' => 'RA']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('yayasan.filters.unit', 'RA')
            ->where('yayasan.cash_in.invoices', 0)
            ->where('yayasan.cash_in.topups', 30_000)
            ->where('yayasan.receivables.total', 500_000));

    $this->actingAs($this->admin)->get(route('admin.dashboard', ['periode' => 'bulan_lalu']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('yayasan.filters.period', 'bulan_lalu')
            ->where('yayasan.cash_in.total', 0));

    $this->actingAs($this->admin)->get(route('admin.dashboard', ['periode' => 'rentang', 'dari' => '2026-11-01', 'sampai' => '2026-11-30']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('yayasan.filters.period', 'rentang')
            ->where('yayasan.filters.from', '2026-11-01')
            ->where('yayasan.cash_in.total', 80_000));

    // Rentang tidak valid jatuh ke bawaan bulan ini, periode tak dikenal juga.
    $this->actingAs($this->admin)->get(route('admin.dashboard', ['periode' => 'rentang', 'dari' => '2026-12-01', 'sampai' => '2026-11-01']))
        ->assertInertia(fn (Assert $page) => $page->where('yayasan.filters.period', 'bulan_ini'));
    $this->actingAs($this->admin)->get(route('admin.dashboard', ['periode' => 'aneh']))
        ->assertInertia(fn (Assert $page) => $page->where('yayasan.filters.period', 'bulan_ini'));
});

test('piutang tertinggi memuat tautan WhatsApp wali utama dan komponen belum dibangun tanpa angka karangan', function (): void {
    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('yayasan.top_arrears.0.description', 'Uang Pangkal RA')
            ->where('yayasan.top_arrears.0.whatsapp', null)
            ->where('yayasan.top_arrears.1.description', 'SPP November 2026')
            ->where('yayasan.top_arrears.1.remaining', 150_000)
            ->where('yayasan.top_arrears.1.whatsapp', '6281234567890')
            ->where('yayasan.pending_modules', fn ($modules): bool => collect($modules)->pluck('key')->all()
                === ['cash_out', 'bos', 'zis', 'ppdb', 'dormitory', 'assets']
                && collect($modules)->every(fn ($m): bool => is_string($m['href']))));
});

test('tautan modul segera disembunyikan bila bendahara tidak berizin membuka halamannya', function (): void {
    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $this->actingAs($treasurer)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('yayasan.pending_modules', fn ($modules): bool => collect($modules)->firstWhere('key', 'assets')['href'] === null
                && is_string(collect($modules)->firstWhere('key', 'bos')['href'])));
});

test('modul tagihan nonaktif tidak mengirim piutang dan daftar tunggakan', function (): void {
    app(FeatureFlags::class)->update([Feature::Billing->value => false], $this->admin);

    $this->actingAs($this->admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('yayasan.modules.billing', false)
            ->where('yayasan.receivables', null)
            ->where('yayasan.top_arrears', []));
});
