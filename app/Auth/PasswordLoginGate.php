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

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class PasswordLoginGate
{
    /**
     * Determine if the e-mail and password login is allowed for the request.
     */
    public function allows(Request $request): bool
    {
        $allowedIps = config('sso.password_login_allowed_ips', []);

        if ($allowedIps === []) {
            return ! config('sso.enabled');
        }

        $ip = $request->ip();

        return $ip !== null && IpUtils::checkIp($ip, $allowedIps);
    }
}
