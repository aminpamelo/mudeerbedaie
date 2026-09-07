<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lets a marketer manage the Sanctum tokens that connect their AI assistant
 * (Claude / ChatGPT) to the Funnel Studio MCP server. Tokens are tagged with
 * the "mcp:use" ability so this screen only ever lists or revokes AI-connection
 * tokens, never any other tokens the user may hold.
 */
class McpConnectionController extends Controller
{
    private const ABILITY = 'mcp:use';

    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()
            ->latest()
            ->get()
            ->filter(fn ($token) => in_array(self::ABILITY, $token->abilities ?? [], true))
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->diffForHumans(),
                'created_at' => $token->created_at->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'data' => [
                'server_url' => url('/mcp/funnel-studio'),
                'tokens' => $tokens,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:60'],
        ]);

        $name = trim((string) ($validated['name'] ?? '')) ?: 'AI Connection';

        $token = $request->user()->createToken($name, [self::ABILITY]);

        return response()->json([
            'data' => [
                'id' => $token->accessToken->getKey(),
                'name' => $name,
                'plain_text_token' => $token->plainTextToken,
            ],
        ], 201);
    }

    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $request->user()->tokens()
            ->whereKey($tokenId)
            ->where('abilities', 'like', '%'.self::ABILITY.'%')
            ->delete();

        return response()->json(['success' => true]);
    }
}
