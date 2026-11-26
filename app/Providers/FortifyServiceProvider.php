<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $identity = trim((string) $request->input(Fortify::username()));
            $password = (string) $request->input('password');

            if ($identity === '' || $password === '') {
                return null;
            }

            $user = $this->resolveUserByIdentity($identity);

            if ($user === null || ! $user->is_active || ! Hash::check($password, $user->password)) {
                $this->recordFailedLogin($request, $user, $identity);

                return null;
            }

            return $user;
        });

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->save();
            }
        });
    }

    private function resolveUserByIdentity(string $identity): ?User
    {
        $lowerIdentity = Str::lower($identity);

        $user = User::query()
            ->where('username', $lowerIdentity)
            ->orWhere('email', $lowerIdentity)
            ->first();

        if ($user !== null) {
            return $user;
        }

        $normalizedPhone = PhoneNumber::normalize($identity);

        if ($normalizedPhone !== null) {
            $user = User::query()
                ->where('phone', $normalizedPhone)
                ->first();

            if ($user !== null) {
                return $user;
            }
        }

        return $this->resolvePrimaryGuardianByNis($identity);
    }

    private function resolvePrimaryGuardianByNis(string $nis): ?User
    {
        if (
            ! Schema::hasTable('students')
            || ! Schema::hasTable('guardians')
            || ! Schema::hasTable('guardian_student')
        ) {
            return null;
        }

        $userId = DB::table('students')
            ->join('guardian_student', 'guardian_student.student_id', '=', 'students.id')
            ->join('guardians', 'guardians.id', '=', 'guardian_student.guardian_id')
            ->where('students.nis', $nis)
            ->orderByDesc('guardian_student.is_primary')
            ->value('guardians.user_id');

        if (! is_int($userId) && ! is_string($userId)) {
            return null;
        }

        return User::query()->find((int) $userId);
    }

    private function recordFailedLogin(Request $request, ?User $user, string $identity): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert([
            'user_id' => $user?->id,
            'action' => 'login_failed',
            'subject_type' => User::class,
            'subject_id' => $user?->id,
            'before' => null,
            'after' => json_encode(['identity' => $identity], JSON_THROW_ON_ERROR),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'created_at' => now(),
        ]);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
