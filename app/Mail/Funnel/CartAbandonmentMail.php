<?php

namespace App\Mail\Funnel;

use App\Models\FunnelCart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CartAbandonmentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public FunnelCart $cart,
        public int $emailNumber = 1
    ) {}

    public function envelope(): Envelope
    {
        $subjects = [
            1 => 'Did you forget something?',
            2 => 'Your cart is waiting for you',
            3 => 'Last chance to complete your order',
        ];

        return new Envelope(
            subject: $subjects[$this->emailNumber] ?? 'Complete your purchase',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.funnel.cart-abandonment',
            with: [
                'cart' => $this->cart,
                'emailNumber' => $this->emailNumber,
                'recoveryUrl' => $this->cart->getRecoveryUrl(),
                'items' => $this->cart->getItems(),
                'total' => $this->cart->getFormattedTotal(),
                'funnelName' => $this->cart->funnel->name ?? 'Our Store',
            ],
        );
    }
}
