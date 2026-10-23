<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Providers;

use App\Filament\Pages\Auth\Login;
use Filament\PanelRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Finder\Finder;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerLoginPage();
    }

    /**
     * Replace the dashboard login page with the SSO-aware one.
     *
     * The Cachet panel is registered by the (auto-discovered) Cachet providers before
     * this one, and Filament registers its Livewire components as soon as the panel is,
     * so the page must also be registered with Livewire here.
     */
    protected function registerLoginPage(): void
    {
        $this->app->resolving(PanelRegistry::class, function (PanelRegistry $registry): void {
            $panel = $registry->get('cachet', isStrict: false);

            if ($panel === null || ! $panel->hasLogin()) {
                return;
            }

            $panel->login(Login::class);

            [, $name] = app(Finder::class)->parseNamespaceAndName(Login::class);

            Livewire::component($name, Login::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //

        $this->bootRoute();
    }

    public function bootRoute(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
