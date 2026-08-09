<?php

namespace App\Services;

use App\Models\MindpalConversation;
use App\Models\MindpalMessage;
use Generator;
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

    public function __construct(public MindpalEmbeddingService $embeddingService) {}

    /**
     * Non-streaming version: ask a question and get the full response.
     *
     * @return array{content: string, sources: array<int, array<string, mixed>>}
     */
    public function ask(MindpalConversation $conversation, string $question): array
    {
        $queryEmbedding = $this->embeddingService->embed($question);
        $similarChunks = $this->embeddingService->findSimilarChunks($queryEmbedding, 5);

        $context = $this->buildContext($similarChunks);
        $history = $this->getConversationHistory($conversation);
        $messages = $this->buildMessages($context, $history, $question);

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => 2000,
        ]);

        $assistantContent = $response->choices[0]->message->content;
        $tokensUsed = $response->usage->totalTokens ?? null;

        $sources = $this->extractSources($similarChunks);

        // Save user message
        MindpalMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $question,
        ]);

        // Save assistant message
        MindpalMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $assistantContent,
            'sources' => $sources,
            'tokens_used' => $tokensUsed,
        ]);

        // Auto-title from first question
        $this->autoTitleConversation($conversation, $question);

        return [
            'content' => $assistantContent,
            'sources' => $sources,
        ];
    }

    /**
     * Streaming version: yields tokens one by one.
     *
     * @return Generator<int, string>
     */
    public function streamAsk(MindpalConversation $conversation, string $question): Generator
    {
        $queryEmbedding = $this->embeddingService->embed($question);
        $similarChunks = $this->embeddingService->findSimilarChunks($queryEmbedding, 5);

        $context = $this->buildContext($similarChunks);
        $history = $this->getConversationHistory($conversation);
        $messages = $this->buildMessages($context, $history, $question);

        $stream = OpenAI::chat()->createStreamed([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => 2000,
        ]);

        $fullResponse = '';

        foreach ($stream as $response) {
            $token = $response->choices[0]->delta->content ?? '';

            if ($token !== '') {
                $fullResponse .= $token;
                yield $token;
            }
        }

        $sources = $this->extractSources($similarChunks);

        // Save user message
        MindpalMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $question,
        ]);

        // Save assistant message after streaming completes
        MindpalMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $fullResponse,
            'sources' => $sources,
        ]);

        // Auto-title from first question
        $this->autoTitleConversation($conversation, $question);
    }

    /**
     * Build context string from retrieved chunks with source metadata.
     *
     * @param  array<int, array{chunk: \App\Models\MindpalChunk, score: float}>  $chunks
     */
    private function buildContext(array $chunks): string
    {
        if (empty($chunks)) {
            return 'No relevant context found in the knowledge base.';
        }

        $contextParts = [];

        foreach ($chunks as $i => $item) {
            $chunk = $item['chunk'];
            $documentTitle = $chunk->document->title ?? 'Unknown Document';
            $pageNumber = $chunk->page_number;
            $score = round($item['score'], 3);

            $contextParts[] = sprintf(
                "[Source %d: %s, Page %d (relevance: %s)]\n%s",
                $i + 1,
                $documentTitle,
                $pageNumber,
                $score,
                $chunk->content
            );
        }

        return implode("\n\n---\n\n", $contextParts);
    }

    /**
     * Get the last 10 messages from conversation history.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function getConversationHistory(MindpalConversation $conversation): array
    {
        return $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse()
            ->map(fn (MindpalMessage $msg) => [
                'role' => $msg->role,
                'content' => $msg->content,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Build the full messages array for the OpenAI API.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<int, array{role: string, content: string}>
     */
    private function buildMessages(string $context, array $history, string $question): array
    {
        $messages = [];

        // System prompt
        $messages[] = [
            'role' => 'system',
            'content' => self::SYSTEM_PROMPT,
        ];

        // Context from retrieved chunks
        $messages[] = [
            'role' => 'system',
            'content' => "Here is the relevant context from the knowledge base:\n\n{$context}",
        ];

        // Conversation history
        foreach ($history as $msg) {
            $messages[] = $msg;
        }

        // Current question
        $messages[] = [
            'role' => 'user',
            'content' => $question,
        ];

        return $messages;
    }

    /**
     * Extract source metadata from similar chunks.
     *
     * @param  array<int, array{chunk: \App\Models\MindpalChunk, score: float}>  $chunks
     * @return array<int, array<string, mixed>>
     */
    private function extractSources(array $chunks): array
    {
        return array_map(fn ($item) => [
            'document_id' => $item['chunk']->document_id,
            'document_title' => $item['chunk']->document->title ?? 'Unknown',
            'page_number' => $item['chunk']->page_number,
            'chunk_index' => $item['chunk']->chunk_index,
            'score' => round($item['score'], 4),
        ], $chunks);
    }

    /**
     * Auto-title the conversation from the first question if untitled.
     */
    private function autoTitleConversation(MindpalConversation $conversation, string $question): void
    {
        if ($conversation->title !== null && $conversation->title !== 'New Conversation') {
            return;
        }

        // Use first 80 chars of the question as title
        $title = mb_strlen($question) > 80
            ? mb_substr($question, 0, 77).'...'
            : $question;

        $conversation->update(['title' => $title]);
    }
}
