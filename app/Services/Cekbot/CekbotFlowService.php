<?php

namespace App\Services\Cekbot;

use App\Models\CekbotConversation;
use App\Models\CekbotFlow;
use App\Models\CekbotFlowEnrollment;
use App\Models\CekbotFlowPackage;
use App\Models\CekbotLeadCategory;
use App\Models\Product;
use App\Models\ProductOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The guided sales-funnel engine (Cekbot Flow).
 *
 * Drives a conversation through a scripted funnel — greet → pick package →
 * choose payment (transfer/COD) → collect details → auto-create a ProductOrder.
 * State lives in one active {@see CekbotFlowEnrollment} per conversation.
 *
 * Runs ahead of the keyword/AI reply engine: when this handles a message it
 * returns true so {@see CekbotBotService} stays silent for that turn.
 */
class CekbotFlowService
{
    private const NUMBER_EMOJI = ['1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣', '6️⃣', '7️⃣', '8️⃣', '9️⃣', '🔟'];

    private const CANCEL_WORDS = ['batal', 'cancel', 'stop', 'tak jadi', 'ttak jadi'];

    /**
     * Intercept an inbound message. Returns true when the funnel handled it.
     */
    public function handle(CekbotConversation $conversation, string $body, string $type, CekbotBotService $bot): bool
    {
        try {
            $enrollment = $conversation->activeFlowEnrollment();

            if ($enrollment) {
                $this->advance($enrollment, $conversation, $body, $type, $bot);

                return true;
            }

            $flow = $this->matchingFlow($conversation, $body);

            if ($flow) {
                $this->start($flow, $conversation, $bot);

                return true;
            }
        } catch (\Throwable $e) {
            Log::error('Cekbot flow failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            // Fall through to the normal reply engine on error.
            return false;
        }

        return false;
    }

    /**
     * The first active, non-empty flow whose trigger matches this message.
     */
    private function matchingFlow(CekbotConversation $conversation, string $body): ?CekbotFlow
    {
        return $conversation->session
            ->flows()
            ->active()
            ->with('packages.cekbotProduct')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->first(fn (CekbotFlow $flow) => $flow->packages->isNotEmpty() && $flow->matches($body));
    }

    /**
     * Enrol the conversation and send the opening message + package menu.
     */
    private function start(CekbotFlow $flow, CekbotConversation $conversation, CekbotBotService $bot): void
    {
        CekbotFlowEnrollment::create([
            'cekbot_conversation_id' => $conversation->id,
            'cekbot_flow_id' => $flow->id,
            'status' => CekbotFlowEnrollment::STATUS_ACTIVE,
            'current_step' => CekbotFlowEnrollment::STEP_AWAIT_PACKAGE,
            'data' => [],
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        $this->setCategory($conversation, 'Berminat');

        $intro = trim((string) $flow->welcome_message);
        $menu = $this->packageMenu($flow);

        $bot->sendReply($conversation, $intro !== '' ? $intro."\n\n".$menu : $menu);
    }

    /**
     * Advance an in-progress enrollment based on the customer's reply.
     */
    private function advance(CekbotFlowEnrollment $enrollment, CekbotConversation $conversation, string $body, string $type, CekbotBotService $bot): void
    {
        $enrollment->loadMissing('flow.packages.cekbotProduct');
        $flow = $enrollment->flow;
        $text = trim($body);

        $enrollment->update(['last_activity_at' => now()]);

        // Let the customer bail out at any point.
        if ($this->isCancel($text)) {
            $enrollment->update(['status' => CekbotFlowEnrollment::STATUS_ABANDONED]);
            $bot->sendReply($conversation, 'Baik, saya batalkan tempahan ini. Kalau nak mula semula, taip mesej bila-bila masa ye 🙂');

            return;
        }

        match ($enrollment->current_step) {
            CekbotFlowEnrollment::STEP_AWAIT_PACKAGE => $this->handlePackage($enrollment, $flow, $conversation, $text, $bot),
            CekbotFlowEnrollment::STEP_AWAIT_PAYMENT => $this->handlePayment($enrollment, $flow, $conversation, $text, $bot),
            CekbotFlowEnrollment::STEP_AWAIT_NAME => $this->handleName($enrollment, $flow, $conversation, $text, $bot),
            CekbotFlowEnrollment::STEP_AWAIT_ADDRESS => $this->handleAddress($enrollment, $flow, $conversation, $text, $bot),
            CekbotFlowEnrollment::STEP_AWAIT_RECEIPT => $this->handleReceipt($enrollment, $flow, $conversation, $text, $type, $bot),
            default => null,
        };
    }

    private function handlePackage(CekbotFlowEnrollment $enrollment, CekbotFlow $flow, CekbotConversation $conversation, string $text, CekbotBotService $bot): void
    {
        $package = $this->parsePackageChoice($flow->packages, $text);

        if (! $package) {
            $bot->sendReply($conversation, "Maaf, saya tak pasti pilihan tu 🙈\n\n".$this->packageMenu($flow));

            return;
        }

        $package->loadMissing('cekbotProduct');

        $enrollment->putData([
            'package_id' => $package->id,
            'cekbot_product_id' => $package->cekbot_product_id,
            'product_id' => $package->cekbotProduct?->product_id,
            'package_label' => $package->label,
            'price' => $package->effectivePrice(),
            'currency' => $package->effectiveCurrency(),
        ]);
        $enrollment->save();

        // Decide the next step from the payment configuration.
        if ($flow->offersBothPaymentMethods()) {
            $enrollment->update(['current_step' => CekbotFlowEnrollment::STEP_AWAIT_PAYMENT]);
            $bot->sendReply($conversation, $this->paymentMenu($package));

            return;
        }

        $sole = $flow->solePaymentMethod();
        $enrollment->putData(['payment_method' => $sole]);
        $enrollment->save();

        $this->collectDetails($enrollment, $flow, $conversation, $bot, ackPackage: $package);
    }

    private function handlePayment(CekbotFlowEnrollment $enrollment, CekbotFlow $flow, CekbotConversation $conversation, string $text, CekbotBotService $bot): void
    {
        $method = $this->parsePaymentChoice($text, $flow);

        if ($method === null) {
            $bot->sendReply($conversation, 'Maaf, balas *1* untuk Transfer atau *2* untuk COD ye 🙂');

            return;
        }

        $enrollment->putData(['payment_method' => $method]);
        $enrollment->save();

        $this->collectDetails($enrollment, $flow, $conversation, $bot);
    }

    /**
     * Ask for the customer's name (or skip straight to the next detail).
     */
    private function collectDetails(CekbotFlowEnrollment $enrollment, CekbotFlow $flow, CekbotConversation $conversation, CekbotBotService $bot, ?CekbotFlowPackage $ackPackage = null): void
    {
        if ($flow->ask_name) {
            $enrollment->update(['current_step' => CekbotFlowEnrollment::STEP_AWAIT_NAME]);

            $prefix = $ackPackage
                ? 'Bagus! Anda pilih *'.$ackPackage->label.'* ('.$this->money($ackPackage->effectiveCurrency(), $ackPackage->effectivePrice()).").\n\n"
                : '';
            $bot->sendReply($conversation, $prefix.'Boleh saya dapatkan *nama penuh* anda? 🙂');

            return;
        }

        // No name step — use the WhatsApp profile name and continue.
        $enrollment->putData(['name' => $conversation->name ?: $conversation->phoneNumber()]);
        $enrollment->save();

        $this->afterName($enrollment, $flow, $conversation, $bot);
    }

    private function handleName(CekbotFlowEnrollment $enrollment, CekbotFlow $flow, CekbotConversation $conversation, string $text, CekbotBotService $bot): void
    {
        if ($text === '') {
            $bot->sendReply($conversation, 'Boleh kongsi *nama penuh* anda ye? 🙂');

            return;
        }

        $enrollment->putData(['name' => Str::limit($text, 120, '')]);
        $enrollment->save();

        $this->afterName($enrollment, $flow, $conversation, $bot);
    }

    /**
     * Branch after the name is captured: COD asks for an address, transfer shows
     * the bank details, otherwise the order is created immediately.
     */
    private function afterName(CekbotFlowEnrollment $enrollment, CekbotFlow $flow, CekbotConversation $conversation, CekbotBotService $bot): void
    {
        $method = $enrollment->answer('payment_method');

        if ($method === CekbotFlowEnrollment::PAYMENT_COD) {
            $enrollment->update(['current_step' => CekbotFlowEnrollment::STEP_AWAIT_ADDRESS]);
            $bot->sendReply($conversation, 'Baik! Untuk COD, boleh kongsi *alamat penuh* penghantaran? (nama penerima, alamat, poskod, bandar) 🏠');

            return;
        }

        if ($method === CekbotFlowEnrollment::PAYMENT_TRANSFER) {
            $enrollment->update(['current_step' => CekbotFlowEnrollment::STEP_AWAIT_RECEIPT]);
            $bot->sendReply($conversation, $this->transferInstructions($enrollment, $flow));

            return;
        }

        // No payment configured — just capture the order.
        $this->finalize($enrollment, $flow, $conversation, $bot);
    }

    private function handleAddress(CekbotFlowEnrollment $enrollment, CekbotFlow $flow, CekbotConversation $conversation, string $text, CekbotBotService $bot): void
    {
        if ($text === '') {
            $bot->sendReply($conversation, 'Boleh kongsi *alamat penuh* untuk penghantaran ye? 🏠');

            return;
        }

        $enrollment->putData(['address' => $text]);
        $enrollment->save();

        $this->finalize($enrollment, $flow, $conversation, $bot);
    }

    private function handleReceipt(CekbotFlowEnrollment $enrollment, CekbotFlow $flow, CekbotConversation $conversation, string $text, string $type, CekbotBotService $bot): void
    {
        $enrollment->putData([
            'receipt' => $type !== 'text' ? '['.$type.']' : ($text !== '' ? Str::limit($text, 200) : '[dihantar]'),
        ]);
        $enrollment->save();

        $this->finalize($enrollment, $flow, $conversation, $bot);
    }

    /**
     * Create the order, close the enrollment, and send the confirmation.
     */
    private function finalize(CekbotFlowEnrollment $enrollment, CekbotFlow $flow, CekbotConversation $conversation, CekbotBotService $bot): void
    {
        $order = $this->createOrder($enrollment, $flow, $conversation);

        $enrollment->update([
            'status' => CekbotFlowEnrollment::STATUS_COMPLETED,
            'current_step' => CekbotFlowEnrollment::STEP_DONE,
            'product_order_id' => $order->id,
            'completed_at' => now(),
        ]);

        $this->setCategory($conversation, 'Deal');

        $bot->sendReply($conversation, $this->confirmationMessage($enrollment, $flow, $order));
    }

    private function createOrder(CekbotFlowEnrollment $enrollment, CekbotFlow $flow, CekbotConversation $conversation): ProductOrder
    {
        $label = (string) $enrollment->answer('package_label', 'Pakej');
        $price = (float) $enrollment->answer('price', 0);
        $currency = (string) $enrollment->answer('currency', 'RM');
        $name = trim((string) $enrollment->answer('name')) ?: ($conversation->name ?: $conversation->phoneNumber());
        $phone = $conversation->phoneNumber();
        $method = $enrollment->answer('payment_method'); // bank_transfer|cod|null
        $address = $enrollment->answer('address');
        $catalogProductId = $enrollment->answer('product_id');

        $order = ProductOrder::create([
            'order_number' => ProductOrder::generateOrderNumber(),
            'status' => 'pending',
            'payment_status' => 'pending',
            'source' => 'whatsapp_bot',
            'source_reference' => 'cekbot:'.$conversation->session->session_name,
            'sales_source_id' => $flow->sales_source_id,
            'currency' => $currency,
            'subtotal' => $price,
            'total_amount' => $price,
            'customer_name' => $name,
            'customer_phone' => $phone,
            'payment_method' => $method,
            'order_date' => now(),
            'shipping_address' => $address ? [
                'name' => $name,
                'phone' => $phone,
                'full_address' => $address,
            ] : null,
            'metadata' => [
                'cekbot_flow_id' => $flow->id,
                'cekbot_flow_name' => $flow->name,
                'cekbot_conversation_id' => $conversation->id,
                'cekbot_session' => $conversation->session->session_name,
                'chat_id' => $conversation->chat_id,
                'receipt' => $enrollment->answer('receipt'),
            ],
        ]);

        $order->items()->create([
            'product_id' => $catalogProductId,
            'itemable_type' => $catalogProductId ? Product::class : null,
            'itemable_id' => $catalogProductId,
            'product_name' => $label,
            'quantity_ordered' => 1,
            'unit_price' => $price,
            'total_price' => $price,
            'unit_cost' => 0,
        ]);

        // The free-text COD address lives in the shipping_address JSON column
        // (set above), the same as funnel/POS/lead orders — ProductOrder's
        // effectiveAddress() normalises it for courier booking (postcode/state
        // are derived), so we don't force a half-empty structured address row.

        $order->addSystemNote('Order created from WhatsApp Cekbot flow: '.$flow->name, [
            'cekbot_flow_id' => $flow->id,
            'payment_method' => $method,
        ]);

        return $order;
    }

    /**
     * The numbered package menu.
     */
    private function packageMenu(CekbotFlow $flow): string
    {
        $lines = $flow->packages->values()->map(function (CekbotFlowPackage $p, int $i) {
            $bullet = self::NUMBER_EMOJI[$i] ?? (($i + 1).'.');

            return $bullet.' '.$p->label.' – '.$this->money($p->effectiveCurrency(), $p->effectivePrice());
        })->implode("\n");

        $prompt = trim((string) $flow->package_prompt) ?: 'Balas nombor pakej yang berminat 🙂';

        return $lines."\n\n".$prompt;
    }

    private function paymentMenu(CekbotFlowPackage $package): string
    {
        return 'Bagus! Anda pilih *'.$package->label.'* ('.$this->money($package->effectiveCurrency(), $package->effectivePrice()).").\n\n"
            ."Nak buat pembayaran macam mana?\n"
            ."1️⃣ Transfer / Online Banking\n"
            ."2️⃣ COD (Bayar semasa terima)\n\n"
            .'Balas *1* atau *2* ye 🙂';
    }

    private function transferInstructions(CekbotFlowEnrollment $enrollment, CekbotFlow $flow): string
    {
        $price = $this->money((string) $enrollment->answer('currency', 'RM'), (float) $enrollment->answer('price', 0));
        $bank = trim((string) $flow->bank_details);
        $extra = trim((string) $flow->transfer_instructions);

        $lines = ['Baik! Untuk bayaran transfer, sila bank-in:'];

        if ($bank !== '') {
            $lines[] = '';
            $lines[] = $bank;
        }

        $lines[] = '';
        $lines[] = 'Jumlah: *'.$price.'*';

        if ($extra !== '') {
            $lines[] = '';
            $lines[] = $extra;
        }

        $lines[] = '';
        $lines[] = 'Hantar *resit/screenshot* bila dah transfer ye 🙏';

        return implode("\n", $lines);
    }

    private function confirmationMessage(CekbotFlowEnrollment $enrollment, CekbotFlow $flow, ProductOrder $order): string
    {
        $vars = [
            '{order_number}' => $order->order_number,
            '{package}' => (string) $enrollment->answer('package_label', ''),
            '{price}' => $this->money((string) $enrollment->answer('currency', 'RM'), (float) $enrollment->answer('price', 0)),
            '{name}' => (string) $enrollment->answer('name', ''),
        ];

        $template = trim((string) $flow->confirmation_message);

        if ($template === '') {
            $template = "Terima kasih {name}! 🎉\n\n"
                ."Pesanan anda telah kami terima:\n"
                ."🧾 No. Pesanan: {order_number}\n"
                ."📦 Pakej: {package}\n"
                ."💰 Jumlah: {price}\n\n"
                .'Pasukan kami akan proses & hubungi anda tak lama lagi. 🙏';
        }

        return strtr($template, $vars);
    }

    /**
     * @param  Collection<int, CekbotFlowPackage>  $packages
     */
    private function parsePackageChoice(Collection $packages, string $text): ?CekbotFlowPackage
    {
        $packages = $packages->values();

        if (preg_match('/\d+/', $text, $m)) {
            $index = (int) $m[0];
            if ($index >= 1 && $index <= $packages->count()) {
                return $packages[$index - 1];
            }
        }

        $lower = mb_strtolower(trim($text));

        if (mb_strlen($lower) >= 2) {
            foreach ($packages as $package) {
                $label = mb_strtolower(trim($package->label));
                if ($label !== '' && str_contains($lower, $label)) {
                    return $package;
                }
            }
        }

        return null;
    }

    private function parsePaymentChoice(string $text, CekbotFlow $flow): ?string
    {
        $lower = mb_strtolower(trim($text));

        $wantsTransfer = (bool) preg_match('/\b1\b/', $lower)
            || str_contains($lower, 'transfer')
            || str_contains($lower, 'bank')
            || str_contains($lower, 'online')
            || str_contains($lower, 'trf');

        $wantsCod = (bool) preg_match('/\b2\b/', $lower)
            || str_contains($lower, 'cod')
            || str_contains($lower, 'c.o.d')
            || (str_contains($lower, 'bayar') && str_contains($lower, 'terima'));

        if ($wantsTransfer && ! $wantsCod && $flow->payment_transfer_enabled) {
            return CekbotFlowEnrollment::PAYMENT_TRANSFER;
        }

        if ($wantsCod && ! $wantsTransfer && $flow->payment_cod_enabled) {
            return CekbotFlowEnrollment::PAYMENT_COD;
        }

        return null;
    }

    private function isCancel(string $text): bool
    {
        $lower = mb_strtolower(trim($text));

        foreach (self::CANCEL_WORDS as $word) {
            if ($lower === $word) {
                return true;
            }
        }

        return false;
    }

    /**
     * Move the conversation into a pipeline category by name (best effort).
     */
    private function setCategory(CekbotConversation $conversation, string $name): void
    {
        $category = CekbotLeadCategory::query()->where('name', $name)->first();

        if ($category) {
            $conversation->update(['lead_category_id' => $category->id]);
        }
    }

    /**
     * Format a price without trailing ".00" for whole amounts (RM97, RM12.50).
     */
    private function money(string $currency, float $amount): string
    {
        $formatted = number_format($amount, 2);

        if (str_ends_with($formatted, '.00')) {
            $formatted = substr($formatted, 0, -3);
        }

        return $currency.$formatted;
    }
}
