# MindPal RAG Chatbot — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a RAG chatbot at `/my/mindpal` where students ask questions answered by AI using uploaded PDF sources, with admin PDF management at `/admin/mindpal`.

**Architecture:** OpenAI GPT-4o-mini for chat + text-embedding-3-small for embeddings. PDF text extracted via smalot/pdfparser, chunked and embedded, stored in MySQL/SQLite. Cosine similarity retrieval, streamed responses via SSE. Student UI is Inertia React (student portal); admin UI is Livewire Volt.

**Tech Stack:** Laravel 12, OpenAI PHP SDK, smalot/pdfparser, Inertia.js + React 19, SSE streaming, Laravel Queue, Pest PHP 4.

**Design Doc:** `docs/plans/2026-08-09-mindpal-rag-chatbot-design.md`

---

## Task 1: Install Dependencies + Database + Models

**Files:**
- Modify: `composer.json` — add openai-php/laravel, smalot/pdfparser
- Create: `database/migrations/2026_08_09_XXXXXX_create_mindpal_tables.php`
- Create: `app/Models/MindpalDocument.php`
- Create: `app/Models/MindpalChunk.php`
- Create: `app/Models/MindpalConversation.php`
- Create: `app/Models/MindpalMessage.php`
- Create: `tests/Feature/Mindpal/MindpalModelTest.php`

### Step 1: Install PHP packages

```bash
composer require openai-php/laravel smalot/pdfparser --no-interaction
```

### Step 2: Add OPENAI_API_KEY to .env.example

Add to `.env.example`:
```
OPENAI_API_KEY=
```

And to `.env`:
```
OPENAI_API_KEY=sk-your-key-here
```

### Step 3: Create migration

```bash
php artisan make:migration create_mindpal_tables --no-interaction
```

Write migration with 4 tables:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mindpal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedInteger('total_pages')->default(0);
            $table->unsignedInteger('total_chunks')->default(0);
            $table->string('status', 20)->default('processing');
            $table->text('error_message')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('status', 'mindpal_doc_status_index');
        });

        Schema::create('mindpal_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('mindpal_documents')->cascadeOnDelete();
            $table->text('content');
            $table->unsignedInteger('page_number')->default(1);
            $table->unsignedInteger('chunk_index')->default(0);
            $table->json('embedding')->nullable();
            $table->unsignedInteger('token_count')->default(0);
            $table->timestamps();

            $table->index(['document_id', 'chunk_index'], 'mindpal_chunk_doc_idx');
        });

        Schema::create('mindpal_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at'], 'mindpal_conv_user_time');
        });

        Schema::create('mindpal_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('mindpal_conversations')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->json('sources')->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->timestamps();

            $table->index('conversation_id', 'mindpal_msg_conv_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mindpal_messages');
        Schema::dropIfExists('mindpal_conversations');
        Schema::dropIfExists('mindpal_chunks');
        Schema::dropIfExists('mindpal_documents');
    }
};
```

### Step 4: Create models

```bash
php artisan make:model MindpalDocument --no-interaction
php artisan make:model MindpalChunk --no-interaction
php artisan make:model MindpalConversation --no-interaction
php artisan make:model MindpalMessage --no-interaction
```

**MindpalDocument:**
- fillable: title, description, file_path, file_name, file_size, total_pages, total_chunks, status, error_message, uploaded_by
- relationships: chunks() hasMany, uploader() belongsTo User
- scopes: scopeReady (status = 'ready')
- constants: STATUS_PROCESSING, STATUS_READY, STATUS_FAILED

**MindpalChunk:**
- fillable: document_id, content, page_number, chunk_index, embedding, token_count
- casts: embedding => array
- relationships: document() belongsTo

**MindpalConversation:**
- fillable: user_id, title
- relationships: user() belongsTo, messages() hasMany
- scopes: scopeForUser

**MindpalMessage:**
- fillable: conversation_id, role, content, sources, tokens_used
- casts: sources => array
- relationships: conversation() belongsTo

### Step 5: Run migration

```bash
php artisan migrate
```

### Step 6: Write model tests

Create `tests/Feature/Mindpal/MindpalModelTest.php`:
- document has chunks relationship
- conversation has messages relationship
- document status scopes work
- message casts sources as array
- document cascadeOnDelete deletes chunks

### Step 7: Run tests

```bash
php artisan test --compact tests/Feature/Mindpal/MindpalModelTest.php
```

### Step 8: Commit

```bash
git add composer.json composer.lock database/migrations/*mindpal* app/Models/Mindpal*.php tests/Feature/Mindpal/ .env.example
git commit -m "feat(mindpal): install deps, create database tables and models"
```

---

## Task 2: PDF Processing Service + Queue Job

**Files:**
- Create: `app/Services/MindpalPdfService.php`
- Create: `app/Services/MindpalEmbeddingService.php`
- Create: `app/Jobs/ProcessMindpalDocumentJob.php`
- Create: `tests/Feature/Mindpal/MindpalPdfServiceTest.php`

### Step 1: Create MindpalPdfService

Handles PDF text extraction and chunking:

```php
<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

class MindpalPdfService
{
    private const CHUNK_SIZE = 500;      // ~500 tokens per chunk
    private const CHUNK_OVERLAP = 50;    // 50 token overlap

    public function extractText(string $filePath): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $pages = $pdf->getPages();

        $result = [];
        foreach ($pages as $i => $page) {
            $text = $page->getText();
            if (trim($text)) {
                $result[] = [
                    'page' => $i + 1,
                    'text' => trim($text),
                ];
            }
        }

        return $result;
    }

    public function chunkPages(array $pages): array
    {
        $chunks = [];
        $index = 0;

        foreach ($pages as $pageData) {
            $words = preg_split('/\s+/', $pageData['text']);
            $totalWords = count($words);

            if ($totalWords <= self::CHUNK_SIZE) {
                $chunks[] = [
                    'content' => $pageData['text'],
                    'page_number' => $pageData['page'],
                    'chunk_index' => $index++,
                    'token_count' => $totalWords,
                ];
                continue;
            }

            // Split large pages into overlapping chunks
            $pos = 0;
            while ($pos < $totalWords) {
                $chunkWords = array_slice($words, $pos, self::CHUNK_SIZE);
                $chunks[] = [
                    'content' => implode(' ', $chunkWords),
                    'page_number' => $pageData['page'],
                    'chunk_index' => $index++,
                    'token_count' => count($chunkWords),
                ];
                $pos += self::CHUNK_SIZE - self::CHUNK_OVERLAP;
            }
        }

        return $chunks;
    }

    public function getPageCount(string $filePath): int
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        return count($pdf->getPages());
    }
}
```

### Step 2: Create MindpalEmbeddingService

Wraps OpenAI embedding + cosine similarity:

```php
<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class MindpalEmbeddingService
{
    public function embed(string $text): array
    {
        $response = OpenAI::embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $text,
        ]);

        return $response->embeddings[0]->embedding;
    }

    public function embedBatch(array $texts): array
    {
        $response = OpenAI::embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $texts,
        ]);

        return array_map(fn ($e) => $e->embedding, $response->embeddings);
    }

    public function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0, $len = count($a); $i < $len; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    public function findSimilarChunks(array $queryEmbedding, int $limit = 5): array
    {
        $chunks = \App\Models\MindpalChunk::query()
            ->whereNotNull('embedding')
            ->with('document:id,title')
            ->get();

        $scored = $chunks->map(function ($chunk) use ($queryEmbedding) {
            return [
                'chunk' => $chunk,
                'score' => $this->cosineSimilarity($queryEmbedding, $chunk->embedding),
            ];
        });

        return $scored->sortByDesc('score')
            ->take($limit)
            ->values()
            ->toArray();
    }
}
```

### Step 3: Create ProcessMindpalDocumentJob

```php
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
    public int $timeout = 600; // 10 minutes for large PDFs

    public function __construct(public int $documentId) {}

    public function handle(MindpalPdfService $pdfService, MindpalEmbeddingService $embedService): void
    {
        $document = MindpalDocument::findOrFail($this->documentId);

        try {
            $fullPath = Storage::disk('public')->path($document->file_path);

            // 1. Extract text from PDF
            $pages = $pdfService->extractText($fullPath);
            $document->update(['total_pages' => count($pages)]);

            // 2. Chunk the text
            $chunks = $pdfService->chunkPages($pages);

            // 3. Generate embeddings in batches of 20
            $batchSize = 20;
            $batches = array_chunk($chunks, $batchSize);

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

            // 4. Mark as ready
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
            'error_message' => 'Processing failed after retries: ' . $exception->getMessage(),
        ]);
    }
}
```

### Step 4: Write service tests

Test the PDF chunking logic (mock OpenAI calls for embedding tests):
- extractText returns pages array
- chunkPages splits large pages with overlap
- chunkPages keeps small pages as single chunk
- cosineSimilarity returns correct value

### Step 5: Run tests and commit

```bash
php artisan test --compact tests/Feature/Mindpal/
git add app/Services/Mindpal*.php app/Jobs/ProcessMindpalDocumentJob.php tests/Feature/Mindpal/
git commit -m "feat(mindpal): add PDF processing service, embedding service, and queue job"
```

---

## Task 3: RAG Chat Service

**Files:**
- Create: `app/Services/MindpalChatService.php`
- Create: `tests/Feature/Mindpal/MindpalChatServiceTest.php`

### Step 1: Create MindpalChatService

This is the core RAG engine — retrieves relevant chunks and generates AI responses:

```php
<?php

namespace App\Services;

use App\Models\MindpalConversation;
use App\Models\MindpalMessage;
use OpenAI\Laravel\Facades\OpenAI;

class MindpalChatService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are MindPal, a knowledgeable AI assistant that answers questions based on provided reference materials.

Rules:
1. ONLY answer based on the provided context. If the context doesn't contain relevant information, say so honestly.
2. Always cite your sources using the format: [Book Title, Page X].
3. Be accurate and factual. Do not make up information.
4. Answer in the same language as the question.
5. If multiple sources provide relevant information, reference all of them.
6. Keep answers clear, structured, and helpful for students.
PROMPT;

    public function __construct(
        private readonly MindpalEmbeddingService $embedService,
    ) {}

    public function ask(MindpalConversation $conversation, string $question): array
    {
        // 1. Generate embedding for the question
        $queryEmbedding = $this->embedService->embed($question);

        // 2. Find similar chunks
        $results = $this->embedService->findSimilarChunks($queryEmbedding, 5);

        // 3. Build context from retrieved chunks
        $context = $this->buildContext($results);
        $sources = $this->extractSources($results);

        // 4. Get conversation history (last 10 messages for context)
        $history = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse()
            ->values();

        // 5. Build messages array
        $messages = $this->buildMessages($history, $context, $question);

        // 6. Call OpenAI
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'max_tokens' => 1500,
            'temperature' => 0.3,
        ]);

        $answer = $response->choices[0]->message->content;
        $tokensUsed = $response->usage->totalTokens;

        // 7. Save user message
        MindpalMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $question,
        ]);

        // 8. Save assistant message
        $assistantMessage = MindpalMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $answer,
            'sources' => $sources,
            'tokens_used' => $tokensUsed,
        ]);

        // 9. Auto-title conversation from first question
        if (!$conversation->title) {
            $conversation->update(['title' => \Str::limit($question, 80)]);
        }

        return [
            'message' => $assistantMessage,
            'sources' => $sources,
        ];
    }

    public function streamAsk(MindpalConversation $conversation, string $question): \Generator
    {
        $queryEmbedding = $this->embedService->embed($question);
        $results = $this->embedService->findSimilarChunks($queryEmbedding, 5);
        $context = $this->buildContext($results);
        $sources = $this->extractSources($results);

        $history = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse()
            ->values();

        $messages = $this->buildMessages($history, $context, $question);

        // Save user message first
        MindpalMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $question,
        ]);

        // Stream from OpenAI
        $stream = OpenAI::chat()->createStreamed([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'max_tokens' => 1500,
            'temperature' => 0.3,
        ]);

        $fullResponse = '';

        foreach ($stream as $response) {
            $delta = $response->choices[0]->delta->content ?? '';
            if ($delta) {
                $fullResponse .= $delta;
                yield $delta;
            }
        }

        // Save full assistant message after streaming completes
        MindpalMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $fullResponse,
            'sources' => $sources,
        ]);

        if (!$conversation->title) {
            $conversation->update(['title' => \Str::limit($question, 80)]);
        }
    }

    private function buildContext(array $results): string
    {
        $context = "Reference Materials:\n\n";

        foreach ($results as $result) {
            $chunk = $result['chunk'];
            $docTitle = $chunk->document->title ?? 'Unknown';
            $context .= "--- [{$docTitle}, Page {$chunk->page_number}] ---\n";
            $context .= $chunk->content . "\n\n";
        }

        return $context;
    }

    private function extractSources(array $results): array
    {
        $sources = [];
        $seen = [];

        foreach ($results as $result) {
            $chunk = $result['chunk'];
            $key = $chunk->document_id . '-' . $chunk->page_number;
            if (!isset($seen[$key])) {
                $sources[] = [
                    'document_id' => $chunk->document_id,
                    'title' => $chunk->document->title ?? 'Unknown',
                    'page_number' => $chunk->page_number,
                    'score' => round($result['score'], 4),
                ];
                $seen[$key] = true;
            }
        }

        return $sources;
    }

    private function buildMessages(object $history, string $context, string $question): array
    {
        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
            ['role' => 'system', 'content' => $context],
        ];

        foreach ($history as $msg) {
            $messages[] = ['role' => $msg->role, 'content' => $msg->content];
        }

        $messages[] = ['role' => 'user', 'content' => $question];

        return $messages;
    }
}
```

### Step 2: Write tests and commit

```bash
git add app/Services/MindpalChatService.php tests/Feature/Mindpal/
git commit -m "feat(mindpal): add RAG chat service with streaming support"
```

---

## Task 4: Admin Dashboard (Livewire Volt)

**Files:**
- Create: `resources/views/livewire/admin/mindpal-documents.blade.php`
- Modify: `routes/web.php` — add admin mindpal route
- Modify: `resources/views/components/layouts/app/sidebar.blade.php` — add admin link
- Create: `tests/Feature/Mindpal/MindpalAdminTest.php`

### Step 1: Create Volt component

Admin page for uploading and managing PDFs. Uses `WithFileUploads` trait for PDF upload, dispatches `ProcessMindpalDocumentJob` on upload. Displays list of documents with status badges (processing/ready/failed), delete action.

Key features:
- File upload (PDF only, max 50MB)
- Title input
- Document list with status, page count, chunk count
- Delete button with confirm
- Re-process failed documents

### Step 2: Add route

In `routes/web.php`, add to admin group:
```php
Volt::route('mindpal', 'admin.mindpal-documents')->name('admin.mindpal');
```

### Step 3: Add sidebar link

Add to admin sidebar (near Password Vault):
```blade
<flux:navlist.item icon="cpu-chip" href="/admin/mindpal">
    {{ __('MindPal') }}
</flux:navlist.item>
```

### Step 4: Write tests

Test admin can upload PDF, view documents, delete documents. Test non-admin forbidden.

### Step 5: Commit

```bash
git commit -m "feat(mindpal): add admin PDF management page (Volt)"
```

---

## Task 5: Student Chat Controllers + Routes

**Files:**
- Create: `app/Http/Controllers/StudentPortal/MindpalController.php`
- Modify: `routes/web.php` — add student mindpal routes
- Create: `tests/Feature/Mindpal/MindpalStudentTest.php`

### Step 1: Create MindpalController

```php
<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\MindpalConversation;
use App\Models\MindpalDocument;
use App\Services\MindpalChatService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MindpalController extends Controller
{
    public function index(Request $request): Response
    {
        $conversations = MindpalConversation::query()
            ->forUser($request->user()->id)
            ->latest('updated_at')
            ->limit(50)
            ->get(['id', 'title', 'updated_at']);

        return Inertia::render('Mindpal', [
            'conversations' => $conversations,
            'documentsCount' => MindpalDocument::ready()->count(),
        ]);
    }

    public function show(Request $request, MindpalConversation $conversation): Response
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'sources', 'created_at']);

        $conversations = MindpalConversation::query()
            ->forUser($request->user()->id)
            ->latest('updated_at')
            ->limit(50)
            ->get(['id', 'title', 'updated_at']);

        return Inertia::render('Mindpal', [
            'conversations' => $conversations,
            'activeConversation' => $conversation->only('id', 'title'),
            'messages' => $messages,
            'documentsCount' => MindpalDocument::ready()->count(),
        ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $conversation = MindpalConversation::create([
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['id' => $conversation->id]);
    }

    public function send(Request $request, MindpalConversation $conversation, MindpalChatService $chatService): StreamedResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        $request->validate(['message' => 'required|string|max:2000']);

        return response()->stream(function () use ($conversation, $request, $chatService) {
            $generator = $chatService->streamAsk($conversation, $request->input('message'));

            foreach ($generator as $token) {
                echo "data: " . json_encode(['token' => $token]) . "\n\n";
                ob_flush();
                flush();
            }

            echo "data: [DONE]\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function destroy(Request $request, MindpalConversation $conversation): \Illuminate\Http\RedirectResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);
        $conversation->delete();

        return redirect()->route('student.mindpal')->with('success', 'Conversation deleted.');
    }
}
```

### Step 2: Add routes

In `routes/web.php`, add to student group:
```php
Route::get('mindpal', [StudentPortal\MindpalController::class, 'index'])->name('mindpal');
Route::get('mindpal/{conversation}', [StudentPortal\MindpalController::class, 'show'])->name('mindpal.show');
Route::post('mindpal/conversations', [StudentPortal\MindpalController::class, 'store'])->name('mindpal.store');
Route::post('mindpal/{conversation}/send', [StudentPortal\MindpalController::class, 'send'])->name('mindpal.send');
Route::delete('mindpal/{conversation}', [StudentPortal\MindpalController::class, 'destroy'])->name('mindpal.destroy');
```

### Step 3: Write tests and commit

```bash
git commit -m "feat(mindpal): add student chat controllers and routes"
```

---

## Task 6: Student Chat React Page

**Files:**
- Create: `resources/js/student/pages/Mindpal.jsx`
- Modify: `resources/js/student/layouts/StudentLayout.jsx` — add MindPal nav item

### Step 1: Create Mindpal.jsx

Build the chat interface following the student portal's glassmorphism style:
- **Left sidebar**: Conversation list with "New Chat" button, search, delete
- **Main area**: Chat messages (user right-aligned, assistant left-aligned), streaming response display
- **Bottom input**: Text input + send button
- **Source citations**: Rendered as small badges below assistant messages (book title + page)
- **Streaming**: Use EventSource (SSE) to receive tokens and append to the current assistant message
- **Mobile**: Sidebar hidden by default, toggle via hamburger icon

Key React patterns:
- Use `useForm` from `@inertiajs/react` for new conversation creation
- Use `fetch` with `ReadableStream` for SSE streaming
- Use `useRef` for auto-scroll to bottom on new messages
- Use `useState` for streaming state management

### Step 2: Add navigation item

In `StudentLayout.jsx`, add MindPal to nav items:
```jsx
{ label: 'MindPal', href: '/my/mindpal', icon: Brain, match: (p) => p.startsWith('/my/mindpal') },
```

Import `Brain` from `lucide-react`.

### Step 3: Build and test visually

```bash
npm run build
```

### Step 4: Commit

```bash
git commit -m "feat(mindpal): add student chat React page with streaming UI"
```

---

## Task 7: Rate Limiting + Polish + Final Tests

**Files:**
- Modify: `routes/web.php` — add throttle middleware to send route
- Create: `tests/Feature/Mindpal/MindpalIntegrationTest.php`

### Step 1: Add rate limiting

Add throttle middleware to the send route:
```php
Route::post('mindpal/{conversation}/send', [...])->middleware('throttle:50,60')->name('mindpal.send');
```

### Step 2: Write integration tests

- Student can create conversation
- Student can view own conversation
- Student cannot view others' conversation (403)
- Admin can upload PDF
- Admin can delete document
- Non-admin cannot access admin mindpal
- Rate limiting works (50 messages/hour)

### Step 3: Run full test suite

```bash
php artisan test --compact tests/Feature/Mindpal/
```

### Step 4: Run Pint

```bash
vendor/bin/pint --dirty
```

### Step 5: Final commit

```bash
git commit -m "feat(mindpal): add rate limiting, integration tests, and polish"
```

---

## Summary

| Task | Description | Key Files |
|------|-------------|-----------|
| 1 | Dependencies + Database + Models | migrations, 4 models, composer packages |
| 2 | PDF Processing + Embedding Service + Job | MindpalPdfService, MindpalEmbeddingService, ProcessMindpalDocumentJob |
| 3 | RAG Chat Service | MindpalChatService (core RAG engine with streaming) |
| 4 | Admin Dashboard (Volt) | PDF upload/manage page, sidebar link |
| 5 | Student Chat Controllers + Routes | MindpalController, SSE streaming endpoint |
| 6 | Student Chat React Page | Mindpal.jsx, nav item addition |
| 7 | Rate Limiting + Polish + Tests | Throttle, integration tests, Pint |
