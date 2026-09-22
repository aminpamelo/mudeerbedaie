<?php

namespace App\Http\Controllers\Cekbot;

use App\Http\Controllers\Controller;
use App\Models\CekbotFlow;
use App\Models\CekbotProduct;
use App\Models\CekbotSession;
use App\Models\Product;
use App\Models\SalesSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin builder for guided sales-funnel flows ({@see CekbotFlow}). One flow
 * per WhatsApp number greets a customer, offers packages, takes a payment
 * choice and auto-creates an order — configured here without code.
 */
class FlowController extends Controller
{
    public function index(): Response
    {
        $sessions = CekbotSession::query()
            ->with(['flows' => fn ($q) => $q->withCount('packages')->orderBy('sort_order')->orderBy('id')])
            ->orderBy('label')
            ->get()
            ->map(fn (CekbotSession $session) => [
                'id' => $session->id,
                'label' => $session->label,
                'phone_number' => $session->phone_number,
                'is_working' => $session->isWorking(),
                'provider' => $session->provider ?: CekbotSession::PROVIDER_WAHA,
                'flows' => $session->flows->map(fn (CekbotFlow $flow) => [
                    'id' => $flow->id,
                    'name' => $flow->name,
                    'is_active' => $flow->is_active,
                    'use_ai' => $flow->use_ai,
                    'trigger_keywords' => $flow->trigger_keywords ?? [],
                    'packages_count' => $flow->packages_count,
                ])->values(),
            ]);

        return Inertia::render('Flows/Index', [
            'sessions' => $sessions,
            'aiAvailable' => filled(config('openai.api_key')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cekbot_session_id' => 'required|exists:cekbot_sessions,id',
            'name' => 'required|string|max:255',
        ]);

        $flow = CekbotFlow::create([
            'cekbot_session_id' => $validated['cekbot_session_id'],
            'name' => $validated['name'],
            'is_active' => false,
            'match_type' => 'contains',
            'trigger_keywords' => [],
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('cekbot.flows.show', $flow->id)
            ->with('success', 'Flow dicipta. Sekarang isi butiran funnel.');
    }

    public function show(CekbotFlow $flow): Response
    {
        $flow->load(['session', 'packages']);

        return Inertia::render('Flows/Show', [
            'flow' => $this->shape($flow),
            // Shop catalogue products — the primary thing merchants link a
            // package to (so the order references a real product).
            'catalogProducts' => Product::query()
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'name', 'base_price'])
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => $p->price !== null ? (float) $p->price : null,
                ]),
            // Cekbot knowledge products — optional, feed richer info to the AI.
            'cekbotProducts' => CekbotProduct::query()
                ->active()
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->get(['id', 'name', 'price', 'currency'])
                ->map(fn (CekbotProduct $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => $p->price !== null ? (float) $p->price : null,
                    'currency' => $p->currency,
                ]),
            'salesSources' => SalesSource::query()
                ->active()
                ->ordered()
                ->get(['id', 'name'])
                ->map(fn (SalesSource $s) => ['id' => $s->id, 'name' => $s->name]),
            'aiAvailable' => filled(config('openai.api_key')),
        ]);
    }

    public function update(Request $request, CekbotFlow $flow): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'use_ai' => 'boolean',
            'ai_instructions' => 'nullable|string|max:4096',
            'match_type' => 'required|in:contains,exact,starts',
            'trigger_keywords' => 'nullable|array|max:50',
            'trigger_keywords.*' => 'nullable|string|max:255',
            'welcome_message' => 'nullable|string|max:4096',
            'package_prompt' => 'nullable|string|max:1000',
            'confirmation_message' => 'nullable|string|max:4096',
            'ask_payment' => 'boolean',
            'payment_transfer_enabled' => 'boolean',
            'payment_cod_enabled' => 'boolean',
            'bank_details' => 'nullable|string|max:4096',
            'transfer_instructions' => 'nullable|string|max:2000',
            'ask_name' => 'boolean',
            'sales_source_id' => 'nullable|exists:sales_sources,id',
            'packages' => 'nullable|array|max:30',
            'packages.*.id' => 'nullable|integer',
            'packages.*.cekbot_product_id' => 'nullable|exists:cekbot_products,id',
            'packages.*.product_id' => 'nullable|exists:products,id',
            'packages.*.label' => 'required|string|max:255',
            'packages.*.price' => 'nullable|numeric|min:0|max:9999999',
            'packages.*.currency' => 'nullable|string|max:8',
        ]);

        $validated['trigger_keywords'] = array_values(array_filter(
            array_map('trim', $validated['trigger_keywords'] ?? [])
        ));

        $packages = $validated['packages'] ?? [];
        unset($validated['packages']);

        $flow->update($validated);

        $this->syncPackages($flow, $packages);

        return back()->with('success', 'Flow disimpan.');
    }

    public function toggle(Request $request, CekbotFlow $flow): RedirectResponse
    {
        $flow->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('success', $flow->is_active ? 'Flow diaktifkan.' : 'Flow dimatikan.');
    }

    public function uploadBankImage(Request $request, CekbotFlow $flow): RedirectResponse
    {
        $request->validate([
            'bank_image' => 'required|image|max:5120',
        ]);

        if (filled($flow->bank_image)) {
            Storage::disk('public')->delete($flow->bank_image);
        }

        $path = $request->file('bank_image')->store('cekbot-flows', 'public');
        $flow->update(['bank_image' => $path]);

        return back()->with('success', 'Gambar bank/QR dimuat naik.');
    }

    public function destroyBankImage(CekbotFlow $flow): RedirectResponse
    {
        if (filled($flow->bank_image)) {
            Storage::disk('public')->delete($flow->bank_image);
            $flow->update(['bank_image' => null]);
        }

        return back()->with('success', 'Gambar dibuang.');
    }

    public function destroy(CekbotFlow $flow): RedirectResponse
    {
        if (filled($flow->bank_image)) {
            Storage::disk('public')->delete($flow->bank_image);
        }

        $flow->delete();

        return redirect()
            ->route('cekbot.flows')
            ->with('success', 'Flow dipadam.');
    }

    /**
     * Replace the flow's packages with the submitted set, updating existing
     * rows by id, creating new ones, and deleting those left out.
     *
     * @param  array<int, array<string, mixed>>  $packages
     */
    private function syncPackages(CekbotFlow $flow, array $packages): void
    {
        $keptIds = [];

        foreach (array_values($packages) as $index => $package) {
            $attributes = [
                'cekbot_product_id' => $package['cekbot_product_id'] ?? null,
                'product_id' => $package['product_id'] ?? null,
                'label' => trim((string) $package['label']),
                'price' => $package['price'] !== null && $package['price'] !== '' ? $package['price'] : null,
                'currency' => $package['currency'] ?? 'RM',
                'sort_order' => $index,
            ];

            $existingId = $package['id'] ?? null;
            $row = $existingId ? $flow->packages()->whereKey($existingId)->first() : null;

            if ($row) {
                $row->update($attributes);
            } else {
                $row = $flow->packages()->create($attributes);
            }

            $keptIds[] = $row->id;
        }

        $flow->packages()->whereKeyNot($keptIds)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(CekbotFlow $flow): array
    {
        return [
            'id' => $flow->id,
            'cekbot_session_id' => $flow->cekbot_session_id,
            'session_label' => $flow->session?->label,
            'session_phone' => $flow->session?->phone_number,
            'session_provider' => $flow->session?->provider ?: CekbotSession::PROVIDER_WAHA,
            'session_is_working' => (bool) $flow->session?->isWorking(),
            'name' => $flow->name,
            'is_active' => $flow->is_active,
            'use_ai' => $flow->use_ai,
            'ai_instructions' => $flow->ai_instructions,
            'match_type' => $flow->match_type,
            'trigger_keywords' => $flow->trigger_keywords ?? [],
            'welcome_message' => $flow->welcome_message,
            'package_prompt' => $flow->package_prompt,
            'confirmation_message' => $flow->confirmation_message,
            'ask_payment' => $flow->ask_payment,
            'payment_transfer_enabled' => $flow->payment_transfer_enabled,
            'payment_cod_enabled' => $flow->payment_cod_enabled,
            'bank_details' => $flow->bank_details,
            'bank_image_url' => $flow->bankImageUrl(),
            'transfer_instructions' => $flow->transfer_instructions,
            'ask_name' => $flow->ask_name,
            'sales_source_id' => $flow->sales_source_id,
            'packages' => $flow->packages->map(fn ($p) => [
                'id' => $p->id,
                'cekbot_product_id' => $p->cekbot_product_id,
                'product_id' => $p->product_id,
                'label' => $p->label,
                'price' => $p->price !== null ? (float) $p->price : null,
                'currency' => $p->currency,
            ])->values(),
        ];
    }
}
