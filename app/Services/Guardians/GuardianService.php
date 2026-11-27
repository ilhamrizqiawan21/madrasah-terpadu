<?php

declare(strict_types=1);

namespace App\Services\Guardians;

use App\Enums\GuardianRelation;
use App\Enums\Role;
use App\Exceptions\GuardianAccountException;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Users\StaffUserService;
use App\Support\InitialPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Satu-satunya penulis wali dan hubungan wali–anak (T-206).
 *
 * Akun login wali: username = nomor HP ternormalisasi, kata sandi awal acak yang
 * hanya dikembalikan ke pemanggil untuk ditampilkan/dicetak sekali (tidak pernah
 * masuk audit atau log). Tanpa paksa ganti kata sandi (ADR-015).
 *
 * Nomor HP yang sudah dipakai akun lain tidak membuat akun ganda:
 * - akun itu sudah wali → anak ditautkan ke wali tersebut;
 * - akun staf (mis. guru yang juga orang tua) → akun itu diberi peran wali dan
 *   tetap memakai kata sandinya sendiri.
 *
 * Tepat satu wali utama per siswa dijaga di sini dengan mengunci semua baris
 * hubungan siswa yang tersentuh.
 *
 * @phpstan-type ChildLink array{student_id: int, is_primary: bool}
 * @phpstan-type GuardianData array{
 *     name: string,
 *     phone: string,
 *     relation: GuardianRelation,
 *     address: string|null,
 *     children: list<ChildLink>
 * }
 */
final class GuardianService
{
    public const OUTCOME_CREATED = 'created';

    public const OUTCOME_LINKED_EXISTING = 'linked_existing';

    public const OUTCOME_ATTACHED_STAFF = 'attached_staff';

    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  GuardianData  $data
     * @return array{guardian: Guardian, password: string|null, outcome: string}
     */
    public function create(array $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor): array {
            $existing = User::withTrashed()
                ->where(fn (Builder $query) => $query->where('phone', $data['phone'])->orWhere('username', $data['phone']))
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($existing?->trashed()) {
                throw GuardianAccountException::phoneHeldByArchivedAccount();
            }

            $guardian = $existing?->guardian;

            if ($guardian !== null) {
                $before = $this->snapshot($guardian);
                $this->applyChildren($guardian, $data['children'], $data['relation'], detachMissing: false);

                $this->auditLogger->record(
                    action: 'children_linked',
                    subject: $guardian,
                    before: $before,
                    after: $this->snapshot($guardian),
                    userId: $actor->id,
                );

                return ['guardian' => $guardian, 'password' => null, 'outcome' => self::OUTCOME_LINKED_EXISTING];
            }

            $password = null;
            $user = $existing;

            if ($user === null) {
                $password = InitialPassword::generate();
                $user = User::query()->create([
                    'name' => $data['name'],
                    'username' => $data['phone'],
                    'phone' => $data['phone'],
                    'password' => $password,
                    'is_active' => true,
                ]);
            }

            $user->assignRole(Role::Guardian->value);

            $guardian = Guardian::query()->create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'relation' => $data['relation'],
                'address' => $data['address'],
            ]);

            $this->applyChildren($guardian, $data['children'], $data['relation'], detachMissing: false);

            $this->auditLogger->record(
                action: 'created',
                subject: $guardian,
                after: [...$this->snapshot($guardian), 'shared_account' => $password === null],
                userId: $actor->id,
            );

            return [
                'guardian' => $guardian,
                'password' => $password,
                'outcome' => $password === null ? self::OUTCOME_ATTACHED_STAFF : self::OUTCOME_CREATED,
            ];
        });
    }

    /**
     * Daftar anak yang dikirim menggantikan seluruh hubungan wali ini.
     *
     * @param  GuardianData  $data
     */
    public function update(Guardian $guardian, array $data, User $actor): Guardian
    {
        return DB::transaction(function () use ($guardian, $data, $actor): Guardian {
            $before = $this->snapshot($guardian);
            $user = $guardian->user;

            $guardian->fill([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'relation' => $data['relation'],
                'address' => $data['address'],
            ])->save();

            if ($this->isSharedStaffAccount($user)) {
                // Akun staf: username & nama tetap milik halaman Pengguna staf; nomor HP ikut agar login HP tetap jalan.
                $user->fill(['phone' => $data['phone']])->save();
            } else {
                $user->fill([
                    'name' => $data['name'],
                    'username' => $data['phone'],
                    'phone' => $data['phone'],
                ])->save();
            }

            $this->applyChildren($guardian, $data['children'], $data['relation'], detachMissing: true);

            $after = $this->snapshot($guardian);

            if ($after !== $before) {
                $this->auditLogger->record(
                    action: 'updated',
                    subject: $guardian,
                    before: $before,
                    after: $after,
                    userId: $actor->id,
                );
            }

            return $guardian;
        });
    }

    public function setActive(Guardian $guardian, bool $active, User $actor): Guardian
    {
        $user = $guardian->user;

        if ($this->isSharedStaffAccount($user)) {
            throw GuardianAccountException::sharedStaffAccount();
        }

        if ($user->is_active === $active) {
            return $guardian;
        }

        return DB::transaction(function () use ($guardian, $user, $active, $actor): Guardian {
            $user->forceFill(['is_active' => $active])->save();

            $this->auditLogger->record(
                action: $active ? 'activated' : 'deactivated',
                subject: $guardian,
                before: ['is_active' => ! $active],
                after: ['is_active' => $active],
                userId: $actor->id,
            );

            return $guardian;
        });
    }

    /**
     * Kata sandi acak baru; nilainya hanya dikembalikan untuk dicetak sekali.
     * Mencetak ulang surat akun selalu lewat reset karena kata sandi tidak bisa dibaca lagi.
     */
    public function resetPassword(Guardian $guardian, User $actor): string
    {
        $user = $guardian->user;

        if ($this->isSharedStaffAccount($user)) {
            throw GuardianAccountException::sharedStaffAccount();
        }

        $password = InitialPassword::generate();

        DB::transaction(function () use ($guardian, $user, $password, $actor): void {
            // Rotasi remember_token agar cookie "ingat saya" lama tidak bisa dipakai lagi.
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            $this->auditLogger->record(
                action: 'password_reset',
                subject: $guardian,
                userId: $actor->id,
            );
        });

        return $password;
    }

    public function isSharedStaffAccount(User $user): bool
    {
        return $user->hasAnyRole(StaffUserService::staffRoleValues());
    }

    /**
     * @param  list<ChildLink>  $children
     */
    private function applyChildren(Guardian $guardian, array $children, GuardianRelation $relation, bool $detachMissing): void
    {
        $requested = collect($children)->keyBy('student_id');
        $requestedIds = collect($children)->map(fn (array $child): int => $child['student_id']);

        $currentIds = GuardianStudent::query()
            ->where('guardian_id', $guardian->id)
            ->get(['student_id'])
            ->toBase()
            ->map(fn (GuardianStudent $link): int => $link->student_id);

        $affected = $currentIds->merge($requestedIds)->unique()->sort()->values();

        // Kunci semua hubungan siswa yang tersentuh, urut id, agar dua admin tidak menghasilkan dua wali utama.
        GuardianStudent::query()
            ->whereIn('student_id', $affected)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);

        if ($detachMissing) {
            $removed = $currentIds->diff($requestedIds)->values();

            if ($removed->isNotEmpty()) {
                $guardian->students()->detach($removed->all());
            }
        }

        foreach ($requested as $studentId => $child) {
            if ($currentIds->contains($studentId)) {
                $guardian->students()->updateExistingPivot($studentId, ['relation' => $relation->value]);
            } else {
                $guardian->students()->attach($studentId, ['relation' => $relation->value, 'is_primary' => false]);
            }

            if ($child['is_primary']) {
                GuardianStudent::query()
                    ->where('student_id', $studentId)
                    ->where('guardian_id', '!=', $guardian->id)
                    ->update(['is_primary' => false]);
            }

            GuardianStudent::query()
                ->where('student_id', $studentId)
                ->where('guardian_id', $guardian->id)
                ->update(['is_primary' => $child['is_primary']]);
        }

        $this->ensureOnePrimary($affected->all(), $guardian->id);
        $guardian->unsetRelation('students');
    }

    /**
     * Siswa yang kehilangan wali utama (karena dilepas atau tanda utamanya dicabut)
     * mendapat wali utama baru: hubungan tertua milik wali lain, atau wali yang
     * sedang diubah bila hanya ia yang tersisa (siswa berwali selalu punya wali utama).
     *
     * @param  array<int, int>  $studentIds
     */
    private function ensureOnePrimary(array $studentIds, int $editedGuardianId): void
    {
        $links = GuardianStudent::query()
            ->whereIn('student_id', $studentIds)
            ->orderBy('id')
            ->get(['id', 'guardian_id', 'student_id', 'is_primary']);

        foreach ($links->groupBy('student_id') as $studentLinks) {
            $primaries = $studentLinks->where('is_primary', true);

            if ($primaries->count() === 1) {
                continue;
            }

            $keep = $primaries->first()
                ?? $studentLinks->firstWhere('guardian_id', '!=', $editedGuardianId)
                ?? $studentLinks->first();

            GuardianStudent::query()
                ->whereKey($studentLinks->pluck('id'))
                ->update(['is_primary' => false]);

            GuardianStudent::query()->whereKey($keep?->id)->update(['is_primary' => true]);
        }
    }

    /**
     * Alamat tidak disalin ke audit; kata sandi tidak pernah.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Guardian $guardian): array
    {
        $links = GuardianStudent::query()
            ->where('guardian_id', $guardian->id)
            ->orderBy('student_id')
            ->get(['student_id', 'is_primary']);

        return [
            'user_id' => $guardian->user_id,
            'name' => $guardian->name,
            'phone' => $guardian->phone,
            'relation' => $guardian->relation->value,
            'children' => $links->map(fn (GuardianStudent $link): array => [
                'student_id' => $link->student_id,
                'is_primary' => $link->is_primary,
            ])->all(),
        ];
    }
}
