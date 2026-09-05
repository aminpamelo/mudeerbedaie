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

            // Function-calling loop: the model may call tools (check order,
            // search products); we run them and feed results back until it
            // produces a final text answer.
            for ($round = 0; $round < 4; $round++) {
                $response = OpenAI::chat()->create([
                    'model' => config('openai.model', 'gpt-4o-mini'),
                    'messages' => $messages,
                    'tools' => $this->tools(),
                    'temperature' => 0.4,
                    'max_tokens' => 600,
                ]);

                $choice = $response->choices[0]->message;
                $toolCalls = $choice->toolCalls ?? [];

                if (empty($toolCalls)) {
                    $content = trim((string) ($choice->content ?? ''));

                    return $content !== '' ? $content : null;
                }

                $messages[] = [
                    'role' => 'assistant',
                    'content' => $choice->content ?? '',
                    'tool_calls' => array_map(fn ($tc) => [
                        'id' => $tc->id,
                        'type' => 'function',
                        'function' => ['name' => $tc->function->name, 'arguments' => $tc->function->arguments],
                    ], $toolCalls),
                ];

                foreach ($toolCalls as $tc) {
                    $args = json_decode($tc->function->arguments, true) ?: [];
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $tc->id,
                        'content' => $this->runTool($tc->function->name, $args),
                    ];
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('Cekbot AI reply failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Tools the sales/service AI can call.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tools(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'check_order_status',
                    'description' => 'Semak status pesanan & penghantaran pelanggan menggunakan nombor pesanan.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'order_number' => ['type' => 'string', 'description' => 'Nombor pesanan, cth: ORD123'],
                        ],
                        'required' => ['order_number'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Cari produk yang dijual (nama, harga, link, penerangan) mengikut kata kunci.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Kata kunci produk, cth: kurma'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function runTool(string $name, array $args): string
    {
        return match ($name) {
            'check_order_status' => $this->toolCheckOrder((string) ($args['order_number'] ?? '')),
            'search_products' => $this->toolSearchProducts((string) ($args['query'] ?? '')),
            default => json_encode(['error' => 'Tool tidak dikenali.']),
        };
    }

    private function toolCheckOrder(string $orderNumber): string
    {
        $orderNumber = trim($orderNumber);

        if ($orderNumber === '') {
            return json_encode(['error' => 'Nombor pesanan diperlukan.']);
        }

        $order = \App\Models\ProductOrder::query()
            ->where('order_number', $orderNumber)
            ->orWhere('platform_order_number', $orderNumber)
            ->latest('id')
            ->first();

        if (! $order) {
            return json_encode(['found' => false, 'message' => 'Pesanan tidak dijumpai.'], JSON_UNESCAPED_UNICODE);
        }

        return json_encode([
            'found' => true,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'total' => $order->total_amount,
            'currency' => $order->currency,
            'tracking_id' => $order->tracking_id,
            'tracking_url' => $order->tracking_id ? 'https://www.tracking.my/instant/'.rawurlencode($order->tracking_id) : null,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function toolSearchProducts(string $query): string
    {
        $query = trim($query);

        $products = CekbotProduct::query()
            ->active()
            ->when($query !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('name', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%")))
            ->orderBy('sort_order')
            ->limit(8)
            ->get();

        return json_encode($products->map(fn (CekbotProduct $p) => [
            'name' => $p->name,
            'price' => $p->price,
            'currency' => $p->currency,
            'url' => $p->url,
            'description' => \Illuminate\Support\Str::limit((string) $p->description, 300),
        ])->all(), JSON_UNESCAPED_UNICODE);
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
