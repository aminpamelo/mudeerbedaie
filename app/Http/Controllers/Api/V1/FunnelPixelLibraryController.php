<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funnel\StoreFunnelPixelRequest;
use App\Http\Requests\Funnel\UpdateFunnelPixelRequest;
use App\Models\FunnelPixel;
use App\Services\Funnel\FacebookPixelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FunnelPixelLibraryController extends Controller
{
    public function __construct(
        protected FacebookPixelService $pixelService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = FunnelPixel::query()
            ->visibleTo($request->user())
            ->orderBy('group_name')
            ->orderBy('name');

        if ($platform = $request->input('platform')) {
            $query->where('platform', $platform);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('group_name', 'like', "%{$search}%");
            });
        }

        $pixels = $query->get()->map(fn (FunnelPixel $pixel) => $this->present($pixel));

        return response()->json(['data' => $pixels]);
    }

    public function store(StoreFunnelPixelRequest $request): JsonResponse
    {
        $pixel = FunnelPixel::create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'platform' => $request->validated('platform'),
            'group_name' => $request->validated('group_name'),
            'settings' => $request->validated('settings'),
        ]);

        return response()->json(['data' => $this->present($pixel)], 201);
    }

    public function update(UpdateFunnelPixelRequest $request, FunnelPixel $pixel): JsonResponse
    {
        $this->authorizeManage($request, $pixel);

        $changes = $request->validated();

        // Credentials changed → previous test result no longer proves anything.
        if (array_key_exists('settings', $changes) && $changes['settings'] !== $pixel->settings) {
            $changes['last_test_status'] = null;
            $changes['last_test_message'] = null;
            $changes['last_tested_at'] = null;
        }

        $pixel->update($changes);

        return response()->json(['data' => $this->present($pixel->fresh())]);
    }

    public function destroy(Request $request, FunnelPixel $pixel): JsonResponse
    {
        $this->authorizeManage($request, $pixel);

        $pixel->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Validate the pixel's credentials: format checks for both platforms, plus
     * a real Conversions API test event for Facebook when a token is present.
     */
    public function test(Request $request, FunnelPixel $pixel): JsonResponse
    {
        $this->authorizeManage($request, $pixel);

        $result = $pixel->platform === 'facebook'
            ? $this->testFacebook($pixel)
            : $this->testGoogle($pixel);

        $pixel->update([
            'last_test_status' => $result['success'] ? 'passed' : 'failed',
            'last_test_message' => $result['message'],
            'last_tested_at' => now(),
        ]);

        return response()->json($result + ['data' => $this->present($pixel->fresh())]);
    }

    protected function testFacebook(FunnelPixel $pixel): array
    {
        $settings = $pixel->settings ?? [];
        $pixelId = trim((string) ($settings['pixel_id'] ?? ''));
        $accessToken = trim((string) ($settings['access_token'] ?? ''));

        if ($pixelId === '') {
            return ['success' => false, 'message' => 'Pixel ID is empty.'];
        }

        if (! preg_match('/^\d{15,16}$/', $pixelId)) {
            return ['success' => false, 'message' => "\"{$pixelId}\" is not a valid Facebook Pixel ID — it must be 15-16 digits (no letters, emails, or spaces)."];
        }

        if ($accessToken === '') {
            return ['success' => true, 'message' => 'Pixel ID format is valid. Add a Conversions API access token to fully verify against Facebook.'];
        }

        return $this->pixelService->testCredentials(
            $pixelId,
            $accessToken,
            $settings['test_event_code'] ?? null,
            ['funnel_pixel_id' => $pixel->id]
        );
    }

    protected function testGoogle(FunnelPixel $pixel): array
    {
        $settings = $pixel->settings ?? [];
        $ga4Id = trim((string) ($settings['ga4_measurement_id'] ?? ''));
        $adsId = trim((string) ($settings['ads_conversion_id'] ?? ''));

        if ($ga4Id === '' && $adsId === '') {
            return ['success' => false, 'message' => 'Enter a GA4 Measurement ID or a Google Ads Conversion ID first.'];
        }

        $messages = [];

        if ($ga4Id !== '') {
            if (! preg_match('/^G-[A-Z0-9]{6,}$/i', $ga4Id)) {
                return ['success' => false, 'message' => "\"{$ga4Id}\" is not a valid GA4 Measurement ID — expected format G-XXXXXXXXXX."];
            }
            $messages[] = "GA4 ID {$ga4Id} format is valid.";
        }

        if ($adsId !== '') {
            if (! preg_match('/^AW-\d{6,}$/i', $adsId)) {
                return ['success' => false, 'message' => "\"{$adsId}\" is not a valid Google Ads Conversion ID — expected format AW-XXXXXXXXX."];
            }
            $messages[] = "Google Ads ID {$adsId} format is valid.";
        }

        $messages[] = 'Use "Check Installation" on a published funnel to confirm the tag fires on the live page.';

        return ['success' => true, 'message' => implode(' ', $messages)];
    }

    protected function authorizeManage(Request $request, FunnelPixel $pixel): void
    {
        $user = $request->user();

        if ($user->isFighter() && (int) $pixel->user_id !== (int) $user->id) {
            abort(403, 'You do not have access to this pixel.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(FunnelPixel $pixel): array
    {
        return [
            'id' => $pixel->id,
            'name' => $pixel->name,
            'platform' => $pixel->platform,
            'group_name' => $pixel->group_name,
            'settings' => $pixel->settings,
            'last_test_status' => $pixel->last_test_status,
            'last_test_message' => $pixel->last_test_message,
            'last_tested_at' => $pixel->last_tested_at?->toIso8601String(),
            'linked_funnels_count' => $pixel->linkedFunnelsCount(),
            'created_at' => $pixel->created_at?->toIso8601String(),
        ];
    }
}
