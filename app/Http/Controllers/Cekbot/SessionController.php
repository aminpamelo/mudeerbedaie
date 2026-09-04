<?php

namespace App\Http\Controllers\Cekbot;

use App\Http\Controllers\Controller;
use App\Models\CekbotSession;
use App\Services\WhatsApp\WahaSessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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
    public function __construct(private WahaSessionManager $waha) {}

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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);

        $session = CekbotSession::create([
            'session_name' => $this->uniqueSessionName($validated['label']),
            'label' => $validated['label'],
            'notes' => $validated['notes'] ?? null,
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
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);

        $session->update([
            'label' => $validated['label'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Maklumat nombor dikemas kini.');
    }

    public function destroy(CekbotSession $session): RedirectResponse
    {
        try {
            $this->waha->deleteSession($session->session_name);
        } catch (\Throwable $e) {
            // Best effort — remove locally even if the server call fails.
        }

        $session->delete();

        return back()->with('success', 'Nombor dipadam.');
    }

    public function logout(CekbotSession $session): RedirectResponse
    {
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
        try {
            $this->waha->ensureStarted($session->session_name, $this->sessionConfig($session));
            $data = $this->waha->getSession($session->session_name);

            return response()->json($this->liveState($session, $data));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Live status poll used while the QR modal is open.
     */
    public function status(CekbotSession $session): JsonResponse
    {
        try {
            $data = $this->waha->getSession($session->session_name);

            return response()->json($this->liveState($session, $data));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }

    public function pairingCode(Request $request, CekbotSession $session): JsonResponse
    {
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
                if ($reachable && isset($live[$session->session_name])) {
                    $this->syncRow($session, $live[$session->session_name]);
                } elseif ($reachable) {
                    $session->update(['status' => CekbotSession::STATUS_UNKNOWN, 'last_synced_at' => now()]);
                }

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
                ];
            })
            ->all();

        return [$sessions, $reachable];
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
