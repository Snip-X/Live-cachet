<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Filament\Pages\Auth;

use App\Auth\PasswordLoginGate;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;

class Login extends BaseLogin
{
    /**
     * Authenticate with e-mail and password, only from allowed IP addresses.
     */
    public function authenticate(): ?LoginResponse
    {
        if (! $this->isPasswordLoginAllowed()) {
            Notification::make()
                ->title(__('Password login is not available from your network.'))
                ->danger()
                ->send();

            return null;
        }

        return parent::authenticate();
    }

    public function content(Schema $schema): Schema
    {
        $passwordLoginAllowed = $this->isPasswordLoginAllowed();

        return $schema
            ->components(array_values(array_filter([
                RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE),
                config('sso.enabled') ? $this->getSsoActionsComponent() : null,
                $passwordLoginAllowed ? $this->getFormContentComponent() : null,
                $passwordLoginAllowed ? $this->getMultiFactorChallengeFormContentComponent() : null,
                $passwordLoginAllowed ? null : Text::make(__('Password login is not available from your network.')),
                RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER),
            ])));
    }

    protected function getSsoActionsComponent(): Actions
    {
        return Actions::make([
            Action::make('sso')
                ->label(config('sso.button_label'))
                ->url(route('sso.redirect'))
                ->color('primary'),
        ])
            ->fullWidth()
            ->key('sso-actions');
    }

    protected function isPasswordLoginAllowed(): bool
    {
        return app(PasswordLoginGate::class)->allows(request());
    }
}
