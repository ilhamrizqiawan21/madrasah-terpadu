<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\AcademicYear;
use App\Models\TeachingModule;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->unit = Unit::factory()->create(['code' => 'MI']);
    AcademicYear::factory()->active()->create();
    $this->teacher = User::factory()->create();
    $this->teacher->assignRole(RoleEnum::Teacher->value);
    $this->otherTeacher = User::factory()->create();
    $this->otherTeacher->assignRole(RoleEnum::Teacher->value);
    $this->head = User::factory()->create();
    $this->head->assignRole(RoleEnum::UnitHead->value);
});

test('guru unggah, kepala unit minta revisi, guru kirim ulang, lalu disetujui', function (): void {
    $this->actingAs($this->teacher)->post(route('admin.teaching-modules.store'), [
        'unit_id' => $this->unit->id, 'week_number' => 3, 'title' => 'RPP Pecahan',
        'file' => UploadedFile::fake()->create('rpp.pdf', 200, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $module = TeachingModule::query()->sole();
    Storage::disk('local')->assertExists($module->file_path);

    // Guru lain tidak melihat dan tidak bisa mengunduh berkas ini.
    $this->actingAs($this->otherTeacher)->get(route('admin.teaching-modules.index'))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 0));
    $this->actingAs($this->otherTeacher)->get(route('admin.teaching-modules.download', $module))->assertNotFound();
    $this->actingAs($this->teacher)->put(route('admin.teaching-modules.review', $module), ['status' => 'approved'])->assertForbidden();

    $this->actingAs($this->head)->put(route('admin.teaching-modules.review', $module), ['status' => 'revision'])
        ->assertSessionHasErrors('review_notes');
    $this->actingAs($this->head)->put(route('admin.teaching-modules.review', $module), ['status' => 'revision', 'review_notes' => 'Lengkapi asesmen.'])
        ->assertSessionHasNoErrors();

    $old = $module->file_path;
    $this->actingAs($this->teacher)->post(route('admin.teaching-modules.resubmit', $module), [
        'file' => UploadedFile::fake()->create('rpp-v2.docx', 100),
    ])->assertSessionHasNoErrors();
    Storage::disk('local')->assertMissing($old);
    expect($module->fresh()->status)->toBe('pending');

    $this->actingAs($this->head)->put(route('admin.teaching-modules.review', $module), ['status' => 'approved'])->assertSessionHasNoErrors();
    $this->actingAs($this->head)->get(route('admin.teaching-modules.download', $module))->assertOk();
    $this->actingAs($this->teacher)->delete(route('admin.teaching-modules.destroy', $module))->assertSessionHasErrors('module');
});

test('berkas selain pdf/doc/docx ditolak', function (): void {
    $this->actingAs($this->teacher)->post(route('admin.teaching-modules.store'), [
        'unit_id' => $this->unit->id, 'week_number' => 1, 'title' => 'X',
        'file' => UploadedFile::fake()->create('x.exe', 10),
    ])->assertSessionHasErrors('file');
});
