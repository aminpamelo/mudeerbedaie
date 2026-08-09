<?php

namespace App\Http\Controllers\Mindpal;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMindpalDocumentJob;
use App\Models\MindpalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->input('search', ''),
            'status' => $request->input('status', ''),
        ];

        $documents = MindpalDocument::query()
            ->with('uploader:id,name')
            ->when($filters['search'], fn ($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($doc) => [
                'id' => $doc->id,
                'title' => $doc->title,
                'description' => $doc->description,
                'file_name' => $doc->file_name,
                'file_size' => $doc->file_size,
                'total_pages' => $doc->total_pages,
                'total_chunks' => $doc->total_chunks,
                'status' => $doc->status,
                'error_message' => $doc->error_message,
                'uploader' => $doc->uploader?->name,
                'created_at' => $doc->created_at->diffForHumans(),
            ]);

        return Inertia::render('Documents', [
            'documents' => $documents,
            'filters' => $filters,
            'stats' => [
                'total' => MindpalDocument::count(),
                'ready' => MindpalDocument::ready()->count(),
                'processing' => MindpalDocument::where('status', 'processing')->count(),
                'failed' => MindpalDocument::where('status', 'failed')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'file' => 'required|file|mimes:pdf|max:51200',
        ]);

        $file = $request->file('file');
        $path = $file->store('mindpal-documents', 'public');

        $document = MindpalDocument::create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'status' => MindpalDocument::STATUS_PROCESSING,
            'uploaded_by' => $request->user()->id,
        ]);

        ProcessMindpalDocumentJob::dispatch($document->id);

        return back()->with('success', 'Document uploaded and processing started.');
    }

    public function destroy(MindpalDocument $document): RedirectResponse
    {
        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Document deleted.');
    }

    public function reprocess(MindpalDocument $document): RedirectResponse
    {
        $document->chunks()->delete();
        $document->update([
            'status' => MindpalDocument::STATUS_PROCESSING,
            'error_message' => null,
            'total_chunks' => 0,
        ]);

        ProcessMindpalDocumentJob::dispatch($document->id);

        return back()->with('success', 'Document reprocessing started.');
    }
}
