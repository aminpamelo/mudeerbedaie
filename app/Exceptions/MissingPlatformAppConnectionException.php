<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\PlatformAccount;
use RuntimeException;

class MissingPlatformAppConnectionException extends RuntimeException
{
    public function __construct(
        public readonly PlatformAccount $account,
        public readonly string $category,
        ?string $message = null
    ) {
        parent::__construct(
            $message ?? "PlatformAccount #{$account->id} has no active credential for app category '{$category}'. Connect the corresponding TikTok app to enable this sync."
        );
    }
}
