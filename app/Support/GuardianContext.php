<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Feature;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\User;
use App\Services\Features\FeatureFlags;
use Illuminate\Contracts\Session\Session;

/**
 * Anak terhubung & anak yang sedang dipilih wali di sesi (T-301).
 */
final class GuardianContext
{
    public const SESSION_STUDENT = 'guardian_active_student_id';

    /**
     * @return array{
     *     children: list<array{id: int, nis: string, name: string, nickname: ?string, unit: string, classroom: ?string, photo_url: ?string}>,
     *     activeChild: array{id: int, nis: string, name: string, nickname: ?string, unit: string, classroom: ?string, photo_url: ?string}|null,
     *     billingEnabled: bool,
     *     unreadNotifications: int
     * }
     */
    public static function resolve(User $user, Session $session): array
    {
        $guardian = $user->guardian;
        $billingEnabled = app(FeatureFlags::class)->enabled(Feature::Billing);
        $unreadCount = $user->unreadNotifications()->count();

        if ($guardian === null) {
            return [
                'children' => [],
                'activeChild' => null,
                'billingEnabled' => $billingEnabled,
                'unreadNotifications' => $unreadCount,
            ];
        }

        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');

        $students = $guardian->students()
            ->with([
                'unit',
                'enrollments' => fn ($q) => $activeYearId !== null
                    ? $q->where('academic_year_id', $activeYearId)->with('classroom')
                    : $q->whereRaw('1 = 0'),
            ])
            ->orderByDesc('guardian_student.is_primary')
            ->orderBy('students.name')
            ->orderBy('students.id')
            ->get();

        $children = [];

        foreach ($students as $student) {
            $children[] = self::formatChild($student);
        }

        $selectedId = $session->get(self::SESSION_STUDENT);
        $activeChild = null;

        if (is_int($selectedId)) {
            foreach ($children as $child) {
                if ($child['id'] === $selectedId) {
                    $activeChild = $child;

                    break;
                }
            }
        }

        $activeChild ??= $children[0] ?? null;

        return [
            'children' => $children,
            'activeChild' => $activeChild,
            'billingEnabled' => $billingEnabled,
            'unreadNotifications' => $unreadCount,
        ];
    }

    public static function activeStudent(User $user, Session $session): ?Student
    {
        $guardian = $user->guardian;

        if ($guardian === null) {
            return null;
        }

        $selectedId = $session->get(self::SESSION_STUDENT);

        if (is_int($selectedId)) {
            $student = $guardian->students()->whereKey($selectedId)->first();

            if ($student !== null) {
                return $student;
            }
        }

        return $guardian->students()
            ->orderByDesc('guardian_student.is_primary')
            ->orderBy('students.name')
            ->orderBy('students.id')
            ->first();
    }

    public static function select(User $user, Session $session, int $studentId): bool
    {
        $guardian = $user->guardian;

        if ($guardian === null || ! $guardian->students()->whereKey($studentId)->exists()) {
            return false;
        }

        $session->put(self::SESSION_STUDENT, $studentId);

        return true;
    }

    /**
     * @return array{id: int, nis: string, name: string, nickname: ?string, unit: string, classroom: ?string, photo_url: ?string}
     */
    private static function formatChild(Student $student): array
    {
        $classroom = $student->enrollments->first()?->classroom->name ?? null;

        return [
            'id' => $student->id,
            'nis' => $student->nis,
            'name' => $student->name,
            'nickname' => $student->nickname,
            'unit' => $student->unit->code,
            'classroom' => $classroom,
            'photo_url' => $student->photo_path !== null
                ? route('guardian.students.photo', ['student' => $student->id])
                : null,
        ];
    }
}
