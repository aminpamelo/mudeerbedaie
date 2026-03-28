<?php

namespace App\Notifications\Hr;

use App\Models\AssetAssignment;

class AssetReturnConfirmed extends BaseHrNotification
{
    public function __construct(
        public AssetAssignment $assignment
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Asset Returned';
    }

    protected function body(): string
    {
        $employeeName = $this->assignment->employee->full_name;
        $assetName = $this->assignment->asset->name ?? 'an asset';

        return "{$employeeName} returned {$assetName}.";
    }

    protected function actionUrl(): string
    {
        return '/hr/assets/assignments';
    }

    protected function icon(): string
    {
        return 'rotate-ccw';
    }
}
