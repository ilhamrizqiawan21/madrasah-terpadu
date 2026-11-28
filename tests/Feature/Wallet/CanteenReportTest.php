<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\CanteenVoucher;
use App\Models\RewardOffer;
use App\Models\RewardRedemption;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use App\Services\Wallet\CanteenPosService;
use App\Services\Wallet\WalletService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('laporan kantin menghitung penjualan per kasir, top-up, saldo mengendap, dan voucher subsidi terpisah serta ekspor PDF/Excel', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-28 11:00', 'Asia/Makassar'));

    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'Madrasah Ibtidaiyah']);
    $student = Student::factory()->for($mi)->create();

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([Feature::Wallet->value => true], $admin);

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $cashier = User::factory()->create(['name' => 'Kasir Kantin 1']);
    $cashier->assignRole(RoleEnum::CanteenCashier->value);

    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);

    app(WalletService::class)->topUp($student, 50_000, 'topup-cash:'.Str::uuid(), $treasurer);

    $offer = RewardOffer::query()->create([
        'unit_id' => $mi->id,
        'name' => 'Voucher 5rb',
        'cost_points' => 5,
        'voucher_amount' => 5_000,
        'voucher_valid_days' => 30,
        'is_active' => true,
    ]);
    $redemption = RewardRedemption::query()->create([
        'student_id' => $student->id,
        'academic_year_id' => AcademicYear::factory()->active()->create()->id,
        'reward_offer_id' => $offer->id,
        'cost_points' => 5,
        'voucher_amount' => 5_000,
        'requested_by' => $admin->id,
        'status' => 'approved',
        'decided_by' => $admin->id,
        'decided_at' => now(),
    ]);
    CanteenVoucher::query()->create([
        'student_id' => $student->id,
        'reward_redemption_id' => $redemption->id,
        'amount' => 5_000,
        'remaining' => 5_000,
        'expires_on' => '2026-12-30',
        'created_at' => now(),
    ]);

    app(CanteenPosService::class)->purchase($cashier, $student, 12_000, (string) Str::uuid());

    // Guru -> 403
    $this->actingAs($teacher)
        ->get(route('admin.reports.canteen.index'))
        ->assertForbidden();

    // Bendahara -> 200 dengan angka tepat
    $this->actingAs($treasurer)
        ->get(route('admin.reports.canteen.index', ['unit' => 'MI', 'from' => '2026-11-28', 'to' => '2026-11-28']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/canteen')
            ->where('summary.sales_total', 12_000)
            ->where('summary.sales_voucher', 5_000)
            ->where('summary.sales_wallet', 7_000)
            ->where('summary.topup_cash', 50_000)
            ->where('summary.floating_balance', 43_000)
            ->where('byCashier.0.cashier_name', 'Kasir Kantin 1')
        );

    $pdf = $this->actingAs($treasurer)->get(route('admin.reports.canteen.pdf'));
    $pdf->assertOk();
    expect((string) $pdf->getContent())->toStartWith('%PDF');

    $this->actingAs($treasurer)->get(route('admin.reports.canteen.excel'))->assertOk();
});
