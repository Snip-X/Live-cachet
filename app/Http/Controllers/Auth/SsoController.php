<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Http\Controllers\Auth;

use App\Auth\SsoAccessDeniedException;
use App\Auth\SsoUserResolver;
use App\Http\Controllers\Controller;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class SsoController extends Controller
{
    /**
     * Redirect the user to the identity provider.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        abort_unless(config('sso.enabled'), 404);

        try {
            return $this->driver()->redirect();
        } catch (Throwable $exception) {
            Log::error('SSO redirect failed, check the OIDC_* configuration.', ['exception' => $exception]);

            return $this->failed(__('Single sign-on failed. Please try again.'));
        }
    }

    /**
     * Handle the identity provider callback and sign the user in.
     */
    public function callback(Request $request, SsoUserResolver $resolver): RedirectResponse
    {
        abort_unless(config('sso.enabled'), 404);

        try {
            $user = $resolver->resolve($this->driver()->user());
        } catch (SsoAccessDeniedException $exception) {
            Log::warning('SSO login refused: '.$exception->getMessage());

            return $this->failed(__('Your account is not allowed to access the dashboard.'));
        } catch (Throwable $exception) {
            Log::error('SSO login failed.', ['exception' => $exception]);

            return $this->failed(__('Single sign-on failed. Please try again.'));
        }

        $panel = Filament::getPanel('cachet');

        if ($user instanceof FilamentUser && ! $user->canAccessPanel($panel)) {
            return $this->failed(__('Your account is not allowed to access the dashboard.'));
        }

        $panel->auth()->login($user);

        $request->session()->regenerate();

        return redirect()->intended($panel->getUrl());
    }

    /**
     * Get the OpenID Connect driver, with the callback URL defaulting to this application's route.
     */
    protected function driver(): Provider
    {
        return Socialite::driver('openidconnect')
            ->redirectUrl(config('services.openidconnect.redirect') ?: route('sso.callback'));
    }

    /**
     * Send the user back to the login page with an error notification.
     */
    protected function failed(string $message): RedirectResponse
    {
        Notification::make()
            ->title($message)
            ->danger()
            ->send();

        return redirect()->route('filament.cachet.auth.login');
    }
}
