<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Auth;

use Cachet\Cachet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;

class SsoUserResolver
{
    /**
     * Find, link or create the Cachet user matching an OpenID Connect identity.
     *
     * @throws SsoAccessDeniedException
     */
    public function resolve(AbstractUser $identity): Model
    {
        $claims = $identity->getRaw();
        $subject = (string) $identity->getId();
        $email = Str::lower(trim((string) $identity->getEmail()));

        if ($subject === '' || $email === '') {
            throw new SsoAccessDeniedException('The identity provider did not return a subject and an e-mail address.');
        }

        $groups = $this->groups($claims);

        $this->ensureAllowed($email, $groups);

        return DB::transaction(function () use ($identity, $claims, $subject, $email, $groups): Model {
            $user = $this->query()->where('oidc_sub', $subject)->first()
                ?? $this->linkableUserByEmail($email, $claims);

            $isNew = $user === null;
            $user ??= Cachet::userModel();

            $user->forceFill([
                'oidc_sub' => $subject,
                'name' => $identity->getName() ?: ($user->name ?: Str::before($email, '@')),
            ]);

            if ($isNew) {
                $user->forceFill([
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => Str::password(32),
                    'is_admin' => false,
                ]);
            } elseif (Str::lower($user->email) !== $email
                && $this->emailIsTrusted($claims)
                && ! $this->byEmail($email)->exists()) {
                $user->email = $email;
            }

            $this->syncAdminFlag($user, $claims, $groups);

            $user->save();

            return $user;
        });
    }

    /**
     * Find an existing account with the same e-mail that may be linked to the identity.
     *
     * Linking hands the existing account (possibly an admin) to whoever controls that
     * e-mail at the IdP, so it requires an e-mail the IdP vouches for.
     *
     * @param  array<string, mixed>  $claims
     */
    protected function linkableUserByEmail(string $email, array $claims): ?Model
    {
        $user = $this->byEmail($email)->first();

        if ($user === null) {
            return null;
        }

        if ($user->oidc_sub !== null) {
            throw new SsoAccessDeniedException('The e-mail address is already linked to another SSO identity.');
        }

        if (! $this->emailIsTrusted($claims)) {
            throw new SsoAccessDeniedException(
                'An account already exists for this e-mail, but the IdP did not mark it as verified. '
                .'Set SSO_TRUST_IDP_EMAIL=true if the IdP only issues e-mails it controls.'
            );
        }

        return $user;
    }

    /**
     * Determine if the e-mail from the IdP may be trusted to identify an existing account.
     *
     * @param  array<string, mixed>  $claims
     */
    protected function emailIsTrusted(array $claims): bool
    {
        if (array_key_exists('email_verified', $claims)) {
            return filter_var($claims['email_verified'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
        }

        return (bool) config('sso.trust_idp_email', false);
    }

    /**
     * Ensure the identity matches the configured domains and groups.
     *
     * @param  list<string>  $groups
     */
    protected function ensureAllowed(string $email, array $groups): void
    {
        $domains = array_map(Str::lower(...), config('sso.allowed_domains', []));
        $allowedGroups = config('sso.allowed_groups', []);

        if ($domains === [] && $allowedGroups === [] && ! config('sso.allow_any_user', false)) {
            throw new SsoAccessDeniedException(
                'No access policy configured: set SSO_ALLOWED_DOMAINS and/or SSO_ALLOWED_GROUPS, or SSO_ALLOW_ANY_USER=true.'
            );
        }

        if ($domains !== [] && ! in_array(Str::after($email, '@'), $domains, true)) {
            throw new SsoAccessDeniedException('The e-mail domain is not allowed.');
        }

        if ($allowedGroups !== [] && array_intersect($groups, $allowedGroups) === []) {
            throw new SsoAccessDeniedException('The user is not a member of an allowed group.');
        }
    }

    /**
     * Synchronise the admin flag from the IdP groups, when admin groups are configured.
     *
     * Nothing changes when the groups claim is missing (misconfigured scope, Entra
     * group overage...), so admins are not demoted by accident.
     *
     * @param  array<string, mixed>  $claims
     * @param  list<string>  $groups
     */
    protected function syncAdminFlag(Model $user, array $claims, array $groups): void
    {
        $adminGroups = config('sso.admin_groups', []);

        if ($adminGroups === [] || ! $this->hasGroupsClaim($claims)) {
            return;
        }

        $isAdmin = array_intersect($groups, $adminGroups) !== [];

        // Never demote the last remaining admin, or nobody could manage users any more.
        if (! $isAdmin && $user->exists && $user->is_admin
            && $this->query()->where('is_admin', true)->lockForUpdate()->count() <= 1) {
            return;
        }

        $user->is_admin = $isAdmin;
    }

    /**
     * Get the group names from the configured claim.
     *
     * @param  array<string, mixed>  $claims
     * @return list<string>
     */
    protected function groups(array $claims): array
    {
        $claim = config('sso.groups_claim', 'groups');

        // Namespaced claims (e.g. "https://example.com/groups") contain dots, nested ones use dot notation.
        $groups = array_key_exists($claim, $claims) ? $claims[$claim] : Arr::get($claims, $claim, []);

        return array_values(array_map('strval', array_filter(Arr::wrap($groups), 'is_scalar')));
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    protected function hasGroupsClaim(array $claims): bool
    {
        $claim = config('sso.groups_claim', 'groups');

        return array_key_exists($claim, $claims) || Arr::has($claims, $claim);
    }

    protected function byEmail(string $email): Builder
    {
        return $this->query()->whereRaw('lower(email) = ?', [$email]);
    }

    protected function query(): Builder
    {
        return Cachet::userModel()->newQuery();
    }
}
