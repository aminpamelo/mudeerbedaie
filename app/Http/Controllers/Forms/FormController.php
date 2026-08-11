<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\StoreFormRequest;
use App\Http\Requests\Forms\UpdateFormRequest;
use App\Models\Form;
use App\Models\FormCategory;
use App\Services\Forms\FieldSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FormController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = (int) $request->user()->id;

        $forms = Form::query()
            ->forUser($userId)
            ->with('category:id,name,color')
            ->withCount('submissions')
            ->latest()
            ->get()
            ->map(fn (Form $form): array => $this->summary($form));

        return Inertia::render('Index', [
            'forms' => $forms,
            'categories' => $this->categories(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Builder', [
            'form' => null,
            'categories' => $this->categories(),
            'fieldTypes' => Form::FIELD_TYPES,
        ]);
    }

    public function store(StoreFormRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $form = Form::create([
            'user_id' => $request->user()->id,
            'form_category_id' => $data['form_category_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'fields' => FieldSchema::normalize($data['fields'] ?? []),
            'settings' => $data['settings'] ?? [],
            'published_at' => ($data['status'] === Form::STATUS_PUBLISHED) ? now() : null,
        ]);

        return redirect()
            ->route('forms.edit', $form)
            ->with('success', 'Borang berjaya dicipta.');
    }

    public function edit(Request $request, Form $form): Response
    {
        $this->authorizeForm($request, $form);

        return Inertia::render('Builder', [
            'form' => $this->detail($form),
            'categories' => $this->categories(),
            'fieldTypes' => Form::FIELD_TYPES,
        ]);
    }

    public function update(UpdateFormRequest $request, Form $form): RedirectResponse
    {
        $this->authorizeForm($request, $form);

        $data = $request->validated();

        $wasPublished = $form->isPublished();

        $form->update([
            'form_category_id' => $data['form_category_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'fields' => FieldSchema::normalize($data['fields'] ?? []),
            'settings' => $data['settings'] ?? [],
            'published_at' => (! $wasPublished && $data['status'] === Form::STATUS_PUBLISHED)
                ? now()
                : $form->published_at,
        ]);

        return back()->with('success', 'Borang berjaya dikemas kini.');
    }

    public function destroy(Request $request, Form $form): RedirectResponse
    {
        $this->authorizeForm($request, $form);

        $form->delete();

        return redirect()
            ->route('forms.index')
            ->with('success', 'Borang telah dibuang.');
    }

    public function duplicate(Request $request, Form $form): RedirectResponse
    {
        $this->authorizeForm($request, $form);

        $copy = $form->replicate(['uuid', 'slug', 'submissions_count', 'published_at']);
        $copy->title = $form->title.' (Salinan)';
        $copy->status = Form::STATUS_DRAFT;
        $copy->submissions_count = 0;
        $copy->published_at = null;
        $copy->user_id = $request->user()->id;
        $copy->save();

        return redirect()
            ->route('forms.edit', $copy)
            ->with('success', 'Salinan borang telah dicipta.');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Form $form): array
    {
        return [
            'id' => $form->id,
            'uuid' => $form->uuid,
            'title' => $form->title,
            'slug' => $form->slug,
            'status' => $form->status,
            'category' => $form->category ? [
                'id' => $form->category->id,
                'name' => $form->category->name,
                'color' => $form->category->color,
            ] : null,
            'submissions_count' => (int) ($form->submissions_count ?? 0),
            'field_count' => count($form->answerableFields()),
            'public_url' => $form->publicUrl(),
            'updated_at' => $form->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Form $form): array
    {
        return [
            ...$this->summary($form),
            'description' => $form->description,
            'fields' => $form->fields ?? [],
            'settings' => $form->settings ?? [],
            'form_category_id' => $form->form_category_id,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function categories()
    {
        return FormCategory::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'color'])
            ->map(fn (FormCategory $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'color' => $c->color,
            ]);
    }

    private function authorizeForm(Request $request, Form $form): void
    {
        abort_unless(
            $form->user_id === $request->user()->id || $request->user()->isAdmin(),
            403,
        );
    }
}
