<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\StudentImportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StudentImportRequest;
use App\Models\User;
use App\Services\Students\StudentImportRow;
use App\Services\Students\StudentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Impor siswa + wali bertahap: unggah → pratinjau → hasil (T-207).
 *
 * Berkas unggahan hanya dikenal lewat sesi pengunggahnya (tidak ada id di URL),
 * dan dihapus setelah disimpan atau dibatalkan.
 */
class StudentImportController extends Controller
{
    private const SESSION_FILE = 'student_import.file';

    private const SESSION_RESULT = 'student_import.result';

    public function __construct(private readonly StudentImportService $service) {}

    public function create(): Response
    {
        return $this->render('upload');
    }

    public function template(): BinaryFileResponse
    {
        return response()
            ->download($this->service->writeTemplate(), 'template-impor-siswa.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    public function store(StudentImportRequest $request): RedirectResponse
    {
        $file = $request->importFile();

        try {
            $path = $this->service->stage($file);
        } catch (StudentImportException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $this->forgetFile($request);
        $request->session()->forget(self::SESSION_RESULT);
        $request->session()->put(self::SESSION_FILE, [
            'path' => $path,
            'name' => Str::limit(basename($file->getClientOriginalName()), 100),
        ]);

        return to_route('admin.students.import.preview');
    }

    public function preview(Request $request): Response|RedirectResponse
    {
        $file = $this->stagedFile($request);

        if ($file === null) {
            return to_route('admin.students.import.create');
        }

        try {
            $rows = $this->service->preview($file['path']);
        } catch (StudentImportException $exception) {
            return $this->restart($request, $exception->getMessage());
        }

        $summary = ['total' => count($rows), 'new' => 0, 'update' => 0, 'unchanged' => 0, 'error' => 0];

        foreach ($rows as $row) {
            $summary[$row->status]++;
        }

        return $this->render('preview', [
            'preview' => [
                'file_name' => $file['name'],
                'summary' => $summary,
                'rows' => array_map(fn (StudentImportRow $row): array => $row->toPreview(), $rows),
            ],
        ]);
    }

    public function commit(Request $request): RedirectResponse
    {
        $file = $this->stagedFile($request);

        if ($file === null) {
            return to_route('admin.students.import.create');
        }

        try {
            $result = $this->service->commit($file['path'], $this->actor($request));
        } catch (StudentImportException $exception) {
            return $this->restart($request, $exception->getMessage());
        } finally {
            $this->forgetFile($request);
        }

        $request->session()->put(self::SESSION_RESULT, $result);

        return to_route('admin.students.import.result');
    }

    public function result(Request $request): Response|RedirectResponse
    {
        $result = $request->session()->get(self::SESSION_RESULT);

        if (! is_array($result)) {
            return to_route('admin.students.import.create');
        }

        return $this->render('result', ['result' => $result]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->forgetFile($request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Impor dibatalkan. Berkas unggahan sudah dihapus.']);

        return to_route('admin.students.import.create');
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function render(string $step, array $props = []): Response
    {
        return Inertia::render('admin/students/import', [
            'step' => $step,
            'maxRows' => StudentImportService::MAX_ROWS,
            'maxKilobytes' => StudentImportService::MAX_KILOBYTES,
            'columns' => StudentImportService::HEADERS,
            ...$props,
        ]);
    }

    /**
     * @return array{path: string, name: string}|null
     */
    private function stagedFile(Request $request): ?array
    {
        $file = $request->session()->get(self::SESSION_FILE);

        if (! is_array($file) || ! is_string($file['path'] ?? null) || ! is_string($file['name'] ?? null)) {
            return null;
        }

        if (! $this->service->exists($file['path'])) {
            $request->session()->forget(self::SESSION_FILE);

            return null;
        }

        return ['path' => $file['path'], 'name' => $file['name']];
    }

    private function forgetFile(Request $request): void
    {
        $file = $request->session()->pull(self::SESSION_FILE);

        if (is_array($file) && is_string($file['path'] ?? null)) {
            $this->service->discard($file['path']);
        }
    }

    private function restart(Request $request, string $message): RedirectResponse
    {
        $this->forgetFile($request);

        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return to_route('admin.students.import.create');
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
