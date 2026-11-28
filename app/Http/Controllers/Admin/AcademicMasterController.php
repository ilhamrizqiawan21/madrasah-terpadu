<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassGroup;
use App\Models\GradeLevel;
use App\Models\Major;
use App\Models\Unit;
use App\Models\User;
use App\Services\Academic\AcademicMasterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akademik > Tingkat Kelas, Rombel, Jurusan: master sederhana per unit dengan satu halaman generik.
 */
class AcademicMasterController extends Controller
{
    /** @var array<string, array{model: class-string<GradeLevel|ClassGroup|Major>, table: string, title: string, noun: string, description: string, max: int}> */
    private const array KINDS = [
        'tingkat' => ['model' => GradeLevel::class, 'table' => 'grade_levels', 'title' => 'Tingkat Kelas', 'noun' => 'tingkat kelas', 'description' => 'Jenjang kelas per unit, mis. Kelas 1–6 atau A/B. Dipakai saat membuat rombel.', 'max' => 20],
        'rombel' => ['model' => ClassGroup::class, 'table' => 'class_groups', 'title' => 'Rombel', 'noun' => 'rombel', 'description' => 'Nama kelompok belajar per unit, mis. A, B, Reguler.', 'max' => 30],
        'jurusan' => ['model' => Major::class, 'table' => 'majors', 'title' => 'Jurusan', 'noun' => 'jurusan', 'description' => 'Jurusan atau peminatan per unit (opsional).', 'max' => 60],
    ];

    public function __construct(private readonly AcademicMasterService $service) {}

    public function index(Request $request, string $kind): Response
    {
        $config = $this->config($kind);
        $search = trim($request->string('search')->toString());
        $unitId = $request->integer('unit') ?: null;

        $paginator = $config['model']::query()
            ->with('unit:id,name')
            ->when($unitId !== null, fn (Builder $q) => $q->where('unit_id', $unitId))
            ->when($search !== '', fn (Builder $q) => $q->whereLike('name', "%{$search}%"))
            ->orderBy('unit_id')
            ->when($kind === 'tingkat', fn (Builder $q) => $q->orderBy('sort_order'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/academic/master', [
            'kind' => $kind,
            'meta' => ['title' => $config['title'], 'noun' => $config['noun'], 'description' => $config['description']],
            'rows' => [
                'data' => array_values(array_map(fn (Model $m): array => [
                    'id' => (int) $m->getKey(),
                    'unit_id' => (int) $m->getAttribute('unit_id'),
                    'unit_name' => (string) $m->getRelationValue('unit')?->getAttribute('name'),
                    'name' => (string) $m->getAttribute('name'),
                    'sort_order' => $m instanceof GradeLevel ? $m->sort_order : null,
                ], $paginator->items())),
                'meta' => [
                    'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
                    'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
                ],
            ],
            'filters' => ['search' => $search, 'unit' => $unitId],
            'unitOptions' => array_values(Unit::query()->where('is_active', true)->orderBy('id')->get(['id', 'name'])
                ->map(fn (Unit $u): array => ['value' => (string) $u->id, 'label' => $u->name])->all()),
        ]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        $config = $this->config($kind);
        $this->service->save(new $config['model'], $this->data($request, $kind, null), $this->actor($request));

        return $this->done("{$config['title']} ditambahkan.");
    }

    public function update(Request $request, string $kind, int $id): RedirectResponse
    {
        $config = $this->config($kind);
        $model = $config['model']::query()->findOrFail($id);
        $this->service->save($model, $this->data($request, $kind, $id), $this->actor($request));

        return $this->done("{$config['title']} diperbarui.");
    }

    public function destroy(Request $request, string $kind, int $id): RedirectResponse
    {
        $config = $this->config($kind);
        $this->service->delete($config['model']::query()->findOrFail($id), $this->actor($request));

        return $this->done("{$config['title']} dihapus.");
    }

    /**
     * @return array<string, int|string>
     */
    private function data(Request $request, string $kind, ?int $ignoreId): array
    {
        $config = $this->config($kind);
        $rules = [
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:'.$config['max'], Rule::unique($config['table'], 'name')->where('unit_id', $request->integer('unit_id'))->ignore($ignoreId)],
        ];

        if ($kind === 'tingkat') {
            $rules['sort_order'] = ['required', 'integer', 'min:0', 'max:99'];
        }

        $data = $request->validate($rules, [], ['unit_id' => 'unit', 'name' => 'nama', 'sort_order' => 'urutan']);

        $clean = ['unit_id' => (int) $data['unit_id'], 'name' => trim((string) $data['name'])];
        if ($kind === 'tingkat') {
            $clean['sort_order'] = (int) $data['sort_order'];
        }

        return $clean;
    }

    /**
     * @return array{model: class-string<GradeLevel|ClassGroup|Major>, table: string, title: string, noun: string, description: string, max: int}
     */
    private function config(string $kind): array
    {
        abort_unless(array_key_exists($kind, self::KINDS), 404);

        return self::KINDS[$kind];
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
