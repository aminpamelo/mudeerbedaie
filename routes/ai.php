<?php

use App\Mcp\Servers\FunnelStudioServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Servers
|--------------------------------------------------------------------------
|
| Funnel Studio MCP server: lets a marketer operate their Funnel Studio
| account from an AI assistant (Claude / ChatGPT). Protected by OAuth 2.1
| (Laravel Passport) — the marketer clicks "Connect" in their AI, signs in to
| Kelasify, and approves. Every tool then runs as that user, scoped to their
| own funnels and ad accounts. OAuth is required by ChatGPT and supported by
| Claude, so both connect with a clean sign-in (no tokens to paste).
|
*/
Mcp::oauthRoutes();

Mcp::web('/mcp/funnel-studio', FunnelStudioServer::class)
    ->middleware(['auth:api', 'throttle:60,1']);
