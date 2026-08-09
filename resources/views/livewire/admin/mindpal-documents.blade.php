<?php

use App\Jobs\ProcessMindpalDocumentJob;
use App\Models\MindpalDocument;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $pdfFile;

    public string $title = '';

    public function mount(): void
    {
        if (! auth()->user()->hasAnyRole(['admin', 'employee'])) {
            abort(403, 'Access denied');
        }
    }

    public function upload(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'pdfFile' => ['required', 'file', 'mimes:pdf', 'max:51200'],
        ]);

        $originalName = $this->pdfFile->getClientOriginalName();
        $path = $this->pdfFile->store('mindpal-documents', 'public');

        $document = MindpalDocument::create([
            'title' => $this->title,
            'file_path' => $path,
            'file_name' => $originalName,
            'file_size' => $this->pdfFile->getSize(),
            'status' => MindpalDocument::STATUS_PROCESSING,
            'uploaded_by' => auth()->id(),
        ]);

        ProcessMindpalDocumentJob::dispatch($document->id);

        $this->reset('title', 'pdfFile');

        session()->flash('success', 'Document uploaded and processing started.');
    }

    public function deleteDocument(int $id): void
    {
        $document = MindpalDocument::findOrFail($id);

        // Delete the file from storage
        if ($document->file_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($document->file_path)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();
    }

    public function reprocessDocument(int $id): void
    {
        $document = MindpalDocument::findOrFail($id);

        if ($document->status !== MindpalDocument::STATUS_FAILED) {
            return;
        }

        // Delete existing chunks before reprocessing
        $document->chunks()->delete();

        $document->update([
            'status' => MindpalDocument::STATUS_PROCESSING,
            'error_message' => null,
        ]);

        ProcessMindpalDocumentJob::dispatch($document->id);

        session()->flash('success', 'Document reprocessing started.');
    }

    public function with(): array
    {
        return [
            'documents' => MindpalDocument::query()
                ->with('uploader')
                ->orderByDesc('created_at')
                ->get(),
        ];
    }
} ?>

<section>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">MindPal Documents</flux:heading>
            <flux:text class="mt-2">Upload and manage PDF knowledge base</flux:text>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/50 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    {{-- Upload form --}}
    <div class="mb-6 rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-4">Upload PDF</flux:heading>
        <form wire:submit="upload" class="space-y-4">
            <flux:field>
                <flux:label>Title</flux:label>
                <flux:input wire:model="title" placeholder="e.g. Marketing Textbook Chapter 1" />
                @error('title') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
            </flux:field>

            <flux:field>
                <flux:label>PDF File (max 50MB)</flux:label>
                <input
                    type="file"
                    wire:model="pdfFile"
                    accept="application/pdf"
                    class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-zinc-700 hover:file:bg-zinc-200 dark:text-zinc-400 dark:file:bg-zinc-800 dark:file:text-zinc-300"
                />
                @error('pdfFile') <flux:text class="text-sm text-red-500">{{ $message }}</flux:text> @enderror
            </flux:field>

            <div wire:loading wire:target="pdfFile" class="text-sm text-zinc-500 dark:text-zinc-400">
                Uploading file...
            </div>

            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="upload">
                <div class="flex items-center justify-center">
                    <span wire:loading.remove wire:target="upload">Upload & Process</span>
                    <span wire:loading wire:target="upload">Processing...</span>
                </div>
            </flux:button>
        </form>
    </div>

    {{-- Documents table --}}
    <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Title</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Pages</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Chunks</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Size</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Uploaded</th>
                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900">
                @forelse ($documents as $document)
                    <tr wire:key="doc-{{ $document->id }}">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $document->title }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $document->file_name }}</div>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            @if ($document->status === 'processing')
                                <flux:badge color="yellow">Processing</flux:badge>
                            @elseif ($document->status === 'ready')
                                <flux:badge color="green">Ready</flux:badge>
                            @elseif ($document->status === 'failed')
                                <flux:badge color="red">Failed</flux:badge>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $document->total_pages ?? '-' }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $document->total_chunks ?? '-' }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-500 dark:text-zinc-400">
                            @if ($document->file_size)
                                {{ number_format($document->file_size / 1024 / 1024, 1) }} MB
                            @else
                                -
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $document->created_at->format('M j, Y') }}
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                            <div class="flex items-center justify-end gap-2">
                                @if ($document->status === 'failed')
                                    <flux:button variant="ghost" size="sm" wire:click="reprocessDocument({{ $document->id }})">
                                        <div class="flex items-center justify-center text-yellow-600 dark:text-yellow-400">
                                            <flux:icon name="arrow-path" class="mr-1 h-4 w-4" />
                                            Retry
                                        </div>
                                    </flux:button>
                                @endif
                                <flux:button variant="ghost" size="sm" wire:click="deleteDocument({{ $document->id }})" wire:confirm="Are you sure you want to delete this document? All associated chunks will also be deleted.">
                                    <div class="flex items-center justify-center text-red-600 dark:text-red-400">
                                        <flux:icon name="trash" class="mr-1 h-4 w-4" />
                                        Delete
                                    </div>
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">
                            No documents uploaded yet. Use the form above to upload your first PDF.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
