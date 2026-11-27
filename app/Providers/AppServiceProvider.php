<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Settings\SettingsRepository;
use App\Support\Branding;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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

        // Favicon unggahan (T-201) dipasang di root view; null = favicon bawaan di public/.
        View::composer('app', function (ViewContract $view): void {
            $path = app(SettingsRepository::class)->identity()['favicon_path'];
            $view->with('brandingFaviconUrl', Branding::assetUrl('favicon', $path));
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
