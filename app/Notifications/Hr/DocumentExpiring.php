<?php

namespace App\Notifications\Hr;

use App\Models\EmployeeDocument;

class DocumentExpiring extends BaseHrNotification
{
    public function __construct(
        public EmployeeDocument $document,
        public int $daysLeft
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push'];
    }

    protected function title(): string
    {
        return 'Document Expiring Soon';
    }

    protected function body(): string
    {
        $docName = $this->document->title ?? $this->document->name ?? 'A document';

        return "{$docName} expires in {$this->daysLeft} day(s). Please renew it.";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/documents';
    }

    protected function icon(): string
    {
        return 'file-warning';
    }
}
