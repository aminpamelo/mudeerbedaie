<?php

use App\Models\ActualLiveRecord;
use App\Models\LiveSession;

it('belongs to a matched actual live record', function () {
    $record = ActualLiveRecord::factory()->create();
    $session = LiveSession::factory()->create([
        'matched_actual_live_record_id' => $record->id,
    ]);

    expect($session->matchedActualLiveRecord->id)->toBe($record->id);
});
