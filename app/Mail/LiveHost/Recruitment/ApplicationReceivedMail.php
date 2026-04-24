<?php

namespace App\Mail\LiveHost\Recruitment;

use App\Models\LiveHostApplicant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public LiveHostApplicant $applicant) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'We received your application',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.recruitment.application-received',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
