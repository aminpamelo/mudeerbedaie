<?php

declare(strict_types=1);

namespace App\Passport;

use Laravel\Passport\Client as PassportClient;

/**
 * Custom Passport client that back-fills the `redirect_uris` and `grant_types`
 * attributes Laravel MCP's OAuth dynamic-client-registration controller reads.
 *
 * Passport 12's `oauth_clients` table stores only a single `redirect` string
 * and has no `grant_types` column, whereas Laravel MCP was written against
 * Passport 13 (which exposes both natively). Passport 13 requires PHP 8.4, and
 * production runs 8.3, so we derive the two attributes here instead. See
 * [[project-funnel-studio-mcp]] / the PHP 8.3 shim.
 */
class Client extends PassportClient
{
    /**
     * Derive the redirect URIs from the stored comma-joined `redirect` string.
     *
     * @return array<int, string>
     */
    public function getRedirectUrisAttribute(): array
    {
        $redirect = (string) ($this->attributes['redirect'] ?? '');

        return array_values(array_filter(array_map('trim', explode(',', $redirect))));
    }

    /**
     * The MCP server only ever registers authorization-code-grant clients.
     *
     * @return array<int, string>
     */
    public function getGrantTypesAttribute(): array
    {
        return ['authorization_code', 'refresh_token'];
    }
}
