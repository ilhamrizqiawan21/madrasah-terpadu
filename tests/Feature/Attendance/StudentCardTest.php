<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Enums\StudentCardKind;
use App\Models\AuditLog;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\User;
use App\Services\Attendance\StudentCardService;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function cardAdmin(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole(RoleEnum::Admin->value);

    return $user;
}

test('tamu dan peran tanpa izin ditolak mengakses kartu siswa', function (): void {
    $student = Student::factory()->create();

    $this->post(route('admin.students.cards.qr.issue', $student))
        ->assertRedirect(route('login'));

    $teacher = User::factory()->create(['is_active' => true]);
    $teacher->assignRole(RoleEnum::Teacher->value);

    $this->actingAs($teacher)
        ->post(route('admin.students.cards.qr.issue', $student))
        ->assertForbidden();

    $this->actingAs($teacher)
        ->post(route('admin.students.cards.rfid.register', $student), ['uid' => 'AB:CD:12:34'])
        ->assertForbidden();

    $this->actingAs($teacher)
        ->delete(route('admin.students.cards.revoke', $student))
        ->assertForbidden();
});

test('menerbitkan kartu QR menyimpan hash SHA-256 bukan token mentah dan membatasi satu kartu aktif', function (): void {
    $admin = cardAdmin();
    $student = Student::factory()->create();
    $service = app(StudentCardService::class);

    $first = $service->issueQr($student, $admin);
    expect(strlen($first['token']))->toBe(64)
        ->and($first['card']->kind)->toBe(StudentCardKind::Qr)
        ->and($first['card']->token_hash)->toBe(hash('sha256', $first['token']))
        ->and($first['card']->token_hash)->not->toBe($first['token']);

    expect($service->findActiveByRawToken($first['token'])?->id)->toBe($first['card']->id);

    // Terbitkan ulang → kartu pertama otomatis dicabut, hanya satu kartu aktif tersisa.
    $second = $service->issueQr($student, $admin);

    expect(StudentCard::query()->where('student_id', $student->id)->active()->count())->toBe(1)
        ->and($first['card']->fresh()?->isActive())->toBeFalse()
        ->and($service->findActiveByRawToken($first['token']))->toBeNull()
        ->and($service->findActiveByRawToken($second['token'])?->id)->toBe($second['card']->id);

    // Token mentah tidak pernah tercatat di audit log.
    $auditPayloads = AuditLog::query()
        ->where('subject_id', $student->id)
        ->pluck('after')
        ->map(fn ($v): string => json_encode($v) ?: '')
        ->implode(' ');

    expect($auditPayloads)->not->toContain($first['token'])
        ->not->toContain($second['token']);
});

test('kartu RFID menormalisasi UID dan menolak duplikat serta pencabutan menonaktifkan kartu', function (): void {
    $admin = cardAdmin();
    $student1 = Student::factory()->create();
    $student2 = Student::factory()->create();
    $service = app(StudentCardService::class);

    $this->actingAs($admin)
        ->from(route('admin.students.index'))
        ->post(route('admin.students.cards.rfid.register', $student1), [
            'uid' => 'ab:cd-12 34',
        ])
        ->assertRedirect(route('admin.students.index'))
        ->assertSessionHasNoErrors();

    $card = $student1->activeCard()->first();
    expect($card)->not->toBeNull()
        ->and($card?->kind)->toBe(StudentCardKind::Rfid)
        ->and($card?->token_hash)->toBe(hash('sha256', 'ABCD1234'))
        ->and($service->findActiveByRawToken('ab:cd-12 34')?->id)->toBe($card?->id)
        ->and($service->findActiveByRawToken('ABCD1234')?->id)->toBe($card?->id);

    // UID sama pada siswa lain ditolak.
    $this->actingAs($admin)
        ->from(route('admin.students.index'))
        ->post(route('admin.students.cards.rfid.register', $student2), [
            'uid' => 'ABCD1234',
        ])
        ->assertSessionHasErrors('uid');

    // Cabut kartu → tidak lagi ditemukan saat scan.
    $this->actingAs($admin)
        ->from(route('admin.students.index'))
        ->delete(route('admin.students.cards.revoke', $student1))
        ->assertRedirect(route('admin.students.index'));

    expect($student1->activeCard()->first())->toBeNull()
        ->and($service->findActiveByRawToken('ABCD1234'))->toBeNull();
});
