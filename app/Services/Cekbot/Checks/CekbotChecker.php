<?php

namespace App\Services\Cekbot\Checks;

use App\Models\CekbotConversation;

/**
 * A "check" the bot can perform against internal systems (orders, enrolments,
 * payments, …) and answer with live data. Return null when not applicable.
 */
interface CekbotChecker
{
    public function respond(string $body, CekbotConversation $conversation): ?string;
}
