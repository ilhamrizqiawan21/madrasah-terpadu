<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\TeachingModule;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Unggah modul ajar dan keputusan supervisi. Berkas disimpan di disk privat dan
 * hanya diunduh lewat rute yang memeriksa hak akses.
 */
final class TeachingModuleService
{
    public const string DISK = 'local';

    private const string DIRECTORY = 'teaching-modules';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{unit_id: int, academic_year_id: int, week_number: int, title: string}  $data
     */
    public function upload(array $data, UploadedFile $file, User $teacher): TeachingModule
    {
        $path = $file->store(self::DIRECTORY, self::DISK);
        if ($path === false) {
            throw new RuntimeException('Berkas modul ajar gagal disimpan.');
        }

        try {
            return DB::transaction(function () use ($data, $file, $path, $teacher): TeachingModule {
                $module = TeachingModule::query()->create([
                    ...$data,
                    'user_id' => $teacher->id,
                    'file_path' => $path,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'status' => 'pending',
                ]);
                $this->audit->record('teaching_module.uploaded', $module, null, ['title' => $module->title, 'week_number' => $module->week_number], $teacher->id);

                return $module;
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($path);
            throw $e;
        }
    }

    /** Guru mengganti berkas setelah diminta revisi; status kembali menunggu review. */
    public function resubmit(TeachingModule $module, UploadedFile $file, User $teacher): void
    {
        if ($module->status !== 'revision') {
            throw ValidationException::withMessages(['file' => 'Berkas hanya bisa diganti setelah diminta revisi.']);
        }

        $path = $file->store(self::DIRECTORY, self::DISK);
        if ($path === false) {
            throw new RuntimeException('Berkas modul ajar gagal disimpan.');
        }
        $old = $module->file_path;

        DB::transaction(function () use ($module, $file, $path, $teacher): void {
            $module->forceFill([
                'file_path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'status' => 'pending',
            ])->save();
            $this->audit->record('teaching_module.resubmitted', $module, ['status' => 'revision'], ['status' => 'pending'], $teacher->id);
        });

        Storage::disk(self::DISK)->delete($old);
    }

    public function review(TeachingModule $module, string $status, ?string $notes, User $reviewer): void
    {
        DB::transaction(function () use ($module, $status, $notes, $reviewer): void {
            $before = ['status' => $module->status];
            $module->forceFill([
                'status' => $status,
                'review_notes' => $notes,
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();
            $this->audit->record('teaching_module.reviewed', $module, $before, ['status' => $status], $reviewer->id);
        });
    }

    public function delete(TeachingModule $module, User $actor): void
    {
        if ($module->status === 'approved') {
            throw ValidationException::withMessages(['module' => 'Modul ajar yang sudah disetujui tidak bisa dihapus.']);
        }

        DB::transaction(function () use ($module, $actor): void {
            $this->audit->record('teaching_module.deleted', $module, ['title' => $module->title], null, $actor->id);
            $module->delete();
        });

        Storage::disk(self::DISK)->delete($module->file_path);
    }
}
