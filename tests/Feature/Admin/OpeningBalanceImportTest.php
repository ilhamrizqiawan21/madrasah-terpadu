<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\InvoiceStatus;
use App\Enums\Role as RoleEnum;
use App\Models\AuditLog;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function enableBillingForOpeningBalance(User $actor): void
{
    app(FeatureFlags::class)->update([
        Feature::Billing->value => true,
    ], $actor);
}

/**
 * @param  list<list<string|int>>  $rows
 */
function makeOpeningBalanceXlsx(array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'saldo_awal_').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }
    $writer->close();

    return new UploadedFile($path, 'saldo-awal.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

test('hanya pengguna berizin penerbitan tagihan yang dapat mengakses saldo awal migrasi', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForOpeningBalance($admin);

    $unitHead = User::factory()->create();
    $unitHead->assignRole(RoleEnum::UnitHead->value);

    $this->actingAs($unitHead)
        ->get(route('admin.opening-balances.index'))
        ->assertForbidden();

    $treasurer = User::factory()->create();
    $treasurer->assignRole(RoleEnum::Treasurer->value);

    $this->actingAs($treasurer)
        ->get(route('admin.opening-balances.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/billing/opening-balances'));
});

test('pratinjau dan impor saldo awal membuat tagihan jenis Saldo Awal dengan deskripsi asli dan idempoten saat diimpor ulang', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);
    enableBillingForOpeningBalance($admin);

    $mi = Unit::factory()->create(['code' => 'MI']);
    $student = Student::factory()->create(['unit_id' => $mi->id, 'nis' => '2026001', 'name' => 'Rafi']);

    $file = makeOpeningBalanceXlsx([
        ['nis', 'deskripsi', 'nominal', 'jatuh_tempo'],
        ['2026001', 'Tunggakan SPP 2025/2026', 350000, '2026-07-15'],
        ['9999999', 'NIS tidak ada', 100000, '2026-07-15'],
    ]);

    // Pratinjau tidak menulis ke DB
    $this->actingAs($admin)
        ->postJson(route('admin.opening-balances.preview'), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('summary.will_create', 1)
        ->assertJsonPath('summary.error', 1)
        ->assertJsonPath('summary.total_amount', 350000);

    expect(Invoice::query()->count())->toBe(0);

    // Eksekusi impor
    $file2 = makeOpeningBalanceXlsx([
        ['nis', 'deskripsi', 'nominal', 'jatuh_tempo'],
        ['2026001', 'Tunggakan SPP 2025/2026', 350000, '2026-07-15'],
        ['9999999', 'NIS tidak ada', 100000, '2026-07-15'],
    ]);

    $this->actingAs($admin)
        ->post(route('admin.opening-balances.store'), ['file' => $file2])
        ->assertRedirect();

    $feeType = FeeType::query()->where('name', 'Saldo Awal')->firstOrFail();
    $invoice = Invoice::query()->where('student_id', $student->id)->firstOrFail();

    expect($invoice->fee_type_id)->toBe($feeType->id)
        ->and($invoice->description)->toBe('Tunggakan SPP 2025/2026')
        ->and($invoice->amount)->toBe(350000)
        ->and($invoice->due_date->toDateString())->toBe('2026-07-15')
        ->and($invoice->status)->toBe(InvoiceStatus::Unpaid);

    expect(AuditLog::query()->where('action', 'opening_balance.imported')->exists())->toBeTrue();

    // Impor ulang file yang sama -> idempoten (tidak menggandakan)
    $file3 = makeOpeningBalanceXlsx([
        ['nis', 'deskripsi', 'nominal', 'jatuh_tempo'],
        ['2026001', 'Tunggakan SPP 2025/2026', 350000, '2026-07-15'],
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.opening-balances.preview'), ['file' => $file3])
        ->assertOk()
        ->assertJsonPath('summary.will_create', 0)
        ->assertJsonPath('summary.already_exists', 1);

    $this->actingAs($admin)
        ->post(route('admin.opening-balances.store'), ['file' => $file3])
        ->assertRedirect();

    expect(Invoice::query()->count())->toBe(1);
});
