<?php

namespace App\Exceptions\LiveHost;

use RuntimeException;

/**
 * Thrown by LiveHostPayrollService when a lifecycle transition is attempted
 * on a payroll run whose current status doesn't allow it (e.g. recompute on
 * a locked run, or markPaid on a draft).
 */
class PayrollRunStateException extends RuntimeException
{
    public static function cannotRecompute(string $currentStatus): self
    {
        return new self(
            "Cannot recompute payroll run in status '{$currentStatus}'. Recompute is only allowed on draft runs."
        );
    }

    public static function cannotLock(string $currentStatus): self
    {
        return new self(
            "Cannot lock payroll run in status '{$currentStatus}'. Only draft runs may be locked."
        );
    }

    public static function cannotMarkPaid(string $currentStatus): self
    {
        return new self(
            "Cannot mark payroll run as paid in status '{$currentStatus}'. Only locked runs may be paid."
        );
    }
}
