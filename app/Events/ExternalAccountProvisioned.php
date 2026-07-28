<?php

namespace App\Events;

use App\Models\ExternalProvisioningRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExternalAccountProvisioned
{
    use Dispatchable, SerializesModels;

    /**
     * Fired once an external system has successfully provisioned an account
     * for a paid order. Delivery of the returned credentials to the buyer
     * (email / WhatsApp / thank-you page) is handled by listeners on this event.
     */
    public function __construct(
        public ExternalProvisioningRequest $request
    ) {}
}
