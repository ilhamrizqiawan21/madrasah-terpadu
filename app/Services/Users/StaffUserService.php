<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\Role;
use App\Exceptions\StaffUserGuardException;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\InitialPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Satu-satunya penulis akun staf (T-114). Akun wali dikelola di modul wali (T-206).
 */
final class StaffUserService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Akun yang dikelola di halaman pengguna staf: punya peran staf, atau belum punya peran
     * sama sekali (agar admin bisa memperbaikinya). Akun wali murni tidak termasuk.
     *
     * @return Builder<User>
     */
    public static function staffQuery(): Builder
    {
        $staffRoles = self::staffRoleValues();

        return User::query()->where(function (Builder $query) use ($staffRoles): void {
            $query->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', $staffRoles))
                ->orWhereDoesntHave('roles');
        });
    }

    /**
     * @return list<string>
     */
    public static function staffRoleValues(): array
    {
        return array_map(fn (Role $role): string => $role->value, Role::staff());
    }

    /**
     * @param  array{name: string, username: string, phone: ?string, email: ?string, roles: list<string>, unit_id?: ?int, nip?: ?string, position?: ?string}  $data
     * @return array{user: User, password: string}
     */
    public function create(array $data, User $actor): array
    {
        $password = InitialPassword::generate();

        $user = DB::transaction(function () use ($data, $password, $actor): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'username' => $data['username'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'password' => $password,
                'is_active' => true,
            ]);

            $user->syncRoles($data['roles']);
            $this->syncProfile($user, $data);

            $this->auditLogger->record(
                action: 'created',
                subject: $user,
                after: $this->snapshot($user, $data['roles']),
                userId: $actor->id,
            );

            return $user;
        });

        return ['user' => $user, 'password' => $password];
    }

    /**
     * @param  array{name: string, username: string, phone: ?string, email: ?string, roles: list<string>, unit_id?: ?int, nip?: ?string, position?: ?string}  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor): User {
            /** @var list<string> $currentRoles */
            $currentRoles = $user->getRoleNames()->values()->all();
            $before = $this->snapshot($user, array_values(array_intersect($currentRoles, self::staffRoleValues())));

            $removesAdmin = in_array(Role::Admin->value, $currentRoles, true)
                && ! in_array(Role::Admin->value, $data['roles'], true);

            if ($removesAdmin) {
                if ($user->is($actor)) {
                    throw StaffUserGuardException::cannotRemoveOwnAdminRole();
                }

                $this->ensureNotLastActiveAdmin($user);
            }

            $user->fill([
                'name' => $data['name'],
                'username' => $data['username'],
                'phone' => $data['phone'],
                'email' => $data['email'],
            ])->save();

            // Peran non-staf (mis. guru yang juga wali murid) dipertahankan.
            $nonStaffRoles = array_values(array_diff($currentRoles, self::staffRoleValues()));
            $user->syncRoles([...$data['roles'], ...$nonStaffRoles]);
            $this->syncProfile($user, $data);

            $this->auditLogger->record(
                action: 'updated',
                subject: $user,
                before: $before,
                after: $this->snapshot($user, $data['roles']),
                userId: $actor->id,
            );

            return $user;
        });
    }

    public function setActive(User $user, bool $active, User $actor): User
    {
        if ($user->is_active === $active) {
            return $user;
        }

        return DB::transaction(function () use ($user, $active, $actor): User {
            if (! $active) {
                if ($user->is($actor)) {
                    throw StaffUserGuardException::cannotDeactivateSelf();
                }

                if ($user->hasRole(Role::Admin->value)) {
                    $this->ensureNotLastActiveAdmin($user);
                }
            }

            $user->forceFill(['is_active' => $active])->save();

            $this->auditLogger->record(
                action: $active ? 'activated' : 'deactivated',
                subject: $user,
                before: ['is_active' => ! $active],
                after: ['is_active' => $active],
                userId: $actor->id,
            );

            return $user;
        });
    }

    /**
     * Ganti kata sandi dengan yang acak; nilainya hanya dikembalikan ke pemanggil
     * untuk ditampilkan sekali, tidak pernah masuk audit atau log.
     */
    public function resetPassword(User $user, User $actor): string
    {
        $password = InitialPassword::generate();

        DB::transaction(function () use ($user, $password, $actor): void {
            // Rotasi remember_token agar cookie "ingat saya" lama tidak bisa dipakai lagi.
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            $this->auditLogger->record(
                action: 'password_reset',
                subject: $user,
                userId: $actor->id,
            );
        });

        return $password;
    }

    /**
     * Kunci baris admin aktif agar dua admin yang saling menonaktifkan bersamaan
     * tidak menyisakan nol admin.
     */
    private function ensureNotLastActiveAdmin(User $user): void
    {
        $activeAdminIds = User::query()
            ->role(Role::Admin->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        if ($activeAdminIds->count() <= 1 && $activeAdminIds->contains($user->id)) {
            throw StaffUserGuardException::lastActiveAdmin();
        }
    }

    /**
     * @param  array{unit_id?: ?int, nip?: ?string, position?: ?string}  $data
     */
    private function syncProfile(User $user, array $data): void
    {
        $unitId = $data['unit_id'] ?? null;
        $nip = $data['nip'] ?? null;
        $position = $data['position'] ?? null;

        if ($unitId === null && $nip === null && $position === null && ! $user->staffProfile()->exists()) {
            return;
        }

        StaffProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'unit_id' => $unitId,
                'nip' => $nip,
                'position' => $position,
            ],
        );

        $user->unsetRelation('staffProfile');
    }

    /**
     * @param  list<string>  $roles
     * @return array<string, mixed>
     */
    private function snapshot(User $user, array $roles): array
    {
        $profile = $user->staffProfile;

        return [
            'name' => $user->name,
            'username' => $user->username,
            'phone' => $user->phone,
            'email' => $user->email,
            'roles' => $roles,
            'unit_id' => $profile?->unit_id,
            'nip' => $profile?->nip,
            'position' => $profile?->position,
        ];
    }
}
