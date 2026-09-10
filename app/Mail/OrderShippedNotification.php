<?php

namespace App\Mail;

use App\Models\ProductOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderShippedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProductOrder $order,
        public string $body = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pesanan anda telah dihantar — '.$this->order->order_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.order-shipped',
            with: [
                'order' => $this->order,
                'body' => $this->body,
                'trackingNumber' => $this->order->tracking_id,
                'trackingUrl' => $this->order->tracking_url,
                'courier' => $this->order->shipping_provider_label,
                'storeName' => (string) config('store.name', config('app.name', 'Kami')),
            ],
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        return [];
    }
}
