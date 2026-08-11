<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-only oversight of every form in the system: who created it, its
 * category, status and submission volume, with filtering.
 */
class AdminFormController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->query('category');
        $status = $request->query('status');

        $forms = Form::query()
            ->with(['user:id,name,email', 'category:id,name,color'])
            ->withCount('submissions')
            ->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
            ->when($categoryId, fn ($q) => $q->where('form_category_id', $categoryId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $rows = collect($forms->items())->map(fn (Form $form): array => [
            'id' => $form->id,
            'title' => $form->title,
            'slug' => $form->slug,
            'status' => $form->status,
            'creator' => $form->user ? [
                'id' => $form->user->id,
                'name' => $form->user->name,
                'email' => $form->user->email,
            ] : null,
            'category' => $form->category ? [
                'id' => $form->category->id,
                'name' => $form->category->name,
                'color' => $form->category->color,
            ] : null,
            'submissions_count' => (int) $form->submissions_count,
            'public_url' => $form->publicUrl(),
            'created_at' => $form->created_at?->toIso8601String(),
        ]);

        return Inertia::render('Admin', [
            'forms' => $rows,
            'meta' => [
                'total' => $forms->total(),
                'current_page' => $forms->currentPage(),
                'last_page' => $forms->lastPage(),
                'links' => $forms->linkCollection()->toArray(),
            ],
            'filters' => [
                'search' => $search,
                'category' => $categoryId ? (int) $categoryId : null,
                'status' => $status,
            ],
            'categories' => FormCategory::query()->ordered()->get(['id', 'name', 'color']),
            'stats' => [
                'total_forms' => Form::query()->count(),
                'published' => Form::query()->where('status', Form::STATUS_PUBLISHED)->count(),
                'total_submissions' => (int) Form::query()->sum('submissions_count'),
                'creators' => Form::query()->distinct('user_id')->count('user_id'),
            ],
        ]);
    }

    /**
     * Global feed of every submission across every form — the "Semua
     * Submission" sidebar page. Filterable by form, free text and date.
     */
    public function submissions(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $formId = $request->query('form');
        $from = $request->query('from');
        $to = $request->query('to');

        $submissions = FormSubmission::query()
            ->with(['form:id,title,slug', 'submitter:id,name'])
            ->when($formId, fn ($q) => $q->where('form_id', $formId))
            ->when($search !== '', fn ($q) => $q->where('data', 'like', '%'.$search.'%'))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $rows = collect($submissions->items())->map(function (FormSubmission $s): array {
            $firstAnswer = collect($s->data ?? [])
                ->first(fn ($v) => is_string($v) && $v !== '');

            return [
                'id' => $s->id,
                'form_id' => $s->form_id,
                'form_title' => $s->form?->title ?? '—',
                'submitted_by' => $s->submitter?->name,
                'submitted_at' => $s->created_at?->toIso8601String(),
                'preview' => is_string($firstAnswer) ? $firstAnswer : '',
                'pdf_url' => $s->form
                    ? route('forms.submissions.pdf', [$s->form_id, $s->id])
                    : null,
                'form_url' => $s->form
                    ? route('forms.submissions.index', $s->form_id)
                    : null,
            ];
        });

        return Inertia::render('AllSubmissions', [
            'submissions' => $rows,
            'meta' => [
                'total' => $submissions->total(),
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'links' => $submissions->linkCollection()->toArray(),
            ],
            'filters' => [
                'search' => $search,
                'form' => $formId ? (int) $formId : null,
                'from' => $from,
                'to' => $to,
            ],
            'forms' => Form::query()->orderBy('title')->get(['id', 'title']),
        ]);
    }
}
