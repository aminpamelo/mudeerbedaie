<?php

namespace App\Notifications\Hr;

use App\Models\AssetAssignment;

class AssetReturnRequested extends BaseHrNotification
{
    public function __construct(
        public AssetAssignment $assignment,
        public int $daysLeft
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Asset Return Due';
    }

    protected function body(): string
    {
        $assetName = $this->assignment->asset->name ?? 'an asset';

        return "Please return {$assetName} within {$this->daysLeft} day(s).";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/assets';
    }

    protected function icon(): string
    {
        return 'package';
    }
}
