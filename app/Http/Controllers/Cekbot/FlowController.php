<?php

namespace App\Http\Controllers\Cekbot;

use App\Http\Controllers\Controller;
use App\Models\CekbotFlow;
use App\Models\CekbotFlowPackage;
use App\Models\CekbotProduct;
use App\Models\CekbotSession;
use App\Models\FacebookAdAccount;
use App\Models\Package;
use App\Models\Product;
use App\Models\SalesSource;
use App\Services\Funnel\FacebookAdsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin builder for guided sales-funnel flows ({@see CekbotFlow}). One flow
 * per WhatsApp number greets a customer, offers packages, takes a payment
 * choice and auto-creates an order — configured here without code.
 */
class FlowController extends Controller
{
    private const OPENING_DIR = 'cekbot-flows/opening';

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
                    'trigger_ads_count' => count($flow->trigger_ads ?? []),
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
            // Shop catalogue packages (bundles) — linkable just like products.
            'catalogPackages' => Package::query()
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'name', 'price'])
                ->map(fn (Package $p) => [
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
            'trigger_ads' => 'nullable|array|max:50',
            'trigger_ads.*.id' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'trigger_ads.*.name' => 'nullable|string|max:255',
            'welcome_message' => 'nullable|string|max:4096',
            'opening_messages' => 'nullable|array|max:10',
            'opening_messages.*.type' => 'required|in:text,image',
            'opening_messages.*.text' => 'nullable|required_if:opening_messages.*.type,text|string|max:4096',
            'opening_messages.*.path' => ['nullable', 'required_if:opening_messages.*.type,image', 'string', 'max:255', 'starts_with:'.self::OPENING_DIR.'/'.$flow->id.'/', 'not_regex:/\.\./'],
            'opening_messages.*.caption' => 'nullable|string|max:1024',
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
            'packages.*.shop_package_id' => 'nullable|exists:packages,id',
            'packages.*.label' => 'required|string|max:255',
            'packages.*.price' => 'nullable|numeric|min:0|max:9999999',
            'packages.*.currency' => 'nullable|string|max:8',
        ]);

        $validated['trigger_keywords'] = array_values(array_filter(
            array_map('trim', $validated['trigger_keywords'] ?? [])
        ));

        if (array_key_exists('trigger_ads', $validated)) {
            $validated['trigger_ads'] = collect($validated['trigger_ads'] ?? [])
                ->map(fn (array $ad) => ['id' => $ad['id'], 'name' => filled($ad['name'] ?? null) ? trim($ad['name']) : null])
                ->unique('id')
                ->values()
                ->all();
        }

        $packages = $validated['packages'] ?? [];
        unset($validated['packages']);

        if (array_key_exists('opening_messages', $validated)) {
            $validated['opening_messages'] = $this->normaliseOpening($validated['opening_messages'] ?? []);
            $this->deleteOrphanedOpeningImages($flow, $validated['opening_messages']);
        }

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

    /**
     * Upload an image for the opening sequence. Returns its path/url; it is
     * attached to the flow when the builder saves.
     */
    public function uploadOpeningImage(Request $request, CekbotFlow $flow): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $path = $request->file('image')->store(self::OPENING_DIR.'/'.$flow->id, 'public');

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    public function destroyBankImage(CekbotFlow $flow): RedirectResponse
    {
        if (filled($flow->bank_image)) {
            Storage::disk('public')->delete($flow->bank_image);
            $flow->update(['bank_image' => null]);
        }

        return back()->with('success', 'Gambar dibuang.');
    }

    /**
     * Copy a flow (settings, packages and images) into a new inactive draft,
     * so it can be tweaked without disturbing the live original.
     */
    public function duplicate(Request $request, CekbotFlow $flow): RedirectResponse
    {
        $copy = DB::transaction(function () use ($request, $flow): CekbotFlow {
            $copy = $flow->replicate(['bank_image', 'opening_messages']);
            $copy->name = $flow->name.' (Salinan)';
            $copy->is_active = false;
            $copy->created_by = $request->user()->id;
            $copy->save();

            $flow->packages->each(fn (CekbotFlowPackage $package) => $package->replicate()->fill(['cekbot_flow_id' => $copy->id])->save());

            $disk = Storage::disk('public');

            if (filled($flow->bank_image) && $disk->exists($flow->bank_image)) {
                $bankPath = 'cekbot-flows/'.Str::random(40).'.'.pathinfo($flow->bank_image, PATHINFO_EXTENSION);
                $disk->copy($flow->bank_image, $bankPath);
                $copy->bank_image = $bankPath;
            }

            $copy->opening_messages = collect($flow->opening_messages ?? [])
                ->map(function (array $message) use ($disk, $copy): array {
                    if (($message['type'] ?? null) !== 'image' || blank($message['path'] ?? null) || ! $disk->exists($message['path'])) {
                        return $message;
                    }

                    $newPath = self::OPENING_DIR.'/'.$copy->id.'/'.basename($message['path']);
                    $disk->copy($message['path'], $newPath);

                    return [...$message, 'path' => $newPath];
                })
                ->values()
                ->all() ?: null;

            $copy->save();

            return $copy;
        });

        return redirect()
            ->route('cekbot.flows.show', $copy->id)
            ->with('success', 'Flow disalin sebagai draf. Tukar keyword trigger sebelum aktifkan.');
    }

    /**
     * Search Facebook ads (all connected ad accounts) for the flow's ad trigger
     * picker.
     */
    public function searchAds(Request $request, FacebookAdsService $ads): JsonResponse
    {
        $request->validate(['q' => 'nullable|string|max:100']);

        return response()->json([
            'connected' => FacebookAdAccount::query()->where('account_status', 1)->exists(),
            'ads' => $ads->searchAds((string) $request->query('q', '')),
        ]);
    }

    public function destroy(CekbotFlow $flow): RedirectResponse
    {
        if (filled($flow->bank_image)) {
            Storage::disk('public')->delete($flow->bank_image);
        }

        Storage::disk('public')->deleteDirectory(self::OPENING_DIR.'/'.$flow->id);

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
                'shop_package_id' => $package['shop_package_id'] ?? null,
                'label' => Str::squish((string) $package['label']),
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
            'trigger_ads' => $flow->trigger_ads ?? [],
            'welcome_message' => $flow->welcome_message,
            'opening_messages' => $flow->openingMessages(),
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
                'shop_package_id' => $p->shop_package_id,
                'label' => $p->label,
                'price' => $p->price !== null ? (float) $p->price : null,
                'currency' => $p->currency,
            ])->values(),
        ];
    }

    /**
     * Keep only the fields each opening message type uses.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array<string, string|null>>
     */
    private function normaliseOpening(array $messages): array
    {
        return collect($messages)
            ->map(fn (array $m) => $m['type'] === 'image'
                ? ['type' => 'image', 'path' => $m['path'], 'caption' => filled($m['caption'] ?? null) ? trim($m['caption']) : null]
                : ['type' => 'text', 'text' => trim((string) $m['text'])])
            ->values()
            ->all();
    }

    /**
     * Delete opening images that were removed from the sequence.
     *
     * @param  array<int, array<string, string|null>>  $messages
     */
    private function deleteOrphanedOpeningImages(CekbotFlow $flow, array $messages): void
    {
        $kept = collect($messages)->pluck('path')->filter();

        collect($flow->opening_messages ?? [])
            ->pluck('path')
            ->filter()
            ->reject(fn (string $path) => $kept->contains($path))
            ->each(fn (string $path) => Storage::disk('public')->delete($path));
    }
}
