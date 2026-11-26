<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin
        {--name= : Nama lengkap admin}
        {--username= : Username login}
        {--password= : Kata sandi (minimal 12 karakter)}
        {--email= : Alamat email opsional}';

    protected $description = 'Buat akun administrator baru untuk Madrasah Terpadu';

    public function handle(AuditLogger $auditLogger): int
    {
        $name = trim((string) ($this->option('name') ?? $this->ask('Nama lengkap')));
        $username = Str::lower(trim((string) ($this->option('username') ?? $this->ask('Username'))));
        $password = (string) ($this->option('password') ?? $this->secret('Kata sandi (minimal 12 karakter)'));
        $emailOption = $this->option('email');
        $email = is_string($emailOption) && trim($emailOption) !== ''
            ? Str::lower(trim($emailOption))
            : null;

        if ($name === '' || $username === '') {
            $this->error('Nama lengkap dan username wajib diisi.');

            return self::FAILURE;
        }

        if (mb_strlen($password) < 12) {
            $this->error('Kata sandi minimal 12 karakter.');

            return self::FAILURE;
        }

        if (User::withTrashed()->where('username', $username)->exists()) {
            $this->error("Username [{$username}] sudah terdaftar.");

            return self::FAILURE;
        }

        if ($email !== null && User::withTrashed()->where('email', $email)->exists()) {
            $this->error("Email [{$email}] sudah terdaftar.");

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($name, $username, $email, $password, $auditLogger): User {
            Role::findOrCreate(RoleEnum::Admin->value, 'web');

            $user = User::query()->create([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'is_active' => true,
            ]);

            if ($email !== null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $user->assignRole(RoleEnum::Admin->value);

            $auditLogger->record(
                action: 'created',
                subject: $user,
                after: [
                    'name' => $user->name,
                    'username' => $user->username,
                    'role' => RoleEnum::Admin->value,
                ],
                userId: $user->id,
            );

            return $user;
        });

        $this->info("Akun admin [{$user->username}] berhasil dibuat.");

        return self::SUCCESS;
    }
}
