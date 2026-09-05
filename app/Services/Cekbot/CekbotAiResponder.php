<?php

namespace App\Services\Cekbot;

use App\Models\CekbotBotSetting;
use App\Models\CekbotConversation;
use App\Models\CekbotMessage;
use App\Models\CekbotProduct;
use App\Models\MindpalChunk;
use App\Services\MindpalEmbeddingService;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * Generates an AI reply for an incoming WhatsApp message (Fasa 4).
 *
 * Grounds answers in the shared "Tanya Ilmu" knowledge base (reusing MindPal's
 * embedding retrieval) when documents exist, but keeps replies short and
 * WhatsApp-appropriate — no citations, same language as the customer.
 */
class CekbotAiResponder
{
    private const BASE_PROMPT = <<<'PROMPT'
Anda pembantu WhatsApp untuk khidmat pelanggan sebuah syarikat. Balas dengan ringkas, mesra dan profesional.
Peraturan:
1. Jawab dalam bahasa yang sama seperti pelanggan (Melayu/Inggeris).
2. Ringkas — sesuai untuk WhatsApp (elakkan jawapan terlalu panjang).
3. Guna maklumat konteks yang diberi bila relevan. Jangan reka fakta.
4. Jika tidak tahu atau di luar skop, minta pelanggan tunggu ejen manusia dengan sopan.
5. Jangan sertakan petikan sumber atau nombor muka surat.
6. WhatsApp tidak menyokong markdown — kongsi link sebagai URL penuh biasa (cth: https://contoh.com/produk), JANGAN guna format [teks](url). Untuk tebal guna *bintang*.
PROMPT;

    public function __construct(private MindpalEmbeddingService $embeddings) {}

    public function reply(CekbotConversation $conversation, string $message, CekbotBotSetting $settings): ?string
    {
        if (blank(config('openai.api_key'))) {
            return null;
        }

        try {
            $messages = $this->buildMessages($conversation, $message, $settings);

            $response = OpenAI::chat()->create([
                'model' => config('openai.model', 'gpt-4o-mini'),
                'messages' => $messages,
                'temperature' => 0.4,
                'max_tokens' => 500,
            ]);

            $content = trim((string) ($response->choices[0]->message->content ?? ''));

            return $content !== '' ? $content : null;
        } catch (\Throwable $e) {
            Log::warning('Cekbot AI reply failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function buildMessages(CekbotConversation $conversation, string $message, CekbotBotSetting $settings): array
    {
        $system = self::BASE_PROMPT;
        if (filled($settings->ai_system_prompt)) {
            $system .= "\n\nArahan tambahan:\n".$settings->ai_system_prompt;
        }

        $messages = [['role' => 'system', 'content' => $system]];

        $context = $this->retrieveContext($message);
        if ($context !== null) {
            $messages[] = ['role' => 'system', 'content' => "Konteks daripada pangkalan pengetahuan:\n\n{$context}"];
        }

        $products = $this->productContext();
        if ($products !== null) {
            $messages[] = ['role' => 'system', 'content' => $products];
        }

        foreach ($this->history($conversation) as $entry) {
            $messages[] = $entry;
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }

    /**
     * Retrieve knowledge-base context via MindPal embeddings, or null when there
     * is no knowledge base / retrieval fails.
     */
    private function retrieveContext(string $message): ?string
    {
        if (! MindpalChunk::query()->exists()) {
            return null;
        }

        try {
            $embedding = $this->embeddings->embed($message);
            $chunks = $this->embeddings->findSimilarChunks($embedding, 3);

            if (empty($chunks)) {
                return null;
            }

            $parts = array_map(function ($item) {
                $chunk = $item['chunk'];

                return trim((string) $chunk->content);
            }, $chunks);

            return implode("\n\n---\n\n", array_filter($parts));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Build the sales product-knowledge context, or null when there are no
     * active products.
     */
    private function productContext(): ?string
    {
        $products = CekbotProduct::query()
            ->active()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        if ($products->isEmpty()) {
            return null;
        }

        $lines = $products->values()
            ->map(fn (CekbotProduct $p, int $i) => ($i + 1).'. '.$p->toContextLine())
            ->implode("\n");

        return 'Senarai produk syarikat (untuk jualan). Bila pelanggan tanya tentang produk, harga atau nak beli, '
            ."guna senarai ini, cadangkan produk yang sesuai, dan kongsi link bila ada:\n\n".$lines;
    }

    /**
     * Recent conversation turns mapped to chat roles (in => user, out => assistant).
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function history(CekbotConversation $conversation): array
    {
        return $conversation->messages()
            ->whereNotNull('body')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->reverse()
            ->map(fn (CekbotMessage $m) => [
                'role' => $m->direction === CekbotMessage::DIRECTION_OUT ? 'assistant' : 'user',
                'content' => (string) $m->body,
            ])
            ->values()
            ->all();
    }
}
