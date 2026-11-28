<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
});

test('wali kelas dapat mengunggah, menerbitkan, dan menghapus e-rapor siswa rombelnya, sedangkan guru lain ditolak', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->active()->create();
    $homeroom = User::factory()->create();
    $homeroom->assignRole(RoleEnum::Teacher->value);
    $otherTeacher = User::factory()->create();
    $otherTeacher->assignRole(RoleEnum::Teacher->value);

    $classroom = Classroom::factory()->for($mi)->for($year)->create([
        'name' => '5-A',
        'homeroom_teacher_id' => $homeroom->id,
    ]);
    $student = Student::factory()->for($mi)->create(['name' => 'Aisyah']);
    Enrollment::query()->create([
        'student_id' => $student->id,
        'classroom_id' => $classroom->id,
        'academic_year_id' => $year->id,
    ]);

    $this->actingAs($homeroom)
        ->get(route('admin.report-cards.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/elearning/report-cards')
            ->has('rows.data', 1)
        );

    $pdf = UploadedFile::fake()->create('rapor.pdf', 120, 'application/pdf');

    $this->actingAs($otherTeacher)
        ->post(route('admin.report-cards.store'), [
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'semester' => 'odd',
            'file' => $pdf,
        ])
        ->assertForbidden();

    $this->actingAs($homeroom)
        ->post(route('admin.report-cards.store'), [
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'semester' => 'odd',
            'file' => $pdf,
            'publish_now' => false,
        ])
        ->assertRedirect();

    $card = ReportCard::query()->firstOrFail();
    expect($card->published_at)->toBeNull();
    Storage::disk('local')->assertExists($card->file_path);

    $this->actingAs($homeroom)
        ->patch(route('admin.report-cards.publish', $card))
        ->assertRedirect();

    expect($card->fresh()?->published_at)->not->toBeNull();

    $this->actingAs($homeroom)
        ->get(route('admin.report-cards.download', $card))
        ->assertOk();

    $this->actingAs($homeroom)
        ->delete(route('admin.report-cards.destroy', $card))
        ->assertRedirect();

    expect(ReportCard::query()->count())->toBe(0);
    expect(AuditLog::query()->where('action', 'report_card.uploaded')->exists())->toBeTrue();
    expect(AuditLog::query()->where('action', 'report_card.published')->exists())->toBeTrue();
    expect(AuditLog::query()->where('action', 'report_card.deleted')->exists())->toBeTrue();
});

test('wali hanya melihat dan mengunduh e-rapor terbit milik anaknya lewat URL bertanda tangan, 404 untuk anak lain', function (): void {
    $mi = Unit::factory()->create(['code' => 'MI']);
    $year = AcademicYear::factory()->active()->create();
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::Admin->value);

    $student1 = Student::factory()->for($mi)->create(['name' => 'Zayd']);
    $student2 = Student::factory()->for($mi)->create(['name' => 'Umar']);

    $guardianUser = User::factory()->create();
    $guardianUser->assignRole(RoleEnum::Guardian->value);
    $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
    $guardian->students()->attach($student1->id, ['relation' => 'ayah', 'is_primary' => true]);

    Storage::disk('local')->put('report-cards/test-published.pdf', '%PDF-1.4 test');
    Storage::disk('local')->put('report-cards/test-draft.pdf', '%PDF-1.4 draft');

    $publishedCard = ReportCard::query()->create([
        'student_id' => $student1->id,
        'academic_year_id' => $year->id,
        'semester' => 'odd',
        'file_path' => 'report-cards/test-published.pdf',
        'published_at' => now(),
        'uploaded_by' => $admin->id,
    ]);

    $draftCard = ReportCard::query()->create([
        'student_id' => $student1->id,
        'academic_year_id' => $year->id,
        'semester' => 'even',
        'file_path' => 'report-cards/test-draft.pdf',
        'published_at' => null,
        'uploaded_by' => $admin->id,
    ]);

    $this->actingAs($guardianUser)
        ->get(route('guardian.students.report-cards.index', $student1))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guardian/report-cards')
            ->has('reportCards', 1)
            ->where('reportCards.0.id', $publishedCard->id)
        );

    $this->actingAs($guardianUser)
        ->get(route('guardian.students.report-cards.index', $student2))
        ->assertNotFound();

    // Unsigned URL ditolak
    $this->actingAs($guardianUser)
        ->get(route('guardian.students.report-cards.download', [$student1, $publishedCard]))
        ->assertForbidden();

    // Signed URL untuk draf ditolak 404
    $draftSigned = URL::temporarySignedRoute('guardian.students.report-cards.download', now()->addMinutes(10), [
        'student' => $student1->id,
        'reportCard' => $draftCard->id,
    ]);
    $this->actingAs($guardianUser)
        ->get($draftSigned)
        ->assertNotFound();

    // Signed URL untuk rapor terbit berhasil diunduh
    $validSigned = URL::temporarySignedRoute('guardian.students.report-cards.download', now()->addMinutes(10), [
        'student' => $student1->id,
        'reportCard' => $publishedCard->id,
    ]);
    $this->actingAs($guardianUser)
        ->get($validSigned)
        ->assertOk();
});
