<?php

declare(strict_types=1);

use App\Services\Funnel\PuckRenderer;

it('preserves line breaks in a Puck order bump description', function () {
    $renderer = new PuckRenderer;

    $description = "Kelas 10 Minggu\nTanya Soalan Direct Dalam Kelas Live\nSetiap Hujung Minggu (malam)";

    $html = $renderer->render([
        'content' => [
            [
                'type' => 'OrderBump',
                'props' => [
                    'headline' => 'KELAS DARAH WANITA',
                    'description' => $description,
                    'price' => '50',
                ],
            ],
        ],
    ])->toHtml();

    // The CSS that makes the typed newlines render as separate lines.
    expect($html)->toContain('white-space: pre-line');
    // The newline characters survive into the rendered description (not collapsed away).
    expect($html)->toContain($description);
});
