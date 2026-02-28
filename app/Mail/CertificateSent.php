<?php

namespace App\Mail;

use App\Models\CertificateIssue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificateSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CertificateIssue $certificateIssue,
        public string $customMessage
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Certificate - '.$this->certificateIssue->getCertificateName(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.certificate-sent',
        );
    }

    public function attachments(): array
    {
        if (! $this->certificateIssue->hasFile()) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('public', $this->certificateIssue->file_path)
                ->as($this->certificateIssue->getDownloadFilename())
                ->withMime('application/pdf'),
        ];
    }
}
