<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\TeachingModule;
use App\Models\Unit;
use App\Models\User;
use App\Services\Academic\TeachingModuleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Akademik > Supervisi Modul Ajar (RPP). Guru melihat berkasnya sendiri; peninjau melihat semua.
 */
class TeachingModuleController extends Controller
{
    private const array FILE_RULES = ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'];

    public function __construct(private readonly TeachingModuleService $service) {}

    public function index(Request $request): Response
    {
        $user = $this->actor($request);
        $canReview = $user->can(Permission::TeachingModulesReview->value);
        $unitId = $request->integer('unit') ?: null;
        $status = array_key_exists($request->string('status')->toString(), TeachingModule::STATUSES) ? $request->string('status')->toString() : null;
        $search = trim($request->string('search')->toString());

        $paginator = TeachingModule::query()
            ->with(['teacher:id,name', 'reviewer:id,name', 'unit:id,code'])
            ->when(! $canReview, fn (Builder $q) => $q->where('user_id', $user->id))
            ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $i) => $i->whereLike('title', "%{$search}%")
                ->orWhereHas('teacher', fn (Builder $t) => $t->whereLike('name', "%{$search}%"))))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest('id')
            ->paginate(25)->withQueryString();

        return Inertia::render('admin/academic/teaching-modules', [
            'rows' => [
                'data' => array_values(array_map(fn (TeachingModule $m): array => [
                    'id' => $m->id, 'unit_code' => $m->unit->code, 'week_number' => $m->week_number, 'title' => $m->title,
                    'teacher' => $m->teacher->name, 'is_mine' => $m->user_id === $user->id,
                    'original_name' => $m->original_name, 'status' => $m->status,
                    'review_notes' => $m->review_notes, 'reviewer' => $m->reviewer?->name,
                    'uploaded_at' => $m->created_at?->toIso8601String(),
                ], $paginator->items())),
                'meta' => [
                    'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
                ],
            ],
            'filters' => ['search' => $search, 'unit' => $unitId, 'status' => $status],
            'unitOptions' => array_values(Unit::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])
                ->map(fn (Unit $u): array => ['value' => (string) $u->id, 'label' => $u->name])->all()),
            'statusOptions' => array_map(fn (string $k, string $v): array => ['value' => $k, 'label' => $v], array_keys(TeachingModule::STATUSES), TeachingModule::STATUSES),
            'canUpload' => $user->can(Permission::TeachingModulesUpload->value),
            'canReview' => $canReview,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'week_number' => ['required', 'integer', 'between:1,52'],
            'title' => ['required', 'string', 'max:150'],
            'file' => self::FILE_RULES,
        ], [], ['unit_id' => 'unit', 'week_number' => 'minggu ke', 'title' => 'judul dokumen', 'file' => 'berkas RPP']);
        $yearId = AcademicYear::query()->where('is_active', true)->value('id');
        abort_if($yearId === null, 404);

        $this->service->upload([
            'unit_id' => (int) $data['unit_id'], 'academic_year_id' => (int) $yearId,
            'week_number' => (int) $data['week_number'], 'title' => trim((string) $data['title']),
        ], $this->file($request), $this->actor($request));

        return $this->done('Modul ajar terkirim ke kepala sekolah untuk ditinjau.');
    }

    public function resubmit(Request $request, TeachingModule $module): RedirectResponse
    {
        abort_unless($module->user_id === $this->actor($request)->id, 404);
        $request->validate(['file' => self::FILE_RULES], [], ['file' => 'berkas RPP']);
        $this->service->resubmit($module, $this->file($request), $this->actor($request));

        return $this->done('Berkas revisi terkirim untuk ditinjau ulang.');
    }

    public function review(Request $request, TeachingModule $module): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'revision'])],
            'review_notes' => ['nullable', 'required_if:status,revision', 'string', 'max:2000'],
        ], ['review_notes.required_if' => 'Tuliskan catatan perbaikan untuk guru.'], ['review_notes' => 'catatan evaluasi']);

        $notes = isset($data['review_notes']) ? trim((string) $data['review_notes']) : null;
        $this->service->review($module, (string) $data['status'], $notes === '' ? null : $notes, $this->actor($request));

        return $this->done($data['status'] === 'approved' ? 'Modul ajar disetujui.' : 'Modul ajar dikembalikan untuk revisi.');
    }

    public function destroy(Request $request, TeachingModule $module): RedirectResponse
    {
        $user = $this->actor($request);
        abort_unless($module->user_id === $user->id || $user->can(Permission::TeachingModulesReview->value), 404);
        $this->service->delete($module, $user);

        return $this->done('Modul ajar dihapus.');
    }

    public function download(Request $request, TeachingModule $module): StreamedResponse
    {
        $user = $this->actor($request);
        abort_unless($module->user_id === $user->id || $user->can(Permission::TeachingModulesReview->value), 404);
        $disk = Storage::disk(TeachingModuleService::DISK);
        abort_unless($disk->exists($module->file_path), 404);

        return $disk->download($module->file_path, $module->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    private function file(Request $request): UploadedFile
    {
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        return $file;
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
