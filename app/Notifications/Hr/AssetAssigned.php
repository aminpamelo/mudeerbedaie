<?php

namespace App\Notifications\Hr;

use App\Models\AssetAssignment;

class AssetAssigned extends BaseHrNotification
{
    public function __construct(
        public AssetAssignment $assignment
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push'];
    }

    protected function title(): string
    {
        return 'Asset Assigned';
    }

    protected function body(): string
    {
        $assetName = $this->assignment->asset->name ?? 'An asset';

        return "{$assetName} has been assigned to you.";
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
