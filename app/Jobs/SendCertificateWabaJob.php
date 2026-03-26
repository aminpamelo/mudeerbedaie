<?php

namespace App\Jobs;

use App\Models\CertificateIssue;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppManager;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendCertificateWabaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $certificateIssueId,
        public string $phoneNumber,
        public int $templateId,
        public int $sentByUserId,
    ) {}

    public function handle(WhatsAppManager $whatsAppManager, WhatsAppService $whatsAppService): void
    {
        $issue = CertificateIssue::with(['certificate', 'student.user', 'class.course'])->find($this->certificateIssueId);

        if (! $issue || ! $issue->hasFile()) {
            Log::warning('SendCertificateWabaJob: Issue not found or no PDF', [
                'issue_id' => $this->certificateIssueId,
            ]);

            return;
        }

        $template = WhatsAppTemplate::find($this->templateId);

        if (! $template || $template->status !== 'APPROVED') {
            Log::warning('SendCertificateWabaJob: Template not found or not approved', [
                'template_id' => $this->templateId,
                'status' => $template?->status,
            ]);

            return;
        }

        $metaProvider = $whatsAppManager->metaProvider();

        $components = $this->buildComponents($issue, $template, $metaProvider);

        $result = $metaProvider->sendTemplate(
            $this->phoneNumber,
            $template->name,
            $template->language,
            $components,
        );

        // Store outbound message in WhatsApp inbox
        $whatsAppService->storeOutboundMessage(
            phoneNumber: $this->phoneNumber,
            type: 'template',
            body: "Template: {$template->name}",
            sendResult: $result,
            sentByUserId: $this->sentByUserId,
        );

        $sentBy = User::find($this->sentByUserId);

        if ($result['success']) {
            $issue->logAction('sent_waba', $sentBy, [
                'status' => 'sent',
                'phone' => $this->phoneNumber,
                'template' => $template->name,
                'message_id' => $result['message_id'] ?? null,
            ]);

            Log::info('Certificate WABA sent', [
                'issue_id' => $this->certificateIssueId,
                'phone' => $this->phoneNumber,
                'template' => $template->name,
                'message_id' => $result['message_id'] ?? null,
            ]);
        } else {
            $issue->logAction('sent_waba', $sentBy, [
                'status' => 'failed',
                'phone' => $this->phoneNumber,
                'template' => $template->name,
                'error' => $result['error'] ?? 'Unknown error',
            ]);

            Log::warning('Certificate WABA send failed', [
                'issue_id' => $this->certificateIssueId,
                'phone' => $this->phoneNumber,
                'error' => $result['error'] ?? 'Unknown error',
            ]);
        }
    }

    /**
     * Build Meta template components with document header and resolved body variables.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildComponents(CertificateIssue $issue, WhatsAppTemplate $template, \App\Services\WhatsApp\MetaCloudProvider $metaProvider): array
    {
        $components = [];
        $mappings = $template->variable_mappings ?? [];

        // Document header — upload PDF to Meta and use media ID
        $hasDocumentHeader = collect($template->components ?? [])
            ->contains(fn ($c) => ($c['type'] ?? '') === 'HEADER' && ($c['format'] ?? '') === 'DOCUMENT');

        if ($hasDocumentHeader) {
            $filePath = Storage::disk('public')->path($issue->file_path);
            $uploadResult = $metaProvider->uploadMedia($filePath, 'application/pdf', $issue->getDownloadFilename());

            if (! $uploadResult['success']) {
                Log::warning('SendCertificateWabaJob: Failed to upload PDF to Meta', [
                    'issue_id' => $issue->id,
                    'error' => $uploadResult['error'] ?? 'Unknown',
                ]);

                throw new \RuntimeException('Failed to upload certificate PDF to Meta: '.($uploadResult['error'] ?? 'Unknown'));
            }

            $components[] = [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'document',
                        'document' => [
                            'id' => $uploadResult['media_id'],
                            'filename' => $issue->getDownloadFilename(),
                        ],
                    ],
                ],
            ];
        }

        // Body variables
        if (! empty($mappings['body'])) {
            $contextMap = $this->getContextMap($issue);
            $parameters = [];

            ksort($mappings['body']);
            foreach ($mappings['body'] as $index => $fieldName) {
                $parameters[] = [
                    'type' => 'text',
                    'text' => $contextMap[$fieldName] ?? '',
                ];
            }

            if (! empty($parameters)) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => $parameters,
                ];
            }
        }

        return $components;
    }

    /**
     * Get the context map of available certificate fields.
     *
     * @return array<string, string>
     */
    protected function getContextMap(CertificateIssue $issue): array
    {
        return [
            'student_name' => $issue->student?->user?->name ?? '',
            'certificate_name' => $issue->getCertificateName(),
            'certificate_number' => $issue->certificate_number ?? '',
            'class_name' => $issue->class?->title ?? '',
            'course_name' => $issue->class?->course?->name ?? '',
            'issue_date' => $issue->issue_date?->format('d/m/Y') ?? '',
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendCertificateWabaJob failed', [
            'issue_id' => $this->certificateIssueId,
            'phone' => $this->phoneNumber,
            'template_id' => $this->templateId,
            'error' => $exception->getMessage(),
        ]);

        $issue = CertificateIssue::find($this->certificateIssueId);
        if ($issue) {
            $sentBy = User::find($this->sentByUserId);
            $issue->logAction('sent_waba', $sentBy, [
                'status' => 'failed',
                'phone' => $this->phoneNumber,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
