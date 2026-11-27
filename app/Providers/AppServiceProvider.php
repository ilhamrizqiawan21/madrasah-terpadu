<?php

declare(strict_types=1);

namespace App\Providers;

use App\Notifications\Channels\WhatsAppChannel;
use App\Services\Settings\SettingsRepository;
use App\Support\Branding;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
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
        $this->configureDefaults();

        // Kanal WhatsApp cadangan no-op (ADR-005, T-404): terdaftar tetapi tidak dipakai di via().
        Notification::extend('whatsapp', static fn (): WhatsAppChannel => new WhatsAppChannel);

        // Favicon & warna utama unggahan (T-201/T-208) dipasang di root view.
        View::composer('app', function (ViewContract $view): void {
            $identity = app(SettingsRepository::class)->identity();
            $view->with('brandingFaviconUrl', Branding::assetUrl('favicon', $identity['favicon_path']));
            $view->with('brandingPrimaryColor', $identity['primary_color']);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Strict mode catches N+1 queries, silently dropped attributes, and
        // typos in attribute names during development and tests. Production
        // stays lenient so a missed eager load degrades instead of crashing.
        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
