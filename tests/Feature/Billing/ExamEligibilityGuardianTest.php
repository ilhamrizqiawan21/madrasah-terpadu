<?php

declare(strict_types=1);

use App\Enums\ExamRequirementScope;
use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\Role as RoleEnum;
use App\Jobs\SendExamEligibilityReminders;
use App\Models\ExamEligibilityOverride;
use App\Models\ExamPeriod;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\ExamEligibilityReminderNotification;
use App\Services\Features\FeatureFlags;
use App\Services\Guardian\GuardianHomeSummaryService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('beranda wali menampilkan kartu perlu tindakan saat anak belum layak dan menyembunyikannya saat layak atau dispensasi', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'Madrasah Ibtidaiyah']);
    $student = Student::factory()->for($mi)->create(['name' => 'Aisyah', 'nickname' => 'Aisyah']);
    $guardianUser = User::factory()->create();
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->for($guardianUser)->create();
    $guardian->students()->attach($student->id, ['relation' => 'ibu', 'is_primary' => true]);

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
        Feature::ExamEligibility->value => true,
    ], $admin);

    $spp = FeeType::factory()->for($mi)->create(['name' => 'SPP MI']);
    $period = ExamPeriod::factory()->for($mi)->active()->create([
        'name' => 'PAS Ganjil',
        'starts_on' => now()->addDays(5)->toDateString(),
        'ends_on' => now()->addDays(10)->toDateString(),
        'created_by' => $admin->id,
    ]);
    $period->requirements()->create([
        'fee_type_id' => $spp->id,
        'invoice_scope' => ExamRequirementScope::AllOpen,
    ]);

    $invoice = Invoice::factory()->for($student)->for($spp)->create([
        'description' => 'SPP November 2026',
        'amount' => 150_000,
        'paid_total' => 0,
        'status' => InvoiceStatus::Unpaid,
    ]);

    $cache = app(GuardianHomeSummaryService::class);
    $cache->invalidate($student->id);

    // 1. Belum layak -> kartu tampil
    $this->actingAs($guardianUser)
        ->get(route('guardian.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('home.exam_eligibility.period_name', 'PAS Ganjil')
            ->where('home.exam_eligibility.total_amount', 150_000)
            ->has('home.exam_eligibility.invoices', 1)
        );

    // 2. Diberi dispensasi -> kartu hilang
    $override = ExamEligibilityOverride::query()->create([
        'exam_period_id' => $period->id,
        'student_id' => $student->id,
        'reason' => 'Dispensasi',
        'granted_by' => $admin->id,
        'granted_at' => now(),
    ]);
    $cache->invalidate($student->id);

    $this->actingAs($guardianUser)
        ->get(route('guardian.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('home.exam_eligibility', null));

    // 3. Cabut dispensasi lalu lunasi tagihan -> kartu juga hilang
    $override->forceFill(['revoked_at' => now(), 'revoked_by' => $admin->id])->save();
    $invoice->forceFill(['paid_total' => 150_000, 'status' => InvoiceStatus::Paid])->save();
    $cache->invalidate($student->id);

    $this->actingAs($guardianUser)
        ->get(route('guardian.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('home.exam_eligibility', null));
});

test('pengingat kelayakan ujian H-7 dan H-1 hanya dikirim untuk anak belum layak dan maksimal sekali per hari', function (): void {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-24 07:15', 'Asia/Makassar'));

    $mi = Unit::factory()->create(['code' => 'MI', 'name' => 'Madrasah Ibtidaiyah']);
    $sIneligible = Student::factory()->for($mi)->create(['name' => 'Zaid']);
    $sEligible = Student::factory()->for($mi)->create(['name' => 'Umar']);
    $sDispensed = Student::factory()->for($mi)->create(['name' => 'Ali']);

    $guardians = [];
    foreach ([$sIneligible, $sEligible, $sDispensed] as $idx => $st) {
        $u = User::factory()->create();
        $u->assignRole(RoleEnum::Guardian->value);
        $g = Guardian::factory()->for($u)->create();
        $g->students()->attach($st->id, ['relation' => 'ayah', 'is_primary' => true]);
        $guardians[$idx] = $u;
    }

    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
        Feature::ExamEligibility->value => true,
    ], $admin);

    $spp = FeeType::factory()->for($mi)->create(['name' => 'SPP MI']);
    // Mulai tepat H-7 dari 2026-11-24 -> 2026-12-01
    $period = ExamPeriod::factory()->for($mi)->active()->create([
        'name' => 'PAS Ganjil',
        'starts_on' => '2026-12-01',
        'ends_on' => '2026-12-07',
        'created_by' => $admin->id,
    ]);
    $period->requirements()->create([
        'fee_type_id' => $spp->id,
        'invoice_scope' => ExamRequirementScope::AllOpen,
    ]);

    Invoice::factory()->for($sIneligible)->for($spp)->create(['amount' => 150_000, 'paid_total' => 0, 'status' => InvoiceStatus::Unpaid]);
    Invoice::factory()->for($sEligible)->for($spp)->create(['amount' => 150_000, 'paid_total' => 150_000, 'status' => InvoiceStatus::Paid]);
    Invoice::factory()->for($sDispensed)->for($spp)->create(['amount' => 150_000, 'paid_total' => 0, 'status' => InvoiceStatus::Unpaid]);

    ExamEligibilityOverride::query()->create([
        'exam_period_id' => $period->id,
        'student_id' => $sDispensed->id,
        'reason' => 'Dispensasi',
        'granted_by' => $admin->id,
        'granted_at' => now(),
    ]);

    $sentFirst = app()->call([new SendExamEligibilityReminders, 'handle']);
    $sentSecond = app()->call([new SendExamEligibilityReminders, 'handle']);

    expect($sentFirst)->toBe(1)
        ->and($sentSecond)->toBe(0);

    Notification::assertSentTo($guardians[0], ExamEligibilityReminderNotification::class);
    Notification::assertNotSentTo($guardians[1], ExamEligibilityReminderNotification::class);
    Notification::assertNotSentTo($guardians[2], ExamEligibilityReminderNotification::class);
});
