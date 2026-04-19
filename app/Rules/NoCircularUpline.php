<?php

namespace App\Rules;

use App\Models\LiveHostCommissionProfile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoCircularUpline implements ValidationRule
{
    /**
     * @param  int  $targetUserId  The user whose upline is being set.
     */
    public function __construct(private int $targetUserId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $proposedUplineId = (int) $value;
        $targetUserId = $this->targetUserId;

        // Rule 1: can't upline yourself
        if ($proposedUplineId === $targetUserId) {
            $fail('This selection would create a circular upline: a host cannot be their own upline.');

            return;
        }

        // Rule 2: walk up from the proposed upline - if we reach the target user,
        // setting this upline would close a loop.
        $cursor = $proposedUplineId;
        $seen = [];
        $maxDepth = 50;

        for ($depth = 0; $depth < $maxDepth; $depth++) {
            if ($cursor === $targetUserId) {
                $fail('This selection would create a circular upline: it would close a loop through the existing chain.');

                return;
            }

            if (in_array($cursor, $seen, true)) {
                // Existing data already has a cycle - don't loop forever.
                return;
            }
            $seen[] = $cursor;

            $next = LiveHostCommissionProfile::query()
                ->where('user_id', $cursor)
                ->where('is_active', true)
                ->value('upline_user_id');

            if (! $next) {
                return; // reached root without hitting target
            }

            $cursor = (int) $next;
        }
    }
}
