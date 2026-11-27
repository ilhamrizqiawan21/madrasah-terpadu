<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\HabitItem;
use App\Models\HabitLog;
use App\Models\Student;
use App\Models\User;
use App\Services\Habits\HabitLogService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HabitController extends Controller
{
    public function __construct(
        private readonly HabitLogService $service,
    ) {}

    public function index(Request $request, Student $student): Response
    {
        $timezone = config('sekolah.timezone', 'Asia/Makassar');
        $tz = is_string($timezone) ? $timezone : 'Asia/Makassar';
        $today = CarbonImmutable::now($tz)->startOfDay();

        $requestedDate = (string) $request->query('date', $today->toDateString());
        $selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)
            ? CarbonImmutable::parse($requestedDate, $tz)->startOfDay()
            : $today;

        if ($selectedDate->greaterThan($today) || $selectedDate->lessThan($today->subDays(HabitLogService::MAX_BACKDATE_DAYS))) {
            $selectedDate = $today;
        }

        $dateString = $selectedDate->toDateString();

        $dateOptions = [];
        for ($i = 0; $i <= HabitLogService::MAX_BACKDATE_DAYS; $i++) {
            $d = $today->subDays($i);
            $dateOptions[] = [
                'date' => $d->toDateString(),
                'label' => match ($i) {
                    0 => 'Hari ini',
                    1 => 'Kemarin',
                    default => $d->format('d/m'),
                },
            ];
        }

        $items = HabitItem::query()
            ->active()
            ->forUnit($student->unit_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $logsByItem = HabitLog::query()
            ->where('student_id', $student->id)
            ->whereDate('log_date', $dateString)
            ->get()
            ->keyBy('habit_item_id');

        $rows = $items->map(function (HabitItem $item) use ($logsByItem): array {
            /** @var HabitLog|null $log */
            $log = $logsByItem->get($item->id);

            return [
                'habit_item_id' => $item->id,
                'name' => $item->name,
                'aspect' => $item->aspect->value,
                'aspect_label' => $item->aspect->label(),
                'done' => $log->done ?? false,
                'verification' => $log->verification ?? 'pending',
                'note' => $log?->note,
            ];
        })->values()->all();

        return Inertia::render('guardian/habits', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
            ],
            'selectedDate' => $dateString,
            'dateOptions' => $dateOptions,
            'items' => $rows,
        ]);
    }

    public function toggle(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'habit_item_id' => ['required', 'integer', 'exists:habit_items,id'],
            'log_date' => ['required', 'date'],
            'done' => ['required', 'boolean'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $item = HabitItem::query()->findOrFail((int) $validated['habit_item_id']);

        $this->service->toggleByGuardian(
            actor: $actor,
            student: $student,
            item: $item,
            logDate: (string) $validated['log_date'],
            done: (bool) $validated['done'],
        );

        return back();
    }
}
