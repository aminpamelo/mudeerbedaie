<?php

namespace App\Http\Controllers\Cekbot;

use App\Http\Controllers\Controller;
use App\Models\CekbotConversation;
use App\Models\CekbotLeadCategory;
use App\Models\CekbotSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    /** Colours a lead category may use (token → Tailwind classes resolved client-side). */
    private const COLORS = ['slate', 'blue', 'sky', 'emerald', 'green', 'amber', 'orange', 'red', 'rose', 'violet', 'purple', 'pink'];

    public function index(Request $request): Response
    {
        $view = $request->string('view')->toString() === 'board' ? 'board' : 'list';

        $categories = CekbotLeadCategory::query()->ordered()->withCount('leads')->get()
            ->map(fn (CekbotLeadCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
                'sort_order' => $c->sort_order,
                'leads_count' => $c->leads_count,
            ]);

        $props = [
            'view' => $view,
            'filters' => [
                'search' => $request->string('search')->toString() ?: null,
                'session' => $request->integer('session') ?: null,
                'label' => $request->string('label')->toString() ?: null,
            ],
            'categories' => $categories,
            'colorOptions' => self::COLORS,
            'sessions' => CekbotSession::query()->orderBy('label')->get(['id', 'label', 'phone_number'])
                ->map(fn ($s) => ['id' => $s->id, 'label' => $s->label, 'phone_number' => $s->phone_number]),
            'availableLabels' => InboxController::LABELS,
            'stats' => $this->stats(),
        ];

        if ($view === 'board') {
            $props['board'] = $this->board($request);
        } else {
            $props['leads'] = $this->baseQuery($request)
                ->withCount('messages')
                ->with('category:id,name,color')
                ->orderByDesc('last_message_at')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (CekbotConversation $c) => $this->shape($c));
        }

        return Inertia::render('Leads/Index', $props);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:60',
            'color' => 'required|string|in:'.implode(',', self::COLORS),
        ]);

        CekbotLeadCategory::create([
            'name' => $validated['name'],
            'color' => $validated['color'],
            'sort_order' => (int) CekbotLeadCategory::max('sort_order') + 1,
        ]);

        return back()->with('success', 'Kategori ditambah.');
    }

    public function updateCategory(Request $request, CekbotLeadCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:60',
            'color' => 'required|string|in:'.implode(',', self::COLORS),
        ]);

        $category->update($validated);

        return back()->with('success', 'Kategori dikemas kini.');
    }

    public function destroyCategory(CekbotLeadCategory $category): RedirectResponse
    {
        $category->delete();

        return back()->with('success', 'Kategori dipadam. Leads di dalamnya kembali ke "Tiada kategori".');
    }

    /**
     * Move a lead into a category (or clear it). Used by Kanban drag-and-drop
     * and the per-card "move to" menu.
     */
    public function moveLead(Request $request, CekbotConversation $lead): RedirectResponse
    {
        $validated = $request->validate([
            'lead_category_id' => 'nullable|exists:cekbot_lead_categories,id',
        ]);

        $lead->update(['lead_category_id' => $validated['lead_category_id'] ?? null]);

        return back(303)->with('success', 'Lead dipindahkan.');
    }

    public function export(Request $request): StreamedResponse
    {
        $leads = $this->baseQuery($request)
            ->withCount('messages')
            ->with('category:id,name')
            ->orderByDesc('last_message_at')
            ->get();

        $filename = 'cekbot-leads-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($leads) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nama', 'Nombor', 'Nombor Bisnes', 'Kategori', 'Label', 'Jumlah Mesej', 'Kali Terakhir', 'Pertama Dihubungi']);

            foreach ($leads as $lead) {
                fputcsv($out, [
                    $lead->name ?? '',
                    $lead->phoneNumber(),
                    $lead->relationLoaded('session') && $lead->session ? $lead->session->label : '',
                    $lead->category?->name ?? '',
                    implode(', ', $lead->labels ?? []),
                    $lead->messages_count,
                    $lead->last_message_at?->format('Y-m-d H:i') ?? '',
                    $lead->created_at?->format('Y-m-d H:i') ?? '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Build the Kanban columns: one per category plus an "uncategorised" column.
     *
     * @return array<int, array<string, mixed>>
     */
    private function board(Request $request): array
    {
        $columns = [];

        $uncategorised = (clone $this->baseQuery($request))
            ->whereNull('lead_category_id')
            ->withCount('messages')
            ->with('category:id,name,color')
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get()
            ->map(fn (CekbotConversation $c) => $this->shape($c));

        $columns[] = [
            'id' => null,
            'name' => 'Tiada kategori',
            'color' => 'slate',
            'leads' => $uncategorised->values(),
            'total' => (clone $this->baseQuery($request))->whereNull('lead_category_id')->count(),
        ];

        foreach (CekbotLeadCategory::query()->ordered()->get() as $category) {
            $leads = (clone $this->baseQuery($request))
                ->where('lead_category_id', $category->id)
                ->withCount('messages')
                ->with('category:id,name,color')
                ->orderByDesc('last_message_at')
                ->limit(100)
                ->get()
                ->map(fn (CekbotConversation $c) => $this->shape($c));

            $columns[] = [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'leads' => $leads->values(),
                'total' => (clone $this->baseQuery($request))->where('lead_category_id', $category->id)->count(),
            ];
        }

        return $columns;
    }

    /**
     * @return array<string, int>
     */
    private function stats(): array
    {
        $all = CekbotConversation::query()->where('is_group', false);

        return [
            'total' => (clone $all)->count(),
            'new_week' => (clone $all)->where('created_at', '>=', now()->subWeek())->count(),
            'handed_over' => (clone $all)->whereNotNull('handed_over_at')->count(),
        ];
    }

    /**
     * @return Builder<CekbotConversation>
     */
    private function baseQuery(Request $request): Builder
    {
        $search = $request->string('search')->toString();

        return CekbotConversation::query()
            ->with('session:id,label,phone_number')
            ->where('is_group', false)
            ->when($request->integer('session'), fn ($q, $id) => $q->where('cekbot_session_id', $id))
            ->when($request->string('label')->toString(), fn ($q, $label) => $q->whereJsonContains('labels', $label))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('chat_id', 'like', "%{$search}%")));
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(CekbotConversation $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phoneNumber(),
            'labels' => $c->labels ?? [],
            'messages_count' => $c->messages_count,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
            'archived' => $c->archived_at !== null,
            'category_id' => $c->lead_category_id,
            'category' => $c->relationLoaded('category') && $c->category ? [
                'id' => $c->category->id,
                'name' => $c->category->name,
                'color' => $c->category->color,
            ] : null,
            'session' => $c->relationLoaded('session') && $c->session ? [
                'id' => $c->session->id,
                'label' => $c->session->label,
                'phone' => $c->session->phone_number,
            ] : null,
        ];
    }
}
