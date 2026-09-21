<?php

namespace App\Http\Controllers\Cekbot;

use App\Http\Controllers\Controller;
use App\Models\CekbotSession;
use App\Services\Cekbot\CekbotOutbound;
use App\Services\WhatsApp\WahaSessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cekbot — WhatsApp number/session manager.
 *
 * Each row in cekbot_sessions maps to one WAHA session (== one WhatsApp
 * number). WAHA is the live source of truth for status + the connected number;
 * this controller keeps the local table in sync on every page load and drives
 * the connect / QR-scan flow via JSON endpoints polled by the React modal.
 */
class SessionController extends Controller
{
    public function __construct(private WahaSessionManager $waha, private CekbotOutbound $out) {}

    public function index(): Response
    {
        [$sessions, $reachable] = $this->reconcile();

        $serverUrl = $this->waha->serverUrl();

        // Capability is probed live (cached briefly) rather than inferred from
        // the tier string, so in-app onboarding lights up automatically the
        // moment a blocked server's config is fixed — no redeploy needed.
        $canManage = $reachable
            ? Cache::remember('cekbot.waha.writes_allowed', 120, fn () => $this->waha->writesAllowed())
            : true;

        return Inertia::render('Sessions/Index', [
            'sessions' => $sessions,
            'waha' => [
                'configured' => $this->waha->isConfigured(),
                'reachable' => $reachable,
                'serverUrl' => $serverUrl,
                'dashboardUrl' => $serverUrl ? rtrim($serverUrl, '/').'/dashboard' : null,
                'canManage' => $canManage,
            ],
            'cloud' => [
                // Callback URL + verify hint the admin pastes into the Meta App
                // dashboard when wiring an official number's webhook.
                'webhookUrl' => route('api.cekbot.cloud.webhook.handle'),
                'apiVersion' => config('services.whatsapp.meta.api_version', 'v21.0'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSession($request);
        $provider = $validated['provider'] ?? CekbotSession::PROVIDER_WAHA;

        if ($provider === CekbotSession::PROVIDER_CLOUD_API) {
            $session = CekbotSession::create([
                'session_name' => $this->uniqueSessionName($validated['label']),
                'label' => $validated['label'],
                'notes' => $validated['notes'] ?? null,
                'provider' => CekbotSession::PROVIDER_CLOUD_API,
                'phone_number' => $validated['phone_number'] ?? null,
                'phone_number_id' => $validated['phone_number_id'],
                'waba_id' => $validated['waba_id'] ?? null,
                'access_token' => $validated['access_token'],
                'app_secret' => $validated['app_secret'] ?? null,
                'verify_token' => ($validated['verify_token'] ?? null) ?: Str::random(24),
                'api_version' => $validated['api_version'] ?? null,
                'status' => null,
                'created_by' => $request->user()->id,
            ]);

            return back()->with([
                'success' => 'Nombor WhatsApp Rasmi ditambah. Sahkan kredensial untuk menyambung.',
                'connectSessionId' => $session->id,
            ]);
        }

        $session = CekbotSession::create([
            'session_name' => $this->uniqueSessionName($validated['label']),
            'label' => $validated['label'],
            'notes' => $validated['notes'] ?? null,
            'provider' => CekbotSession::PROVIDER_WAHA,
            'status' => null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with([
            'success' => 'Nombor ditambah. Scan QR untuk sambungkan WhatsApp.',
            'connectSessionId' => $session->id,
        ]);
    }

    public function update(Request $request, CekbotSession $session): RedirectResponse
    {
        $validated = $this->validateSession($request, $session);

        $data = [
            'label' => $validated['label'],
            'notes' => $validated['notes'] ?? null,
        ];

        // Cloud credentials are editable; secrets stay put when left blank so an
        // admin can tweak the label without re-pasting the access token.
        if ($session->isCloudApi()) {
            $data['phone_number'] = $validated['phone_number'] ?? null;
            $data['phone_number_id'] = $validated['phone_number_id'] ?? $session->phone_number_id;
            $data['waba_id'] = $validated['waba_id'] ?? null;
            $data['api_version'] = $validated['api_version'] ?? null;

            foreach (['access_token', 'app_secret', 'verify_token'] as $secret) {
                if (filled($validated[$secret] ?? null)) {
                    $data[$secret] = $validated[$secret];
                }
            }
        }

        $session->update($data);

        return back()->with('success', 'Maklumat nombor dikemas kini.');
    }

    /**
     * Shared validation for creating/updating a number. Cloud credentials are
     * required only when the number runs on the official Cloud API — and on
     * update the access token is optional (blank keeps the stored one).
     *
     * @return array<string, mixed>
     */
    private function validateSession(Request $request, ?CekbotSession $session = null): array
    {
        $isUpdate = $session !== null;
        $isCloud = $isUpdate
            ? $session->isCloudApi()
            : $request->input('provider') === CekbotSession::PROVIDER_CLOUD_API;

        $tokenRule = ($isCloud && ! $isUpdate) ? 'required' : 'nullable';

        return $request->validate([
            'label' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'provider' => ['nullable', Rule::in([CekbotSession::PROVIDER_WAHA, CekbotSession::PROVIDER_CLOUD_API])],
            'phone_number' => 'nullable|string|max:30',
            'phone_number_id' => [$isCloud && ! $isUpdate ? 'required' : 'nullable', 'string', 'max:255'],
            'waba_id' => 'nullable|string|max:255',
            'access_token' => [$tokenRule, 'string', 'max:2000'],
            'app_secret' => 'nullable|string|max:255',
            'verify_token' => 'nullable|string|max:255',
            'api_version' => 'nullable|string|max:20',
        ]);
    }

    public function destroy(CekbotSession $session): RedirectResponse
    {
        // Cloud API numbers have no WAHA session to tear down.
        if ($session->isWaha()) {
            try {
                $this->waha->deleteSession($session->session_name);
            } catch (\Throwable $e) {
                // Best effort — remove locally even if the server call fails.
            }
        }

        $session->delete();

        return back()->with('success', 'Nombor dipadam.');
    }

    public function logout(CekbotSession $session): RedirectResponse
    {
        if ($session->isCloudApi()) {
            return back()->with('error', 'Log keluar tidak berkenaan untuk WhatsApp Rasmi (Cloud API).');
        }

        try {
            $this->waha->logoutSession($session->session_name);
            $session->update([
                'status' => CekbotSession::STATUS_SCAN_QR,
                'phone_number' => null,
                'last_synced_at' => now(),
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal log keluar: '.$e->getMessage());
        }

        return back()->with('success', 'Nombor telah di-log keluar.');
    }

    public function stop(CekbotSession $session): RedirectResponse
    {
        if ($session->isCloudApi()) {
            return back()->with('error', 'Tindakan ini tidak berkenaan untuk WhatsApp Rasmi (Cloud API).');
        }

        try {
            $this->waha->stopSession($session->session_name);
            $session->update(['status' => CekbotSession::STATUS_STOPPED, 'last_synced_at' => now()]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal hentikan sesi: '.$e->getMessage());
        }

        return back()->with('success', 'Sesi dihentikan.');
    }

    public function restart(CekbotSession $session): RedirectResponse
    {
        if ($session->isCloudApi()) {
            return back()->with('error', 'Tindakan ini tidak berkenaan untuk WhatsApp Rasmi (Cloud API).');
        }

        try {
            $this->waha->restartSession($session->session_name);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal restart sesi: '.$e->getMessage());
        }

        return back()->with('success', 'Sesi di-restart.');
    }

    /**
     * Ensure the WAHA session exists + is running, then return the current
     * status and (if scannable) the QR data URI. Polled by the connect modal.
     */
    public function connect(CekbotSession $session): JsonResponse
    {
        if ($session->isCloudApi()) {
            return response()->json($this->cloudState($session));
        }

        try {
            $this->waha->ensureStarted($session->session_name, $this->sessionConfig($session));
            $data = $this->waha->getSession($session->session_name);

            return response()->json($this->liveState($session, $data));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Live status poll used while the connect modal is open.
     */
    public function status(CekbotSession $session): JsonResponse
    {
        if ($session->isCloudApi()) {
            return response()->json($this->cloudState($session));
        }

        try {
            $data = $this->waha->getSession($session->session_name);

            return response()->json($this->liveState($session, $data));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Validate an official Cloud API number's credentials against Meta and cache
     * the connected/failed status. Cloud numbers have no QR — "connect" just
     * confirms the phone number id + token reach the Graph API.
     *
     * @return array<string, mixed>
     */
    private function cloudState(CekbotSession $session): array
    {
        $result = $this->out->cloudProvider($session)->checkStatus();
        $connected = (bool) ($result['success'] ?? false);

        $session->update([
            'status' => $connected ? CekbotSession::STATUS_WORKING : CekbotSession::STATUS_FAILED,
            'last_synced_at' => now(),
        ]);

        return [
            'ok' => true,
            'provider' => CekbotSession::PROVIDER_CLOUD_API,
            'status' => $connected ? CekbotSession::STATUS_WORKING : CekbotSession::STATUS_FAILED,
            'qr' => null,
            'phone' => $session->phone_number,
            'message' => $result['message'] ?? null,
        ];
    }

    public function pairingCode(Request $request, CekbotSession $session): JsonResponse
    {
        if ($session->isCloudApi()) {
            return response()->json(['ok' => false, 'error' => 'Kod pairing tidak berkenaan untuk WhatsApp Rasmi.'], 422);
        }

        $validated = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        try {
            $this->waha->ensureStarted($session->session_name, $this->sessionConfig($session));
            $result = $this->waha->requestPairingCode($session->session_name, $validated['phone']);

            return response()->json([
                'ok' => true,
                'code' => $result['code'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Compute the JSON body for connect/status: status, connected number and a
     * QR data URI when the session is waiting to be scanned. Also refreshes the
     * local cache from the live data.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    private function liveState(CekbotSession $session, ?array $data): array
    {
        if ($data === null) {
            $session->update(['status' => CekbotSession::STATUS_UNKNOWN, 'last_synced_at' => now()]);

            return ['ok' => true, 'status' => CekbotSession::STATUS_UNKNOWN, 'qr' => null, 'phone' => null];
        }

        $status = $data['status'] ?? CekbotSession::STATUS_UNKNOWN;
        $this->syncRow($session, $data);

        $qr = null;
        if (in_array($status, [CekbotSession::STATUS_SCAN_QR, CekbotSession::STATUS_STARTING], true)) {
            $qr = $this->waha->getQrDataUri($session->session_name);
        }

        return [
            'ok' => true,
            'status' => $status,
            'qr' => $qr,
            'phone' => $this->numberFrom($data),
        ];
    }

    /**
     * Merge local rows with live WAHA sessions. Imports any server session we
     * don't track yet, and refreshes status/number for the ones we do.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: bool}
     */
    private function reconcile(): array
    {
        $reachable = true;
        $live = [];

        try {
            foreach ($this->waha->listSessions() as $remote) {
                if (! empty($remote['name'])) {
                    $live[$remote['name']] = $remote;
                }
            }
        } catch (\Throwable $e) {
            $reachable = false;
        }

        if ($reachable) {
            $tracked = CekbotSession::query()->pluck('session_name')->all();
            foreach ($live as $name => $remote) {
                if (! in_array($name, $tracked, true)) {
                    CekbotSession::create([
                        'session_name' => $name,
                        'label' => $name,
                        'status' => $remote['status'] ?? null,
                        'engine' => $this->engineFrom($remote),
                        'phone_number' => $this->numberFrom($remote),
                        'last_synced_at' => now(),
                    ]);
                }
            }
        }

        $sessions = CekbotSession::query()
            ->with('creator:id,name')
            ->latest()
            ->get()
            ->map(function (CekbotSession $session) use ($live, $reachable) {
                // Cloud API numbers aren't WAHA sessions — never reconcile them
                // against the WAHA server (which would wrongly flip them to
                // UNKNOWN when the server is reachable).
                if ($session->isCloudApi()) {
                    return $this->shapeSession($session);
                }

                if ($reachable && isset($live[$session->session_name])) {
                    $this->syncRow($session, $live[$session->session_name]);
                } elseif ($reachable) {
                    $session->update(['status' => CekbotSession::STATUS_UNKNOWN, 'last_synced_at' => now()]);
                }

                return $this->shapeSession($session);
            })
            ->all();

        return [$sessions, $reachable];
    }

    /**
     * Shape a session row for the React page. Cloud fields are included so the
     * card + connect modal can render credentials/webhook hints; the access
     * token itself is never sent — only whether one is stored.
     *
     * @return array<string, mixed>
     */
    private function shapeSession(CekbotSession $session): array
    {
        return [
            'id' => $session->id,
            'session_name' => $session->session_name,
            'label' => $session->label,
            'phone_number' => $session->phone_number,
            'status' => $session->status,
            'engine' => $session->engine,
            'notes' => $session->notes,
            'is_working' => $session->isWorking(),
            'creator' => $session->creator?->name,
            'created_ago' => $session->created_at?->diffForHumans(),
            'provider' => $session->provider ?: CekbotSession::PROVIDER_WAHA,
            'phone_number_id' => $session->phone_number_id,
            'waba_id' => $session->waba_id,
            'api_version' => $session->api_version,
            'verify_token' => $session->isCloudApi() ? $session->verify_token : null,
            'has_token' => filled($session->access_token),
            'has_app_secret' => filled($session->app_secret),
        ];
    }

    /**
     * Refresh a local row from a WAHA session payload.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncRow(CekbotSession $session, array $data): void
    {
        $session->update([
            'status' => $data['status'] ?? $session->status,
            'engine' => $this->engineFrom($data) ?? $session->engine,
            'phone_number' => $this->numberFrom($data) ?? $session->phone_number,
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Extract the connected number (digits only) from a session payload.
     *
     * @param  array<string, mixed>  $data
     */
    private function numberFrom(array $data): ?string
    {
        $id = $data['me']['id'] ?? null;

        return $id ? Str::before((string) $id, '@') : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function engineFrom(array $data): ?string
    {
        return $data['engine']['engine'] ?? ($data['config']['engine'] ?? null);
    }

    /**
     * Session config sent to WAHA on create. Metadata only for now — webhooks
     * (for the auto-reply bot) land in a later phase.
     *
     * @return array<string, mixed>
     */
    private function sessionConfig(CekbotSession $session): array
    {
        return [
            'metadata' => [
                'app' => 'cekbot',
                'cekbot.session_id' => (string) $session->id,
            ],
        ];
    }

    /**
     * Build a WAHA-safe, unique session name from a human label.
     */
    private function uniqueSessionName(string $label): string
    {
        $base = Str::slug($label) ?: 'sesi';

        do {
            $name = $base.'-'.Str::lower(Str::random(4));
        } while (CekbotSession::query()->where('session_name', $name)->exists());

        return $name;
    }
}
