<?php

use App\Mcp\Servers\FunnelStudioServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Servers
|--------------------------------------------------------------------------
|
| Funnel Studio MCP server: lets a marketer operate their Funnel Studio
| account from an AI assistant (Claude / ChatGPT). Protected by a Sanctum
| personal access token — the marketer pastes "Authorization: Bearer <token>"
| into their AI client, and every tool runs as that user, scoped to their own
| funnels and ad accounts.
|
*/
Mcp::web('/mcp/funnel-studio', FunnelStudioServer::class)
    ->middleware(['auth:sanctum', 'throttle:60,1']);
