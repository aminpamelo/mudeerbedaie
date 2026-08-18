<?php

namespace App\Jobs;

use App\Models\MindpalChunk;
use App\Models\MindpalDocument;
use App\Services\MindpalEmbeddingService;
use App\Services\MindpalPdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessMindpalDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public int $documentId) {}

    public function handle(MindpalPdfService $pdfService, MindpalEmbeddingService $embedService): void
    {
        // Parsing large PDFs with Smalot is memory-heavy; give the worker headroom.
        @ini_set('memory_limit', '1024M');

        $document = MindpalDocument::findOrFail($this->documentId);

        try {
            $fullPath = Storage::disk('public')->path($document->file_path);

            // 1. Extract text from PDF
            $pages = $pdfService->extractText($fullPath);
            $document->update(['total_pages' => count($pages)]);

            // 2. Chunk pages into smaller pieces
            $chunks = $pdfService->chunkPages($pages);

            // 3. Embed in batches of 20
            $batches = array_chunk($chunks, 20);

            foreach ($batches as $batch) {
                $texts = array_column($batch, 'content');
                $embeddings = $embedService->embedBatch($texts);

                foreach ($batch as $i => $chunkData) {
                    MindpalChunk::create([
                        'document_id' => $document->id,
                        'content' => $chunkData['content'],
                        'page_number' => $chunkData['page_number'],
                        'chunk_index' => $chunkData['chunk_index'],
                        'embedding' => $embeddings[$i],
                        'token_count' => $chunkData['token_count'],
                    ]);
                }
            }

            // 4. Mark document as ready
            $document->update([
                'status' => MindpalDocument::STATUS_READY,
                'total_chunks' => MindpalChunk::where('document_id', $document->id)->count(),
            ]);
        } catch (\Throwable $e) {
            $document->update([
                'status' => MindpalDocument::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('MindPal PDF processing failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $document = MindpalDocument::find($this->documentId);
        $document?->update([
            'status' => MindpalDocument::STATUS_FAILED,
            'error_message' => 'Processing failed after retries: '.$exception->getMessage(),
        ]);
    }
}
