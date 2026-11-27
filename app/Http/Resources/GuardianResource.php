<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\Users\StaffUserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Membutuhkan relasi `user.roles` dan `students.enrollments.classroom`
 * (enrollment sudah disaring ke tahun ajaran yang dilihat).
 *
 * @mixin Guardian
 */
class GuardianResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $staffRoles = StaffUserService::staffRoleValues();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'username' => $this->user->username,
            'relation' => ['value' => $this->relation->value, 'label' => $this->relation->label()],
            'address' => $this->address,
            'is_active' => $this->user->is_active,
            // Akun staf yang juga wali: kata sandi & status dikelola di Pengguna staf.
            'is_staff_account' => $this->user->roles->pluck('name')->intersect($staffRoles)->isNotEmpty(),
            'children' => $this->students->map(function (Student $student): array {
                /** @var Enrollment|null $enrollment */
                $enrollment = $student->enrollments->first();

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'nis' => $student->nis,
                    'classroom' => $enrollment?->classroom->name,
                    'is_primary' => (bool) $student->getRelationValue('link')?->getAttribute('is_primary'),
                ];
            })->values()->all(),
        ];
    }
}
