<?php

namespace App\Http\Controllers\Cekbot;

use App\Http\Controllers\Controller;
use App\Models\CekbotClosingReference;
use App\Models\CekbotFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Library of real sales-team closing chats the Cekbot AI studies for style.
 * Each reference can be tagged to flows, or left global for every flow.
 */
class ClosingReferenceController extends Controller
{
    private const MESSAGES = [
        'title.required' => 'Beri tajuk ringkas untuk rujukan ini.',
        'transcript.required' => 'Tampal perbualan closing (atau muat naik fail .txt).',
        'transcript.max' => 'Perbualan terlalu panjang (maks 30,000 aksara). Pilih bahagian yang penting sahaja.',
        'flow_ids.*.exists' => 'Flow yang dipilih tidak wujud.',
    ];

    public function index(): Response
    {
        $flows = CekbotFlow::query()->orderBy('name')->get(['id', 'name']);
        $flowNames = $flows->pluck('name', 'id');

        return Inertia::render('References/Index', [
            'references' => CekbotClosingReference::query()
                ->with('creator:id,name')
                ->latest('id')
                ->get()
                ->map(fn (CekbotClosingReference $ref) => [
                    'id' => $ref->id,
                    'title' => $ref->title,
                    'transcript' => $ref->transcript,
                    'notes' => $ref->notes,
                    'flow_ids' => array_values(array_map('intval', $ref->flow_ids ?? [])),
                    'flow_names' => collect($ref->flow_ids ?? [])->map(fn ($id) => $flowNames[(int) $id] ?? null)->filter()->values(),
                    'is_active' => $ref->is_active,
                    'chars' => mb_strlen($ref->transcript),
                    'creator' => $ref->creator?->name,
                    'updated_at' => $ref->updated_at?->toIso8601String(),
                ])
                ->values(),
            'flows' => $flows,
            'limits' => ['perReply' => CekbotClosingReference::PROMPT_LIMIT, 'charsEach' => CekbotClosingReference::PROMPT_CHARS_EACH],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CekbotClosingReference::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Rujukan closing ditambah.');
    }

    public function update(Request $request, CekbotClosingReference $reference): RedirectResponse
    {
        $reference->update($this->validated($request));

        return back()->with('success', 'Rujukan closing dikemas kini.');
    }

    public function toggle(CekbotClosingReference $reference): RedirectResponse
    {
        $reference->update(['is_active' => ! $reference->is_active]);

        return back()->with('success', $reference->is_active ? 'Rujukan diaktifkan.' : 'Rujukan dimatikan.');
    }

    public function destroy(CekbotClosingReference $reference): RedirectResponse
    {
        $reference->delete();

        return back()->with('success', 'Rujukan closing dipadam.');
    }

    /**
     * @return array{title: string, transcript: string, notes: ?string, flow_ids: ?array<int, int>, is_active: bool}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'transcript' => ['required', 'string', 'max:30000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'flow_ids' => ['nullable', 'array'],
            'flow_ids.*' => ['integer', Rule::exists('cekbot_flows', 'id')],
            'is_active' => ['boolean'],
        ], self::MESSAGES);

        $flowIds = array_values(array_unique(array_map('intval', $data['flow_ids'] ?? [])));

        return [
            'title' => trim($data['title']),
            'transcript' => trim($data['transcript']),
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            'flow_ids' => $flowIds === [] ? null : $flowIds,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
