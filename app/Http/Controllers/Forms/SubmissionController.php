<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function index(Request $request, Form $form): Response
    {
        $this->authorizeForm($request, $form);

        $search = trim((string) $request->query('search', ''));
        $from = $request->query('from');
        $to = $request->query('to');

        $submissions = $form->submissions()
            ->with('submitter:id,name')
            ->when($search !== '', fn ($q) => $q->where('data', 'like', '%'.$search.'%'))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $rows = collect($submissions->items())
            ->map(fn (FormSubmission $s): array => [
                'id' => $s->id,
                'uuid' => $s->uuid,
                'submitted_at' => $s->created_at?->toIso8601String(),
                'submitted_by' => $s->submitter?->name,
                'answers' => $this->presentAnswers($form, $s),
            ]);

        return Inertia::render('Submissions', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'slug' => $form->slug,
                'status' => $form->status,
                'public_url' => $form->publicUrl(),
                'fields' => $form->answerableFields(),
            ],
            'submissions' => $rows,
            'meta' => [
                'total' => $submissions->total(),
                'current_page' => $submissions->currentPage(),
                'last_page' => $submissions->lastPage(),
                'links' => $submissions->linkCollection()->toArray(),
            ],
            'filters' => [
                'search' => $search,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function pdf(Request $request, Form $form, FormSubmission $submission): SymfonyResponse
    {
        $this->authorizeForm($request, $form);
        $this->assertBelongs($form, $submission);

        $pdf = Pdf::loadView('forms.pdf.submission', [
            'form' => $form,
            'submission' => $submission,
            'answers' => $this->presentAnswers($form, $submission),
        ]);

        $filename = 'borang-'.$form->slug.'-'.$submission->id.'.pdf';

        return $pdf->download($filename);
    }

    public function export(Request $request, Form $form): StreamedResponse
    {
        $this->authorizeForm($request, $form);

        $fields = $form->answerableFields();

        $headers = ['Tarikh Hantar', 'Dihantar Oleh'];
        foreach ($fields as $field) {
            $headers[] = $field['label'] ?: $field['id'];
        }

        $filename = 'borang-'.$form->slug.'-submissions.csv';

        return response()->streamDownload(function () use ($form, $fields, $headers): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, $headers);

            $form->submissions()
                ->with('submitter:id,name')
                ->latest()
                ->chunk(200, function ($chunk) use ($out, $fields): void {
                    foreach ($chunk as $submission) {
                        $row = [
                            $submission->created_at?->format('Y-m-d H:i'),
                            $submission->submitter?->name ?? 'Awam',
                        ];

                        foreach ($fields as $field) {
                            $row[] = $this->plainValue($field, $submission->data[$field['id']] ?? null);
                        }

                        fputcsv($out, $row);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function destroy(Request $request, Form $form, FormSubmission $submission): RedirectResponse
    {
        $this->authorizeForm($request, $form);
        $this->assertBelongs($form, $submission);

        foreach ($form->answerableFields() as $field) {
            if (($field['type'] ?? null) === 'file') {
                $descriptor = $submission->data[$field['id']] ?? null;
                if (is_array($descriptor) && isset($descriptor['path'])) {
                    Storage::disk('public')->delete($descriptor['path']);
                }
            }
        }

        $submission->delete();
        $form->decrement('submissions_count');

        return back()->with('success', 'Submission telah dibuang.');
    }

    /**
     * Present a submission's answers as label/value rows for display.
     *
     * @return array<int, array<string, mixed>>
     */
    private function presentAnswers(Form $form, FormSubmission $submission): array
    {
        $rows = [];

        foreach ($form->answerableFields() as $field) {
            $raw = $submission->data[$field['id']] ?? null;
            $fileUrl = null;

            if (($field['type'] ?? null) === 'file' && is_array($raw) && isset($raw['path'])) {
                $fileUrl = Storage::disk('public')->url($raw['path']);
            }

            $rows[] = [
                'id' => $field['id'],
                'label' => $field['label'] ?: $field['id'],
                'type' => $field['type'] ?? 'short_text',
                'value' => $this->plainValue($field, $raw),
                'file_url' => $fileUrl,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function plainValue(array $field, mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }

        if (($field['type'] ?? null) === 'file' && is_array($raw)) {
            return (string) ($raw['name'] ?? '');
        }

        if (is_array($raw)) {
            return implode(', ', array_map(fn ($v): string => (string) $v, $raw));
        }

        return (string) $raw;
    }

    private function assertBelongs(Form $form, FormSubmission $submission): void
    {
        abort_unless($submission->form_id === $form->id, 404);
    }

    private function authorizeForm(Request $request, Form $form): void
    {
        abort_unless(
            $form->user_id === $request->user()->id || $request->user()->isAdmin(),
            403,
        );
    }
}
