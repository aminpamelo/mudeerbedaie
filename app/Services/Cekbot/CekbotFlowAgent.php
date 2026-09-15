<?php

namespace App\Services\Cekbot;

use App\Models\CekbotConversation;
use App\Models\CekbotFlow;
use App\Models\CekbotFlowEnrollment;
use App\Models\CekbotFlowPackage;
use App\Models\CekbotMessage;
use App\Models\ProductOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * AI sales agent for a Cekbot flow.
 *
 * Runs the whole funnel conversationally: understands the customer's intent in
 * natural language (no forced numbered replies), answers product questions,
 * guides them to a package + payment method, collects & validates the order
 * form (name, phone, address for COD), asks for confirmation, and only then
 * calls create_order. Falls back silently when OpenAI is unavailable — the
 * caller uses the deterministic engine instead.
 */
class CekbotFlowAgent
{
    public function __construct(private CekbotFlowOrderCreator $orders) {}

    /**
     * Produce the next reply for an active flow conversation. Creates the order
     * as a side effect when the model calls create_order with valid details.
     *
     * @return array{reply: ?string, order: ?ProductOrder}
     */
    public function respond(CekbotFlow $flow, CekbotConversation $conversation, string $message): array
    {
        if (blank(config('openai.api_key'))) {
            return ['reply' => null, 'order' => null];
        }

        $flow->loadMissing(['packages.cekbotProduct', 'packages.product']);

        try {
            $messages = $this->buildMessages($flow, $conversation, $message);
            $createdOrder = null;

            // Function-calling loop: the model chats, then calls create_order
            // once the customer has confirmed. We run the tool and feed the
            // result back until it produces a final text reply.
            for ($round = 0; $round < 5; $round++) {
                $response = OpenAI::chat()->create([
                    'model' => config('openai.model', 'gpt-4o-mini'),
                    'messages' => $messages,
                    'tools' => $this->tools($flow),
                    'temperature' => 0.4,
                    'max_tokens' => 700,
                ]);

                $choice = $response->choices[0]->message;
                $toolCalls = $choice->toolCalls ?? [];

                if (empty($toolCalls)) {
                    $content = trim((string) ($choice->content ?? ''));

                    return ['reply' => $content !== '' ? $content : null, 'order' => $createdOrder];
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
                    [$result, $order] = $this->runTool($flow, $conversation, $tc->function->name, $args);

                    if ($order) {
                        $createdOrder = $order;
                    }

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $tc->id,
                        'content' => $result,
                    ];
                }
            }

            return ['reply' => null, 'order' => $createdOrder];
        } catch (\Throwable $e) {
            Log::warning('Cekbot flow agent failed', ['flow_id' => $flow->id, 'error' => $e->getMessage()]);

            return ['reply' => null, 'order' => null];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tools(CekbotFlow $flow): array
    {
        $methods = [];
        if (! $flow->ask_payment) {
            $methods = [];
        } else {
            if ($flow->payment_transfer_enabled) {
                $methods[] = 'transfer';
            }
            if ($flow->payment_cod_enabled) {
                $methods[] = 'cod';
            }
        }

        $paymentSchema = ['type' => 'string', 'description' => 'Cara bayar pelanggan pilih.'];
        if (! empty($methods)) {
            $paymentSchema['enum'] = $methods;
        }

        return [[
            'type' => 'function',
            'function' => [
                'name' => 'create_order',
                'description' => 'Cipta pesanan dalam sistem. HANYA panggil SELEPAS pelanggan mengesahkan semua maklumat (pakej, cara bayar, nama, no. telefon'.($flow->payment_cod_enabled ? ', dan alamat untuk COD' : '').') adalah betul. Jangan panggil sebelum pengesahan.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => array_filter([
                        'package' => ['type' => 'string', 'description' => 'Nama/label pakej yang pelanggan pilih.'],
                        'payment_method' => $flow->ask_payment ? $paymentSchema : null,
                        'customer_name' => ['type' => 'string', 'description' => 'Nama penuh pelanggan.'],
                        'customer_phone' => ['type' => 'string', 'description' => 'No. telefon pelanggan (yang pelanggan berikan).'],
                        'address' => ['type' => 'string', 'description' => 'Alamat penuh penghantaran (wajib untuk COD).'],
                    ]),
                    'required' => array_values(array_filter([
                        'package',
                        $flow->ask_payment ? 'payment_method' : null,
                        'customer_name',
                        'customer_phone',
                    ])),
                ],
            ],
        ]];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{0: string, 1: ?ProductOrder}
     */
    private function runTool(CekbotFlow $flow, CekbotConversation $conversation, string $name, array $args): array
    {
        if ($name !== 'create_order') {
            return [json_encode(['error' => 'Tool tidak dikenali.']), null];
        }

        $package = $this->resolvePackage($flow, (string) ($args['package'] ?? ''));
        if (! $package) {
            return [$this->toolError('Pakej tidak dikenali. Minta pelanggan sahkan pakej yang mana satu.'), null];
        }

        $method = null;
        if ($flow->ask_payment) {
            $method = $this->normalisePayment((string) ($args['payment_method'] ?? ''));
            if ($method === null) {
                return [$this->toolError('Cara bayar belum jelas. Tanya pelanggan: Transfer atau COD?'), null];
            }
            if ($method === CekbotFlowEnrollment::PAYMENT_TRANSFER && ! $flow->payment_transfer_enabled) {
                return [$this->toolError('Transfer tidak tersedia untuk flow ini.'), null];
            }
            if ($method === CekbotFlowEnrollment::PAYMENT_COD && ! $flow->payment_cod_enabled) {
                return [$this->toolError('COD tidak tersedia untuk flow ini.'), null];
            }
        }

        $customerName = trim((string) ($args['customer_name'] ?? ''));
        if ($customerName === '') {
            return [$this->toolError('Nama pelanggan tiada. Minta nama penuh.'), null];
        }

        $phoneRaw = trim((string) ($args['customer_phone'] ?? ''));
        if (strlen(preg_replace('/\D/', '', $phoneRaw)) < 8) {
            return [$this->toolError('No. telefon tiada atau tidak sah. Minta pelanggan beri no. telefon mereka.'), null];
        }

        $address = trim((string) ($args['address'] ?? ''));
        if ($method === CekbotFlowEnrollment::PAYMENT_COD && $address === '') {
            return [$this->toolError('Alamat penghantaran tiada. Minta alamat penuh untuk COD.'), null];
        }

        $order = $this->orders->create($flow, $conversation, [
            'label' => $package->label,
            'price' => $package->effectivePrice(),
            'currency' => $package->effectiveCurrency(),
            'product_id' => $package->orderProductId(),
            'payment_method' => $method,
            'name' => $customerName,
            'phone' => $phoneRaw,
            'address' => $address ?: null,
            'driver' => 'ai',
        ]);

        $result = [
            'success' => true,
            'order_number' => $order->order_number,
            'package' => $package->label,
            'total' => $this->money($package->effectiveCurrency(), $package->effectivePrice()),
            'instruction' => 'Sahkan pesanan berjaya kepada pelanggan, beri no. pesanan, dan ucap terima kasih.',
        ];

        if ($method === CekbotFlowEnrollment::PAYMENT_TRANSFER && filled($flow->bank_details)) {
            $result['bank_details'] = $flow->bank_details;
            $result['instruction'] = 'Sahkan pesanan berjaya, beri no. pesanan, dan beri maklumat bank ini untuk pelanggan buat pembayaran transfer.';
        }

        if (filled($flow->confirmation_message)) {
            $result['confirmation_style'] = strtr($flow->confirmation_message, [
                '{order_number}' => $order->order_number,
                '{package}' => $package->label,
                '{price}' => $this->money($package->effectiveCurrency(), $package->effectivePrice()),
                '{name}' => $customerName,
            ]);
        }

        return [json_encode($result, JSON_UNESCAPED_UNICODE), $order];
    }

    private function toolError(string $message): string
    {
        return json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function buildMessages(CekbotFlow $flow, CekbotConversation $conversation, string $message): array
    {
        $messages = [['role' => 'system', 'content' => $this->systemPrompt($flow)]];

        $history = $this->history($conversation);
        foreach ($history as $entry) {
            $messages[] = $entry;
        }

        // Ensure the current message is present (it usually is the last history
        // turn already, since it's stored before the bot runs).
        $last = end($history);
        if ($last === false || trim((string) $last['content']) !== trim($message)) {
            $messages[] = ['role' => 'user', 'content' => $message];
        }

        return $messages;
    }

    private function systemPrompt(CekbotFlow $flow): string
    {
        $lines = [];
        $lines[] = 'Anda ejen jualan WhatsApp yang mesra, meyakinkan dan membantu untuk syarikat kami. Matlamat anda: bantu pelanggan pilih pakej yang sesuai, jawab soalan mereka dengan baik, dan bila mereka betul-betul berminat, kumpul maklumat & cipta pesanan.';
        $lines[] = '';
        $lines[] = 'PAKEJ YANG DITAWARKAN:';
        $lines[] = $this->packageContext($flow);

        if (filled($flow->welcome_message)) {
            $lines[] = '';
            $lines[] = 'MAKLUMAT / SKRIP RUJUKAN SYARIKAT (guna untuk terangkan pakej):';
            $lines[] = trim((string) $flow->welcome_message);
        }

        $lines[] = '';
        $lines[] = 'CARA BAYAR:';

        if (! $flow->ask_payment) {
            $lines[] = '- (Tidak perlu tanya cara bayar untuk flow ini.)';
        } else {
            if ($flow->payment_transfer_enabled) {
                $lines[] = '- Transfer / Online Banking';
            }
            if ($flow->payment_cod_enabled) {
                $lines[] = '- COD (bayar semasa terima)';
            }
        }

        $askAddress = $flow->payment_cod_enabled;

        $lines[] = '';
        $lines[] = 'PERATURAN PENTING:';
        $lines[] = '1. Balas dalam bahasa yang sama seperti pelanggan (Melayu/Inggeris). Ringkas & mesra — sesuai WhatsApp. Tiada markdown; guna *bintang* untuk tebal, kongsi link sebagai URL penuh.';
        $lines[] = '2. JANGAN paksa pelanggan balas dengan nombor. Faham maksud & kehendak mereka daripada ayat biasa.';
        $lines[] = '3. Jawab soalan produk guna maklumat pakej di atas sahaja. Jangan reka fakta; jika tak pasti, cakap anda akan semak.';
        $lines[] = '4. Bila jelas pelanggan berminat dengan satu pakej, sahkan pakej & harganya, kemudian tanya cara bayar (jika ada lebih satu pilihan dan belum jelas).';
        $lines[] = '5. Selepas cara bayar dipilih, minta maklumat SEPERTI BORANG dalam satu mesej: Nama penuh, No. telefon'.($askAddress ? ', dan Alamat penuh (untuk COD)' : '').'.';
        $lines[] = '6. BACA jawapan pelanggan. Jika mana-mana maklumat tak lengkap atau tiada (contoh: no. telefon tidak diberi), minta secara spesifik maklumat yang tiada itu sahaja.';
        $lines[] = '7. SEBELUM cipta pesanan, RINGKASKAN semua maklumat (pakej, harga, cara bayar, nama, no. telefon'.($askAddress ? ', alamat' : '').') dan MINTA pelanggan sahkan — contoh: "Betul semua ni? 🙂".';
        $lines[] = '8. HANYA selepas pelanggan sahkan betul ("betul"/"ya"/"ok"), panggil fungsi create_order.';
        $lines[] = '9. Selepas order dicipta, ucap terima kasih & beri no. pesanan. Untuk transfer, beri maklumat bank untuk pelanggan buat bayaran.';

        if (filled($flow->ai_instructions)) {
            $lines[] = '';
            $lines[] = 'ARAHAN TAMBAHAN DARIPADA SYARIKAT:';
            $lines[] = trim($flow->ai_instructions);
        }

        return implode("\n", $lines);
    }

    private function packageContext(CekbotFlow $flow): string
    {
        return $flow->packages->values()->map(function (CekbotFlowPackage $p, int $i) {
            $parts = [($i + 1).'. '.$p->label.' — '.$this->money($p->effectiveCurrency(), $p->effectivePrice())];

            $product = $p->cekbotProduct;
            if ($product) {
                if (filled($product->description)) {
                    $parts[] = '   '.Str::limit(trim((string) $product->description), 300);
                }
                if (filled($product->knowledge)) {
                    $parts[] = '   Info: '.Str::limit(trim((string) $product->knowledge), 1200);
                }
                foreach ($product->faqPairs() as $faq) {
                    $parts[] = '   Soalan: '.$faq['question'].' — Jawapan: '.$faq['answer'];
                }
            }

            return implode("\n", $parts);
        })->implode("\n");
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function history(CekbotConversation $conversation): array
    {
        return $conversation->messages()
            ->whereNotNull('body')
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->reverse()
            ->map(fn (CekbotMessage $m) => [
                'role' => $m->direction === CekbotMessage::DIRECTION_OUT ? 'assistant' : 'user',
                'content' => (string) $m->body,
            ])
            ->values()
            ->all();
    }

    private function resolvePackage(CekbotFlow $flow, string $needle): ?CekbotFlowPackage
    {
        $needle = mb_strtolower(trim($needle));
        if ($needle === '') {
            return $flow->packages->count() === 1 ? $flow->packages->first() : null;
        }

        // Exact, then contains either direction.
        foreach ($flow->packages as $package) {
            if (mb_strtolower(trim($package->label)) === $needle) {
                return $package;
            }
        }

        foreach ($flow->packages as $package) {
            $label = mb_strtolower(trim($package->label));
            if ($label !== '' && (str_contains($label, $needle) || str_contains($needle, $label))) {
                return $package;
            }
        }

        return $flow->packages->count() === 1 ? $flow->packages->first() : null;
    }

    private function normalisePayment(string $value): ?string
    {
        $value = mb_strtolower(trim($value));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, 'cod') || (str_contains($value, 'bayar') && str_contains($value, 'terima'))) {
            return CekbotFlowEnrollment::PAYMENT_COD;
        }

        if (str_contains($value, 'transfer') || str_contains($value, 'bank') || str_contains($value, 'online') || str_contains($value, 'trf')) {
            return CekbotFlowEnrollment::PAYMENT_TRANSFER;
        }

        return null;
    }

    private function money(string $currency, float $amount): string
    {
        $formatted = number_format($amount, 2);

        if (str_ends_with($formatted, '.00')) {
            $formatted = substr($formatted, 0, -3);
        }

        return $currency.$formatted;
    }
}
