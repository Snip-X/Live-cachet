<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$list = fn (?string $value): array => array_values(array_filter(array_map('trim', explode(',', (string) $value))));

return [

    /*
    |--------------------------------------------------------------------------
    | OpenID Connect single sign-on
    |--------------------------------------------------------------------------
    |
    | Enables the "Sign in with SSO" button on the dashboard login page. The
    | identity provider itself is configured in config/services.php under
    | the "openidconnect" key (OIDC_* environment variables).
    |
    */

    'enabled' => (bool) env('SSO_ENABLED', false),

    'button_label' => env('SSO_BUTTON_LABEL', 'Sign in with SSO'),

    /*
    |--------------------------------------------------------------------------
    | Access policy
    |--------------------------------------------------------------------------
    |
    | Accounts are created on first SSO login. Restrict who may sign in with
    | a list of e-mail domains and/or IdP groups (comma separated). When both
    | lists are empty, SSO logins are refused unless "allow_any_user" is set.
    |
    | An existing account with the same e-mail is linked only when the IdP
    | marks the e-mail as verified, or when "trust_idp_email" is set for IdPs
    | that never send the "email_verified" claim (e.g. Microsoft Entra ID).
    |
    | When "admin_groups" is set, the admin flag of SSO users is synchronised
    | from these groups on every login. Otherwise new users are not admins
    | and existing users keep their current flag.
    |
    */

    'allowed_domains' => $list(env('SSO_ALLOWED_DOMAINS')),

    'allowed_groups' => $list(env('SSO_ALLOWED_GROUPS')),

    'allow_any_user' => (bool) env('SSO_ALLOW_ANY_USER', false),

    'trust_idp_email' => (bool) env('SSO_TRUST_IDP_EMAIL', false),

    'admin_groups' => $list(env('SSO_ADMIN_GROUPS')),

    'groups_claim' => env('SSO_GROUPS_CLAIM', 'groups'),

    /*
    |--------------------------------------------------------------------------
    | Password login (break-glass access)
    |--------------------------------------------------------------------------
    |
    | IP addresses or CIDR ranges (comma separated) allowed to use the e-mail
    | and password form. When SSO is enabled and this list is empty, password
    | login is refused from everywhere. When SSO is disabled, an empty list
    | means no restriction.
    |
    */

    'password_login_allowed_ips' => $list(env('SSO_PASSWORD_LOGIN_ALLOWED_IPS')),

];
