<?php

use App\Models\Broadcast;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('components.layouts.react-email-builder')]
class extends Component {
    public Broadcast $broadcast;
    public string $initialDesign = '';
    public bool $showPreview = false;
    public string $previewHtml = '';

    public function mount(Broadcast $broadcast): void
    {
        $this->broadcast = $broadcast;

        if ($broadcast->design_json) {
            $this->initialDesign = json_encode($broadcast->design_json);
        }
    }

    public function saveDesign(string $designJson, string $html): void
    {
        $finalHtml = $this->compileEmailHtml($html);

        $this->broadcast->update([
            'design_json' => json_decode($designJson, true),
            'html_content' => $finalHtml,
            'editor_type' => 'visual',
        ]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Broadcast email saved successfully!',
        ]);
    }

    public function autoSave(string $designJson, string $html): void
    {
        $finalHtml = $this->compileEmailHtml($html);

        $this->broadcast->update([
            'design_json' => json_decode($designJson, true),
            'html_content' => $finalHtml,
            'editor_type' => 'visual',
        ]);
    }

    public function previewEmailFromHtml(string $html): void
    {
        $sampleData = $this->getSampleData();
        $processedHtml = str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $html
        );
        $this->previewHtml = $processedHtml;
        $this->showPreview = true;
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
    }

    public function sendTestEmail(string $email, string $html): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Invalid email address');
        }

        $sampleData = $this->getSampleData();
        $processedHtml = str_replace(
            array_keys($sampleData),
            array_values($sampleData),
            $html
        );

        $finalHtml = $this->compileEmailHtml($processedHtml);

        \Illuminate\Support\Facades\Mail::html($finalHtml, function ($message) use ($email) {
            $message->to($email)
                ->subject('[TEST] ' . $this->broadcast->subject);
        });
    }

    protected function compileEmailHtml(string $html): string
    {
        if (stripos($html, '<html') === false && stripos($html, '<!DOCTYPE') === false) {
            $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head><body>' . $html . '</body></html>';
        }

        return $html;
    }

    protected function getSampleData(): array
    {
        return [
            '{{name}}' => 'Ahmad Amin',
            '{{email}}' => 'ahmad@example.com',
            '{{student_id}}' => '12345',
            '{{current_date}}' => now()->format('d M Y'),
            '{{current_time}}' => now()->format('g:i A'),
            '{{company_name}}' => config('app.name'),
            '{{company_email}}' => config('mail.from.address', 'info@example.com'),
        ];
    }
}; ?>

<div wire:id="{{ $this->getId() }}" class="h-screen overflow-hidden">
    <div class="flex items-center justify-between p-4 bg-white dark:bg-zinc-900 border-b dark:border-zinc-700">
        <div class="flex items-center gap-3">
            <a href="{{ route('crm.broadcasts.create') . '?resume=' . $broadcast->id }}" class="text-gray-500 hover:text-gray-700 dark:text-zinc-400 dark:hover:text-zinc-200">
                <flux:icon name="arrow-left" class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-lg font-semibold dark:text-white">{{ $broadcast->name }}</h1>
                <p class="text-sm text-gray-500 dark:text-zinc-400">Visual Email Builder</p>
            </div>
        </div>
    </div>

    <div
        wire:ignore
        id="react-email-builder-root"
        class="h-full"
        data-template-id="{{ $broadcast->id }}"
        data-template-name="{{ $broadcast->name }}"
        data-template-type="broadcast"
        data-template-language="en"
        data-initial-design="{{ $initialDesign }}"
        data-back-url="{{ route('crm.broadcasts.create') . '?resume=' . $broadcast->id }}"
    ></div>

    @if($showPreview)
        <flux:modal wire:model="showPreview" class="max-w-4xl">
            <div class="space-y-4">
                <flux:heading size="lg">Email Preview</flux:heading>
                <div class="border rounded-lg overflow-hidden">
                    <iframe
                        srcdoc="{{ $previewHtml }}"
                        class="w-full h-[600px] border-0"
                        sandbox=""
                    ></iframe>
                </div>
                <div class="flex justify-end">
                    <flux:button variant="ghost" wire:click="closePreview">Close</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
