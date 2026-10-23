<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openidconnect' => [
        // Optional shorthand: entra, keycloak, okta, auth0 or google. Leave empty for any other IdP.
        // Empty values must be null so a provider's derived defaults apply.
        'provider' => env('OIDC_PROVIDER') ?: null,
        'base_url' => env('OIDC_BASE_URL') ?: null,
        'tenant' => env('OIDC_TENANT') ?: null,
        'server_url' => env('OIDC_SERVER_URL') ?: null,
        'realm' => env('OIDC_REALM') ?: null,
        'domain' => env('OIDC_DOMAIN') ?: null,
        'client_id' => env('OIDC_CLIENT_ID'),
        'client_secret' => env('OIDC_CLIENT_SECRET'),
        // Defaults to the absolute URL of the "sso.callback" route when empty.
        'redirect' => env('OIDC_REDIRECT_URI') ?: null,
        'scopes' => env('OIDC_SCOPES') ?: null,
        'require_email' => true,
    ],

];
