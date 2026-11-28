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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
     * @param  array<string, mixed>  $profile
     * @return array{user: User, password: string}
     */
    public function create(array $data, User $actor, array $profile = [], ?string $password = null, ?UploadedFile $photo = null): array
    {
        // Admin boleh menetapkan kata sandi seperti di aplikasi lama; bila kosong, dibuat acak dan ditampilkan sekali.
        $password ??= InitialPassword::generate();

        $user = DB::transaction(function () use ($data, $password, $actor, $profile, $photo): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'username' => $data['username'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'password' => $password,
                'is_active' => true,
            ]);

            $user->syncRoles($data['roles']);
            $this->syncProfile($user, $data, $profile, $photo);

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
     * @param  array<string, mixed>  $profile
     */
    public function update(User $user, array $data, User $actor, array $profile = [], ?string $password = null, ?UploadedFile $photo = null): User
    {
        return DB::transaction(function () use ($user, $data, $actor, $profile, $password, $photo): User {
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
            $this->syncProfile($user, $data, $profile, $photo);

            if ($password !== null) {
                $user->forceFill(['password' => $password])->save();
            }

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
     * @param  array<string, mixed>  $profile
     */
    private function syncProfile(User $user, array $data, array $profile = [], ?UploadedFile $photo = null): void
    {
        $values = [
            'unit_id' => $data['unit_id'] ?? null,
            'nip' => $data['nip'] ?? null,
            'position' => $data['position'] ?? null,
            ...$profile,
        ];

        $existing = StaffProfile::query()->where('user_id', $user->id)->first();
        $hasContent = $photo !== null || array_filter($values, static fn (mixed $v): bool => $v !== null && $v !== 0 && $v !== '') !== [];

        if (! $hasContent && $existing === null) {
            return;
        }

        if ($photo !== null) {
            // Foto pegawai disimpan di disk privat dan hanya dibuka lewat rute berizin.
            $values['photo_path'] = $photo->store('staff-photos', 'local');

            if ($existing?->photo_path !== null) {
                Storage::disk('local')->delete($existing->photo_path);
            }
        }

        StaffProfile::query()->updateOrCreate(['user_id' => $user->id], $values);

        $user->unsetRelation('staffProfile');
    }

    /**
     * Hapus akun pegawai (soft delete, seperti tombol Hapus aplikasi lama). Riwayat transaksi dan audit tetap utuh.
     */
    public function delete(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw StaffUserGuardException::cannotDeleteSelf();
        }

        $user->loadMissing('staffProfile');

        DB::transaction(function () use ($user, $actor): void {
            if ($user->hasRole(Role::Admin->value)) {
                $this->ensureNotLastActiveAdmin($user);
            }

            $before = $this->snapshot($user, array_values(array_intersect($user->getRoleNames()->all(), self::staffRoleValues())));
            $user->forceFill(['is_active' => false])->save();
            $user->delete();

            $this->auditLogger->record(action: 'deleted', subject: $user, before: $before, userId: $actor->id);
        });
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
            'assignment_type' => $profile?->assignment_type?->value,
            'employment_status' => $profile?->employment_status?->value,
            'base_salary' => $profile?->base_salary,
        ];
    }
}
