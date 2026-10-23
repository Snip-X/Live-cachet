<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use App\Auth\PasswordLoginGate;
use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

function requestFrom(string $ip): Request
{
    return Request::create('/', 'GET', server: ['REMOTE_ADDR' => $ip]);
}

it('replaces the dashboard login page', function () {
    expect(Route::getRoutes()->getByName('filament.cachet.auth.login')->getAction('uses'))
        ->toBe(Login::class);
});

it('allows password login from anywhere when SSO is disabled and no IPs are configured', function () {
    config(['sso.enabled' => false, 'sso.password_login_allowed_ips' => []]);

    expect(app(PasswordLoginGate::class)->allows(requestFrom('203.0.113.5')))->toBeTrue();
});

it('refuses password login from everywhere when SSO is enabled and no IPs are configured', function () {
    config(['sso.enabled' => true, 'sso.password_login_allowed_ips' => []]);

    expect(app(PasswordLoginGate::class)->allows(requestFrom('127.0.0.1')))->toBeFalse();
});

it('allows password login only from configured IPs and ranges', function () {
    config(['sso.enabled' => true, 'sso.password_login_allowed_ips' => ['10.0.0.0/8', '192.0.2.10']]);

    $gate = app(PasswordLoginGate::class);

    expect($gate->allows(requestFrom('10.20.30.40')))->toBeTrue()
        ->and($gate->allows(requestFrom('192.0.2.10')))->toBeTrue()
        ->and($gate->allows(requestFrom('203.0.113.5')))->toBeFalse();
});

it('shows the SSO button and the password form from an allowed IP', function () {
    config(['sso.enabled' => true, 'sso.password_login_allowed_ips' => ['127.0.0.1']]);

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->get('/dashboard/login')
        ->assertOk()
        ->assertSee(route('sso.redirect'), escape: false)
        ->assertSee('data.password', escape: false);
});

it('hides the password form from a blocked IP', function () {
    config(['sso.enabled' => true, 'sso.password_login_allowed_ips' => ['127.0.0.1']]);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
        ->get('/dashboard/login')
        ->assertOk()
        ->assertSee(route('sso.redirect'), escape: false)
        ->assertDontSee('data.password', escape: false);
});

it('refuses password authentication from a blocked IP', function () {
    Filament::setCurrentPanel(Filament::getPanel('cachet'));

    $this->mock(PasswordLoginGate::class)->shouldReceive('allows')->andReturnFalse();

    User::forceCreate([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
        'is_admin' => true,
    ]);

    Livewire::test(Login::class)
        ->fillForm(['email' => 'admin@example.com', 'password' => 'password'])
        ->call('authenticate')
        ->assertNotified();

    $this->assertGuest();
});

it('accepts password authentication from an allowed IP', function () {
    Filament::setCurrentPanel(Filament::getPanel('cachet'));

    $this->mock(PasswordLoginGate::class)->shouldReceive('allows')->andReturnTrue();

    $user = User::forceCreate([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
        'is_admin' => true,
    ]);

    Livewire::test(Login::class)
        ->fillForm(['email' => 'admin@example.com', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});
