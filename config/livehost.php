<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allowance (Live Host Pocket)
    |--------------------------------------------------------------------------
    |
    | Toggles RM figures across the host-side Pocket UI. The allowance
    | schema and rate source are still open product questions (see
    | docs/plans/livehost-host-volt-semantics.md §Allowance). Default OFF
    | so Batches 2-4 can scaffold the UI without shipping placeholder
    | numbers. Flip to true via LIVEHOST_ALLOWANCE_ENABLED once a rate
    | source lands.
    |
    */
    'allowance_enabled' => env('LIVEHOST_ALLOWANCE_ENABLED', false),

];
