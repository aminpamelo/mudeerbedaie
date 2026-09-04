<?php

namespace App\Services\Cekbot;

use App\Models\CekbotConversation;
use App\Services\Cekbot\Checks\CekbotChecker;
use App\Services\Cekbot\Checks\OrderStatusChecker;

/**
 * Runs an inbound message through the registered "checkers" and returns the
 * first live-data answer (order status, etc.), or null if none apply.
 *
 * Add new capabilities by appending a CekbotChecker here.
 */
class CekbotCheckService
{
    /** @var array<int, CekbotChecker> */
    private array $checkers;

    public function __construct(OrderStatusChecker $orderStatus)
    {
        $this->checkers = [$orderStatus];
    }

    public function check(string $body, CekbotConversation $conversation): ?string
    {
        foreach ($this->checkers as $checker) {
            $reply = $checker->respond($body, $conversation);

            if (filled($reply)) {
                return $reply;
            }
        }

        return null;
    }
}
