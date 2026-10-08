<?php

namespace App\Services\Cekbot;

use App\Models\CekbotClosingReference;
use App\Models\CekbotConversation;
use App\Models\CekbotFlow;
use App\Models\CekbotFlowEnrollment;
use App\Models\CekbotFlowPackage;
use App\Models\CekbotMedia;
use App\Models\CekbotMessage;
use App\Models\ProductOrder;
use Illuminate\Support\Collection;
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
    /** Most media items listed to the model, to keep the prompt bounded. */
    private const MEDIA_LIMIT = 50;

    /** @var Collection<int, CekbotMedia>|null */
    private ?Collection $mediaLibrary = null;

    public function __construct(private CekbotFlowOrderCreator $orders) {}

    /**
     * Produce the next reply for an active flow conversation. Creates the order
     * as a side effect when the model calls create_order with valid details.
     *
     * @param  array{proof_received?: bool, qr_sent?: bool}  $context  Funnel state the model can't see in the chat text.
     * @return array{reply: ?string, order: ?ProductOrder, send_qr: bool, media: array<int, CekbotMedia>}
     */
    public function respond(CekbotFlow $flow, CekbotConversation $conversation, string $message, array $context = []): array
    {
        if (blank(config('openai.api_key'))) {
            return ['reply' => null, 'order' => null, 'send_qr' => false, 'media' => []];
        }

        $flow->loadMissing(['packages.cekbotProduct', 'packages.product', 'packages.shopPackage']);

        $this->mediaLibrary = null;

        try {
            $messages = $this->buildMessages($flow, $conversation, $message, $context);
            $createdOrder = null;
            $sendQr = false;
            /** @var array<string, CekbotMedia> $media */
            $media = [];

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

                    return ['reply' => $content !== '' ? $content : null, 'order' => $createdOrder, 'send_qr' => $sendQr, 'media' => array_values($media)];
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

                    if ($tc->function->name === 'send_payment_qr') {
                        $sendQr = $this->offersQr($flow);
                        $result = json_encode($sendQr
                            ? ['success' => true, 'instruction' => 'Gambar QR akan dihantar selepas mesej anda. Beritahu pelanggan QR dihantar & boleh scan untuk bayar.']
                            : ['success' => false, 'error' => 'Tiada QR untuk flow ini.'], JSON_UNESCAPED_UNICODE);
                        $order = null;
                    } elseif ($tc->function->name === 'send_media') {
                        $item = $this->mediaLibrary()->firstWhere('key', trim((string) ($args['key'] ?? '')));
                        if ($item) {
                            $media[$item->key] = $item;
                        }
                        $result = json_encode($item
                            ? ['success' => true, 'instruction' => 'Media "'.$item->key.'" akan dihantar selepas mesej anda. Jangan tulis link atau nama fail; cukup sebut ringkas (cth "Ni testimoni pelanggan kami 👇").']
                            : ['success' => false, 'error' => 'Media tidak wujud. Guna salah satu key dalam senarai MEDIA sahaja.'], JSON_UNESCAPED_UNICODE);
                        $order = null;
                    } else {
                        [$result, $order] = $this->runTool($flow, $conversation, $tc->function->name, $args, $context);
                    }

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

            return ['reply' => null, 'order' => $createdOrder, 'send_qr' => $sendQr, 'media' => array_values($media)];
        } catch (\Throwable $e) {
            Log::warning('Cekbot flow agent failed', ['flow_id' => $flow->id, 'error' => $e->getMessage()]);

            return ['reply' => null, 'order' => null, 'send_qr' => false, 'media' => []];
        }
    }

    /**
     * Whether this flow has a transfer QR/bank poster the agent may send.
     */
    private function offersQr(CekbotFlow $flow): bool
    {
        return $flow->payment_transfer_enabled && filled($flow->bank_image);
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

        $tools = [[
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

        if ($this->offersQr($flow)) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'send_payment_qr',
                    'description' => 'Hantar gambar QR / poster bank kepada pelanggan. Panggil HANYA bila pelanggan minta QR, atau nak bayar transfer dan belum terima QR. Jangan panggil bila pelanggan kata dah bayar.',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass],
                ],
            ];
        }

        $library = $this->mediaLibrary();
        if ($library->isNotEmpty()) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => 'send_media',
                    'description' => 'Hantar satu gambar/video dari pustaka MEDIA kepada pelanggan (cth testimoni). Panggil bila arahan syarikat suruh hantar media tertentu, atau bila media itu jelas membantu jawab pelanggan. Boleh panggil beberapa kali untuk beberapa media. Jangan hantar media yang sama berulang kali.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => ['type' => 'string', 'enum' => $library->pluck('key')->values()->all(), 'description' => 'Key media dari senarai MEDIA.'],
                        ],
                        'required' => ['key'],
                    ],
                ],
            ];
        }

        return $tools;
    }

    /**
     * The shared media library the agent may send from (loaded once per turn).
     *
     * @return Collection<int, CekbotMedia>
     */
    private function mediaLibrary(): Collection
    {
        return $this->mediaLibrary ??= CekbotMedia::query()
            ->with('media')
            ->whereHas('media', fn ($query) => CekbotMedia::constrainToSendable($query))
            ->orderBy('key')
            ->limit(self::MEDIA_LIMIT)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{0: string, 1: ?ProductOrder}
     */
    private function runTool(CekbotFlow $flow, CekbotConversation $conversation, string $name, array $args, array $context = []): array
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
            'package_id' => $package->shop_package_id,
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

        if ($method === CekbotFlowEnrollment::PAYMENT_TRANSFER) {
            $result['instruction'] = 'Sahkan pesanan berjaya, beri no. pesanan'
                .(filled($flow->bank_details) ? ', dan beri maklumat bank ini untuk pelanggan buat pembayaran transfer' : '')
                .'. Minta pelanggan hantar gambar/screenshot resit di chat ini selepas transfer supaya team boleh sahkan pembayaran.';

            if (filled($flow->bank_details)) {
                $result['bank_details'] = $flow->bank_details;
            }

            if ($context['proof_received'] ?? false) {
                unset($result['bank_details']);
                $result['instruction'] = 'Sahkan pesanan berjaya & beri no. pesanan. Resit bayaran pelanggan SUDAH diterima tadi — JANGAN minta resit atau beri maklumat bank lagi. Maklumkan team kami akan semak & sahkan bayaran, dan akan maklumkan di sini.';
            }
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
    private function buildMessages(CekbotFlow $flow, CekbotConversation $conversation, string $message, array $context = []): array
    {
        $system = $this->systemPrompt($flow);

        if ($context['proof_received'] ?? false) {
            $system .= "\n\nSTATUS SEMASA: Pelanggan SUDAH hantar resit/bukti bayaran (gambar) dalam chat ini. JANGAN minta resit lagi. Terima kasih, dan teruskan kumpul maklumat yang belum ada untuk cipta pesanan.";
        }

        if ($context['qr_sent'] ?? false) {
            $system .= "\n\nSTATUS SEMASA: Gambar QR bayaran SUDAH dihantar dalam chat ini. Jangan hantar semula melainkan pelanggan minta secara jelas.";
        }

        $messages = [['role' => 'system', 'content' => $system]];

        if ($references = CekbotClosingReference::promptFor($flow->id)) {
            $messages[] = ['role' => 'system', 'content' => $references];
        }

        $history = $this->history($conversation);
        foreach ($history as $entry) {
            $messages[] = $entry;
        }

        // Ensure the current message is present (it usually is the last history
        // turn already, since it's stored before the bot runs).
        $last = end($history);
        if ($last === false || ! str_ends_with(trim((string) $last['content']), trim($message))) {
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

        if ($flow->payment_transfer_enabled && filled($flow->bank_details)) {
            $lines[] = '';
            $lines[] = 'MAKLUMAT BANK (boleh diberi terus bila pelanggan tanya):';
            $lines[] = trim((string) $flow->bank_details);
        }

        if ($this->offersQr($flow)) {
            $lines[] = '';
            $lines[] = 'QR BAYARAN: Ada. Bila pelanggan tanya QR atau nak scan untuk bayar, panggil fungsi send_payment_qr. JANGAN kata tiada QR.';
        }

        $library = $this->mediaLibrary();
        if ($library->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'MEDIA (gambar/video yang boleh dihantar dengan fungsi send_media, ikut key):';
            foreach ($library as $item) {
                $lines[] = '- '.$item->key.' ('.($item->isVideo() ? 'video' : 'gambar').')'
                    .(filled($item->title) ? ': '.$item->title : '')
                    .(filled($item->description) ? ' — '.Str::limit(trim((string) $item->description), 200) : '');
            }
            $lines[] = 'Bila arahan syarikat sebut key media (cth "hantar testimoni-1"), letak media itu pada kedudukannya dengan menulis [[MEDIA:key]] pada baris sendiri dalam balasan (cth [[MEDIA:testimoni-1]]). Sistem akan tukar baris itu kepada gambar/video. Jangan tulis link.';
        }

        $askAddress = $flow->payment_cod_enabled;

        $lines[] = '';
        $lines[] = 'PERATURAN PENTING:';
        $lines[] = '1. Balas dalam bahasa yang sama seperti pelanggan (Melayu/Inggeris). Ringkas & mesra — sesuai WhatsApp. Tiada markdown; guna *bintang* untuk tebal, kongsi link sebagai URL penuh.';
        $lines[] = '2. JANGAN paksa pelanggan balas dengan nombor. Faham maksud & kehendak mereka daripada ayat biasa.';
        $lines[] = '3. Jawab soalan produk guna maklumat pakej di atas sahaja. Jangan reka fakta; jika tak pasti, cakap anda akan semak.';
        $lines[] = '4. Bila jelas pelanggan berminat dengan satu pakej, sahkan pakej & harganya, kemudian tanya cara bayar (jika ada lebih satu pilihan dan belum jelas).';
        $lines[] = '5. Selepas cara bayar dipilih, minta maklumat SEPERTI BORANG dalam satu mesej: Nama penuh, No. telefon'.($askAddress ? ', dan Alamat penghantaran penuh' : '').'.';
        $lines[] = '6. BACA jawapan pelanggan. Jika mana-mana maklumat tak lengkap atau tiada (contoh: no. telefon tidak diberi), minta secara spesifik maklumat yang tiada itu sahaja.';
        $lines[] = '7. SEBELUM cipta pesanan, RINGKASKAN semua maklumat (pakej, harga, cara bayar, nama, no. telefon'.($askAddress ? ', alamat' : '').') dan MINTA pelanggan sahkan — contoh: "Betul semua ni? 🙂".';
        $lines[] = '8. HANYA selepas pelanggan sahkan betul ("betul"/"ya"/"ok"), panggil fungsi create_order.';
        $lines[] = '9. Selepas order dicipta, ucap terima kasih & beri no. pesanan. Untuk transfer, beri maklumat bank & minta pelanggan hantar gambar/screenshot resit di chat ini selepas bayar, supaya team boleh sahkan.';
        $lines[] = '10. Jika pelanggan tanya maklumat bank / nama bank / QR pada bila-bila masa, jawab terus — jangan tahan sehingga borang lengkap. Selepas itu teruskan kumpul maklumat pesanan.';
        $lines[] = '11. Untuk hantar beberapa mesej (bubble) berasingan dalam satu giliran, pisahkan dengan [[SPLIT]]. Sistem akan hantar setiap bahagian sebagai mesej WhatsApp sendiri. Jangan tulis [[SPLIT]] untuk tujuan lain.';

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

            $shopPackage = $p->shopPackage;
            $about = trim(strip_tags((string) ($shopPackage?->short_description ?: $shopPackage?->description)));
            if ($about !== '') {
                $parts[] = '   '.Str::limit($about, 500);
            }

            return implode("\n", $parts);
        })->implode("\n");
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function history(CekbotConversation $conversation): array
    {
        return $conversation->botContextMessages()
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->reverse()
            ->map(fn (CekbotMessage $m) => [
                'role' => $m->direction === CekbotMessage::DIRECTION_OUT ? 'assistant' : 'user',
                'content' => $this->historyContent($m),
            ])
            ->filter(fn (array $entry) => $entry['content'] !== '')
            ->values()
            ->all();
    }

    /**
     * Text the model sees for a past message. Media has no body of its own, so
     * it's described — otherwise a receipt photo is invisible to the model.
     */
    private function historyContent(CekbotMessage $message): string
    {
        $body = trim((string) $message->body);

        if (! in_array($message->type, ['image', 'document'], true)) {
            return $body;
        }

        $label = $message->direction === CekbotMessage::DIRECTION_OUT
            ? '[Gambar dihantar]'
            : '[Pelanggan hantar '.($message->type === 'image' ? 'gambar' : 'dokumen').' — kemungkinan resit bayaran]';

        return trim($label.' '.$body);
    }

    private function resolvePackage(CekbotFlow $flow, string $needle): ?CekbotFlowPackage
    {
        $needle = $this->normaliseLabel($needle);
        if ($needle === '') {
            return $flow->packages->count() === 1 ? $flow->packages->first() : null;
        }

        // Exact, then contains either direction.
        foreach ($flow->packages as $package) {
            if ($this->normaliseLabel($package->label) === $needle) {
                return $package;
            }
        }

        foreach ($flow->packages as $package) {
            $label = $this->normaliseLabel($package->label);
            if ($label !== '' && (str_contains($label, $needle) || str_contains($needle, $label))) {
                return $package;
            }
        }

        return $flow->packages->count() === 1 ? $flow->packages->first() : null;
    }

    /**
     * Compare labels loosely: the model re-types them with single spaces and
     * WhatsApp markdown, so "*Pakej  1 Buku*" must match "Pakej 1 Buku".
     */
    private function normaliseLabel(?string $label): string
    {
        return mb_strtolower(Str::squish(str_replace(['*', '_', '~', '`'], ' ', (string) $label)));
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
