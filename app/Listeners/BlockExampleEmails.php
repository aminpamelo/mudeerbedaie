<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

class BlockExampleEmails
{
    public function handle(MessageSending $event): ?bool
    {
        $message = $event->message;
        $blocked = [];

        // Filter "to" recipients
        $toRecipients = $message->getTo();
        $filteredTo = $this->filterRecipients($toRecipients, $blocked);
        $message->to(...$filteredTo);

        // Filter "cc" recipients
        $ccRecipients = $message->getCc();
        if (! empty($ccRecipients)) {
            $filteredCc = $this->filterRecipients($ccRecipients, $blocked);
            $message->cc(...$filteredCc);
        }

        // Filter "bcc" recipients
        $bccRecipients = $message->getBcc();
        if (! empty($bccRecipients)) {
            $filteredBcc = $this->filterRecipients($bccRecipients, $blocked);
            $message->bcc(...$filteredBcc);
        }

        if (! empty($blocked)) {
            Log::info('Blocked @example.com email addresses', [
                'blocked_addresses' => $blocked,
                'subject' => $message->getSubject(),
            ]);
        }

        // Cancel the email entirely if no valid recipients remain
        if (empty($message->getTo()) && empty($message->getCc()) && empty($message->getBcc())) {
            Log::info('Email cancelled - all recipients were @example.com', [
                'subject' => $message->getSubject(),
            ]);

            return false;
        }

        return null;
    }

    /**
     * Filter out @example.com addresses from a list of recipients.
     *
     * @param  array<Address>  $recipients
     * @param  array<string>  $blocked
     * @return array<Address>
     */
    private function filterRecipients(array $recipients, array &$blocked): array
    {
        $filtered = [];

        foreach ($recipients as $recipient) {
            $email = $recipient->getAddress();

            if (str_ends_with(strtolower($email), '@example.com')) {
                $blocked[] = $email;
            } else {
                $filtered[] = $recipient;
            }
        }

        return $filtered;
    }
}
