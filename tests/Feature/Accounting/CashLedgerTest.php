<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\Role as RoleEnum;
use App\Models\CashAccount;
use App\Models\CashEntry;
use App\Models\Unit;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole(RoleEnum::Treasurer->value);
    app(FeatureFlags::class)->update([Feature::CashBook->value => true], $this->treasurer);
    $this->unit = Unit::factory()->create(['code' => 'MI']);
    $make = fn (string $name, string $dir) => CashAccount::query()->create(['unit_id' => $this->unit->id, 'name' => $name, 'direction' => $dir, 'is_active' => true]);
    $this->grant = $make('Hibah', 'in');
    $this->rent = $make('Sewa gedung', 'in');
    $this->power = $make('Listrik & air', 'out');
    $this->today = now('Asia/Makassar')->toDateString();
});

function income(object $t, CashAccount $account, int $amount)
{
    return $t->actingAs($t->treasurer)->post(route('admin.external-income.store'), [
        'cash_account_id' => $account->id, 'counterparty' => 'Mitra', 'cash_box' => 'bank',
        'amount' => $amount, 'entry_date' => $t->today, 'description' => 'Dana masuk',
    ]);
}

function expense(object $t, CashAccount $funding, int $amount, array $extra = [])
{
    return $t->actingAs($t->treasurer)->post(route('admin.cash-book.expense'), [
        'unit_id' => $t->unit->id, 'cash_account_id' => $t->power->id, 'funding_account_id' => $funding->id,
        'cash_box' => 'cash', 'amount' => $amount, 'entry_date' => $t->today, 'description' => 'Bayar listrik', ...$extra,
    ]);
}

test('pengeluaran dibatasi saldo sumber dana dan aturan alokasi, BKU menghitung saldo berjalan', function (): void {
    income($this, $this->grant, 1_000_000)->assertSessionHasNoErrors();
    income($this, $this->rent, 200_000)->assertSessionHasNoErrors();

    expense($this, $this->rent, 250_000)->assertSessionHasErrors('amount');
    expense($this, $this->grant, 400_000)->assertSessionHasNoErrors();

    // Alokasi: listrik hanya boleh dari sewa.
    $this->actingAs($this->treasurer)->put(route('admin.cash-allocations.update', $this->power), ['income_ids' => [$this->rent->id]])->assertSessionHasNoErrors();
    expense($this, $this->grant, 100_000)->assertSessionHasErrors('funding_account_id');
    expense($this, $this->rent, 150_000)->assertSessionHasNoErrors();

    // Tanggal masa depan ditolak.
    expense($this, $this->rent, 1_000, ['entry_date' => now('Asia/Makassar')->addDay()->toDateString()])->assertSessionHasErrors('entry_date');

    $this->actingAs($this->treasurer)->get(route('admin.cash-book.index', ['unit' => $this->unit->id, 'period' => 'this_month']))
        ->assertInertia(fn (Assert $page) => $page->component('admin/accounting/cash-book')
            ->where('summary.in', 1_200_000)->where('summary.out', 550_000)->where('summary.closing', 650_000)
            ->where('showRunning', true)->where('rows.data.3.running', 650_000)
            ->where('fundingAccounts', fn ($list) => collect($list)->firstWhere('id', $this->grant->id)['balance'] === 600_000
                && collect($list)->firstWhere('id', $this->rent->id)['balance'] === 50_000));
});

test('koreksi membuat entri pembalik sekali saja dan pemasukan yang sudah terpakai tidak bisa dikoreksi', function (): void {
    income($this, $this->rent, 300_000);
    $in = CashEntry::query()->sole();
    expense($this, $this->rent, 200_000);
    $out = CashEntry::query()->where('direction', 'out')->sole();

    $this->actingAs($this->treasurer)->post(route('admin.cash-book.reverse', $in), ['reason' => 'Salah input'])->assertSessionHasErrors('reason');

    $this->actingAs($this->treasurer)->post(route('admin.cash-book.reverse', $out), ['reason' => 'Batal bayar'])->assertSessionHasNoErrors();
    $this->actingAs($this->treasurer)->post(route('admin.cash-book.reverse', $out), ['reason' => 'Batal bayar'])->assertSessionHasErrors('reason');
    $this->actingAs($this->treasurer)->post(route('admin.cash-book.reverse', $in), ['reason' => 'Salah input'])->assertSessionHasNoErrors();

    expect(CashEntry::query()->count())->toBe(4)
        ->and(CashEntry::query()->whereNotNull('reverses_entry_id')->count())->toBe(2);

    $this->actingAs($this->treasurer)->get(route('admin.cash-book.index', ['unit' => $this->unit->id]))
        ->assertInertia(fn (Assert $page) => $page->where('summary.closing', 0));
});

test('pos akun yang sudah dipakai tidak bisa dihapus atau diganti alirannya, dan akses dibatasi', function (): void {
    income($this, $this->grant, 50_000);

    $this->actingAs($this->treasurer)->delete(route('admin.cash-accounts.destroy', $this->grant))->assertSessionHasErrors('master');
    $this->actingAs($this->treasurer)->put(route('admin.cash-accounts.update', $this->grant), [
        'unit_id' => $this->unit->id, 'name' => 'Hibah', 'direction' => 'out', 'is_active' => '1',
    ])->assertSessionHasErrors('direction');
    $this->actingAs($this->treasurer)->delete(route('admin.cash-accounts.destroy', $this->power))->assertSessionHasNoErrors();

    $this->actingAs($this->treasurer)->get(route('admin.cash-accounts.index'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/accounting/accounts')->where('rows.data.0.balance', 50_000));
    $pdf = $this->actingAs($this->treasurer)->get(route('admin.cash-book.pdf', ['period' => 'all']))->assertOk();
    expect(substr((string) $pdf->getContent(), 0, 4))->toBe('%PDF');
    $this->actingAs($this->treasurer)->get(route('admin.external-income.excel'))->assertOk();

    $teacher = User::factory()->create();
    $teacher->assignRole(RoleEnum::Teacher->value);
    $this->actingAs($teacher)->get(route('admin.cash-book.index'))->assertForbidden();

    app(FeatureFlags::class)->update([Feature::CashBook->value => false], $this->treasurer);
    $this->actingAs($this->treasurer)->get(route('admin.cash-book.index'))->assertNotFound();
});
