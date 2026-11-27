<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Attendance\AttendanceClassifier;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->can(Permission::AuditView->value), 403);

        $userId = $request->filled('user_id') ? $request->integer('user_id') : null;
        $action = trim((string) $request->query('action', ''));
        $subjectType = trim((string) $request->query('subject_type', ''));
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        if ($dateFrom !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = '';
        }
        if ($dateTo !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = '';
        }

        $paginator = AuditLog::query()
            ->with('user:id,name,username')
            ->when($userId !== null, fn (Builder $q) => $q->where('user_id', $userId))
            ->when($action !== '', fn (Builder $q) => $q->where('action', $action))
            ->when($subjectType !== '', fn (Builder $q) => $q->where('subject_type', $subjectType))
            ->when($dateFrom !== '', fn (Builder $q) => $q->where('created_at', '>=', $dateFrom.' 00:00:00'))
            ->when($dateTo !== '', fn (Builder $q) => $q->where('created_at', '<=', $dateTo.' 23:59:59'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $logs = $paginator->getCollection()->map(fn (AuditLog $log): array => [
            'id' => $log->id,
            'created_at' => $log->created_at->setTimezone(AttendanceClassifier::BUSINESS_TIMEZONE)->format('d/m/Y H.i.s'),
            'user_name' => $log->user !== null ? $log->user->name : 'Sistem',
            'action' => $log->action,
            'subject_type' => class_basename($log->subject_type),
            'subject_id' => $log->subject_id,
            'ip' => $log->ip ?? '-',
            'before' => $log->before,
            'after' => $log->after,
        ])->values()->all();

        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();

        $subjectTypes = AuditLog::query()
            ->select('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => class_basename($type),
            ])
            ->values()
            ->all();

        $users = User::query()
            ->whereIn('id', AuditLog::query()->whereNotNull('user_id')->select('user_id')->distinct())
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('admin/reports/audit', [
            'filters' => [
                'user_id' => $userId,
                'action' => $action,
                'subject_type' => $subjectType,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'logs' => [
                'data' => $logs,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'users' => $users,
            'actions' => $actions,
            'subjectTypes' => $subjectTypes,
        ]);
    }
}
