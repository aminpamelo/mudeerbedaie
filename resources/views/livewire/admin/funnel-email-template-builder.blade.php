<?php

use App\Models\FunnelEmailTemplate;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new
#[Layout('components.layouts.react-email-builder')]
class extends Component {
    public FunnelEmailTemplate $template;
    public string $initialDesign = '';
    public bool $showPreview = false;
    public string $previewHtml = '';

    public function mount(FunnelEmailTemplate $template): void
    {
        $this->template = $template;

        // Get design JSON for React editor
        if ($template->design_json) {
            $this->initialDesign = json_encode($template->design_json);
        }
    }

    public function saveDesign(string $designJson, string $html): void
    {
        $finalHtml = $this->compileEmailHtml($html);

        $this->template->update([
            'design_json' => json_decode($designJson, true),
            'html_content' => $finalHtml,
            'editor_type' => 'visual',
        ]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Template saved successfully!',
        ]);
    }

    public function autoSave(string $designJson, string $html): void
    {
        $finalHtml = $this->compileEmailHtml($html);

        $this->template->update([
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

    public function sendTestEmail(string $email, string $html): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
                ->subject('[TEST] ' . $this->template->name);
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
            '{{contact.name}}' => 'Ahmad Amin',
            '{{contact.first_name}}' => 'Ahmad',
            '{{contact.email}}' => 'ahmad@example.com',
            '{{contact.phone}}' => '+60123456789',
            '{{order.number}}' => 'PO-20260307-ABC',
            '{{order.total}}' => 'RM 299.00',
            '{{order.date}}' => now()->format('d M Y'),
            '{{order.items_list}}' => '1x Product Name - RM 299.00',
            '{{payment.method}}' => 'Credit Card',
            '{{payment.status}}' => 'Paid',
            '{{product.name}}' => 'Premium Course Bundle',
            '{{product.price}}' => 'RM 499.00',
            '{{product.description}}' => 'Complete course bundle with lifetime access',
            '{{product.image_url}}' => 'https://placehold.co/600x400',
            '{{funnel.name}}' => 'My Sales Funnel',
            '{{funnel.url}}' => 'https://example.com/funnel',
            '{{current_date}}' => now()->format('d M Y'),
            '{{current_time}}' => now()->format('g:i A'),
            '{{company_name}}' => config('app.name'),
            '{{company_email}}' => config('mail.from.address', 'info@example.com'),
        ];
    }
}; ?>

<div wire:id="{{ $this->getId() }}" class="h-screen overflow-hidden">
    <div class="flex items-center justify-between p-4 bg-white border-b">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.funnel-email-templates') }}" class="text-gray-500 hover:text-gray-700">
                <flux:icon name="arrow-left" class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-lg font-semibold">{{ $template->name }}</h1>
                <p class="text-sm text-gray-500">Visual Email Builder</p>
            </div>
        </div>
    </div>

    <div
        wire:ignore
        id="react-email-builder-root"
        class="h-full"
        data-template-id="{{ $template->id }}"
        data-template-name="{{ $template->name }}"
        data-template-type="funnel_email_template"
        data-template-language="en"
        data-initial-design="{{ $initialDesign }}"
        data-back-url="{{ route('admin.funnel-email-templates') }}"
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
                    <flux:button variant="ghost" wire:click="$set('showPreview', false)">Close</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
