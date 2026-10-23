<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use App\Models\User;
use Filament\Facades\Filament;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    config([
        'sso.enabled' => true,
        'sso.allowed_domains' => ['example.com'],
        'sso.allowed_groups' => [],
        'sso.allow_any_user' => false,
        'sso.trust_idp_email' => false,
        'sso.admin_groups' => [],
        'sso.groups_claim' => 'groups',
    ]);
});

/**
 * Fake the identity returned by the OpenID Connect provider.
 *
 * @param  array<string, mixed>  $claims
 */
function fakeSsoIdentity(array $claims): void
{
    $claims += ['sub' => 'subject-1', 'email' => 'jane@example.com', 'name' => 'Jane Doe'];

    $identity = (new SocialiteUser)->setRaw($claims)->map([
        'id' => $claims['sub'],
        'email' => $claims['email'],
        'name' => $claims['name'],
    ]);

    $provider = Mockery::mock(AbstractProvider::class);
    $provider->shouldReceive('redirectUrl')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($identity);

    Socialite::shouldReceive('driver')->with('openidconnect')->andReturn($provider);
}

function dashboardUrl(): string
{
    return Filament::getPanel('cachet')->getUrl();
}

it('redirects back to the login page when the OIDC configuration is invalid', function () {
    $provider = Mockery::mock(AbstractProvider::class);
    $provider->shouldReceive('redirectUrl')->andReturnSelf();
    $provider->shouldReceive('redirect')->andThrow(new InvalidArgumentException('OIDC: base_url is required.'));

    Socialite::shouldReceive('driver')->with('openidconnect')->andReturn($provider);

    $this->get(route('sso.redirect'))->assertRedirect(route('filament.cachet.auth.login'));
});

it('returns 404 when SSO is disabled', function () {
    config(['sso.enabled' => false]);

    $this->get(route('sso.redirect'))->assertNotFound();
    $this->get(route('sso.callback'))->assertNotFound();
});

it('creates a non admin user on first login and signs them in', function () {
    fakeSsoIdentity([]);

    $this->get(route('sso.callback'))->assertRedirect(dashboardUrl());

    $user = User::where('email', 'jane@example.com')->sole();

    expect($user->oidc_sub)->toBe('subject-1')
        ->and($user->name)->toBe('Jane Doe')
        ->and($user->is_admin)->toBeFalse()
        ->and($user->email_verified_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
});

it('links an existing account with the same e-mail', function () {
    $existing = User::forceCreate([
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'password' => 'password',
        'is_admin' => true,
    ]);

    fakeSsoIdentity(['email_verified' => true]);

    $this->get(route('sso.callback'))->assertRedirect(dashboardUrl());

    expect($existing->fresh()->oidc_sub)->toBe('subject-1')
        ->and($existing->fresh()->is_admin)->toBeTrue()
        ->and(User::count())->toBe(1);

    $this->assertAuthenticatedAs($existing);
});

it('refuses to link an existing account to an unverified e-mail', function () {
    User::forceCreate([
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'password' => 'password',
        'is_admin' => true,
    ]);

    fakeSsoIdentity(['email_verified' => false]);

    $this->get(route('sso.callback'))->assertRedirect(route('filament.cachet.auth.login'));

    $this->assertGuest();
});

it('refuses to link an existing account when the IdP does not say the e-mail is verified', function () {
    User::forceCreate([
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'password' => 'password',
        'is_admin' => true,
    ]);

    fakeSsoIdentity([]);

    $this->get(route('sso.callback'))->assertRedirect(route('filament.cachet.auth.login'));

    $this->assertGuest();
    expect(User::sole()->oidc_sub)->toBeNull();
});

it('links an existing account without email_verified when the IdP e-mail is trusted', function () {
    config(['sso.trust_idp_email' => true]);

    $existing = User::forceCreate([
        'name' => 'Jane',
        'email' => 'Jane@Example.com',
        'password' => 'password',
    ]);

    fakeSsoIdentity([]);

    $this->get(route('sso.callback'))->assertRedirect(dashboardUrl());

    expect($existing->fresh()->oidc_sub)->toBe('subject-1')
        ->and(User::count())->toBe(1);
});

it('refuses an e-mail already linked to another SSO identity', function () {
    User::forceCreate([
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'password' => 'password',
        'oidc_sub' => 'another-subject',
    ]);

    fakeSsoIdentity(['email_verified' => true]);

    $this->get(route('sso.callback'))->assertRedirect(route('filament.cachet.auth.login'));

    $this->assertGuest();
});

it('refuses every SSO login when no access policy is configured', function () {
    config(['sso.allowed_domains' => [], 'sso.allowed_groups' => []]);

    fakeSsoIdentity([]);

    $this->get(route('sso.callback'))->assertRedirect(route('filament.cachet.auth.login'));

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('accepts any IdP user when explicitly allowed', function () {
    config(['sso.allowed_domains' => [], 'sso.allowed_groups' => [], 'sso.allow_any_user' => true]);

    fakeSsoIdentity(['email' => 'someone@elsewhere.example']);

    $this->get(route('sso.callback'))->assertRedirect(dashboardUrl());

    expect(User::count())->toBe(1);
});

it('finds the user by subject when the e-mail changed at the IdP', function () {
    $user = User::forceCreate([
        'name' => 'Jane',
        'email' => 'jane.old@example.com',
        'password' => 'password',
        'oidc_sub' => 'subject-1',
    ]);

    fakeSsoIdentity(['email' => 'jane@example.com', 'email_verified' => true]);

    $this->get(route('sso.callback'))->assertRedirect(dashboardUrl());

    expect($user->fresh()->email)->toBe('jane@example.com')
        ->and(User::count())->toBe(1);

    $this->assertAuthenticatedAs($user);
});

it('refuses users outside the allowed domains', function () {
    config(['sso.allowed_domains' => ['corp.example']]);

    fakeSsoIdentity([]);

    $this->get(route('sso.callback'))->assertRedirect(route('filament.cachet.auth.login'));

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

it('refuses users outside the allowed groups', function () {
    config(['sso.allowed_groups' => ['status-editors']]);

    fakeSsoIdentity(['groups' => ['other']]);

    $this->get(route('sso.callback'))->assertRedirect(route('filament.cachet.auth.login'));

    $this->assertGuest();
});

it('grants admin from the configured admin groups', function () {
    config(['sso.admin_groups' => ['status-admins']]);

    fakeSsoIdentity(['groups' => ['status-admins']]);

    $this->get(route('sso.callback'))->assertRedirect(dashboardUrl());

    expect(User::where('email', 'jane@example.com')->sole()->is_admin)->toBeTrue();
});

it('reads groups from a namespaced claim', function () {
    config(['sso.admin_groups' => ['status-admins'], 'sso.groups_claim' => 'https://example.com/groups']);

    fakeSsoIdentity(['https://example.com/groups' => ['status-admins']]);

    $this->get(route('sso.callback'))->assertRedirect(dashboardUrl());

    expect(User::where('email', 'jane@example.com')->sole()->is_admin)->toBeTrue();
});

it('revokes admin when the user left the admin groups, except for the last admin', function () {
    config(['sso.admin_groups' => ['status-admins']]);

    $otherAdmin = User::forceCreate(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'password', 'is_admin' => true]);
    $jane = User::forceCreate(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'password', 'is_admin' => true, 'oidc_sub' => 'subject-1']);

    fakeSsoIdentity(['groups' => []]);
    $this->get(route('sso.callback'));

    expect($jane->fresh()->is_admin)->toBeFalse();

    auth()->logout();
    $otherAdmin->forceFill(['is_admin' => false])->save();
    $jane->forceFill(['is_admin' => true])->save();

    $this->get(route('sso.callback'));

    expect($jane->fresh()->is_admin)->toBeTrue();
});

it('keeps the admin flag when the groups claim is missing', function () {
    config(['sso.admin_groups' => ['status-admins']]);

    User::forceCreate(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'password', 'is_admin' => true]);
    $jane = User::forceCreate(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'password', 'is_admin' => true, 'oidc_sub' => 'subject-1']);

    fakeSsoIdentity([]);

    $this->get(route('sso.callback'))->assertRedirect(dashboardUrl());

    expect($jane->fresh()->is_admin)->toBeTrue();
});

it('redirects back to the login page when the IdP exchange fails', function () {
    $provider = Mockery::mock(AbstractProvider::class);
    $provider->shouldReceive('redirectUrl')->andReturnSelf();
    $provider->shouldReceive('user')->andThrow(new InvalidArgumentException('Callback: invalid state.'));

    Socialite::shouldReceive('driver')->with('openidconnect')->andReturn($provider);

    $this->get(route('sso.callback'))->assertRedirect(route('filament.cachet.auth.login'));

    $this->assertGuest();
});
