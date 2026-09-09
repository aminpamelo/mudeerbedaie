<?php

declare(strict_types=1);

namespace App\Passport;

use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository as PassportClientRepository;

/**
 * Adds the `createAuthorizationCodeGrantClient()` method that Laravel MCP's
 * OAuth dynamic-client-registration controller calls. That method ships with
 * Passport 13 (PHP 8.4+); production runs PHP 8.3 on Passport 12, so we
 * implement it here on top of Passport 12's create(). See
 * [[project-funnel-studio-mcp]] / the PHP 8.3 shim.
 */
class ClientRepository extends PassportClientRepository
{
    /**
     * Create a public authorization-code-grant client (PKCE, no secret) for a
     * dynamically-registering AI client.
     *
     * @param  array<int, string>  $redirectUris
     */
    public function createAuthorizationCodeGrantClient(
        string $name,
        array $redirectUris,
        bool $confidential = true,
        mixed $user = null,
        bool $enableDeviceFlow = false,
    ): Client {
        return $this->create(
            is_object($user) ? $user->getAuthIdentifier() : $user,
            $name,
            implode(',', $redirectUris),
            null,
            false,
            false,
            $confidential,
        );
    }
}
