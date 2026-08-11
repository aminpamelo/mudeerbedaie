<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Services\Forms\SubmissionProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicFormController extends Controller
{
    public function show(string $slug): Response
    {
        $form = Form::where('slug', $slug)->firstOrFail();

        return Inertia::render('Public/Show', [
            'form' => [
                'title' => $form->title,
                'slug' => $form->slug,
                'description' => $form->description,
                'fields' => $form->fields ?? [],
                'status' => $form->status,
                'is_open' => $form->isAcceptingSubmissions(),
            ],
        ]);
    }

    public function submit(Request $request, string $slug, SubmissionProcessor $processor): RedirectResponse
    {
        $form = Form::where('slug', $slug)->firstOrFail();

        abort_unless($form->isAcceptingSubmissions(), 403, 'Borang ini tidak lagi menerima jawapan.');

        $data = $processor->process($form, $request);

        FormSubmission::create([
            'form_id' => $form->id,
            'submitted_by' => $request->user()?->id,
            'data' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        $form->increment('submissions_count');

        return redirect()->route('form.public.thankyou', $form->slug);
    }

    public function thankYou(string $slug): Response
    {
        $form = Form::where('slug', $slug)->firstOrFail();

        return Inertia::render('Public/ThankYou', [
            'form' => [
                'title' => $form->title,
                'slug' => $form->slug,
                'is_open' => $form->isAcceptingSubmissions(),
            ],
            'message' => $form->settings['confirmation_message'] ?? 'Terima kasih atas maklum balas anda.',
        ]);
    }
}
