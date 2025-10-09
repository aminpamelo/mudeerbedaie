<?php

use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Volt\Component;

new class extends Component {
    public Certificate $certificate;

    public array $previewData = [];

    public function mount(Certificate $certificate): void
    {
        $this->certificate = $certificate;
        $this->previewData = $certificate->generatePreview();
    }

    public function downloadSamplePdf()
    {
        // Generate a temporary PDF for preview
        $html = view('certificates.pdf-template', [
            'certificate' => $this->certificate,
            'width' => $this->certificate->width,
            'height' => $this->certificate->height,
            'backgroundColor' => $this->certificate->background_color,
            'backgroundImage' => $this->certificate->background_image,
            'elements' => $this->certificate->elements ?? [],
            'data' => $this->previewData,
        ])->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper(
                $this->certificate->size === 'letter' ? [0, 0, 816, 1056] : [0, 0, 794, 1123],
                $this->certificate->orientation
            )
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'certificate-preview.pdf');
    }
} ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $certificate->name }}</flux:heading>
            <flux:text class="mt-2">Preview certificate template with sample data</flux:text>
        </div>
        <div class="flex items-center gap-3">
            <flux:button variant="outline" href="{{ route('certificates.index') }}" icon="arrow-left">
                Back to List
            </flux:button>
            <flux:button variant="outline" href="{{ route('certificates.edit', $certificate) }}" icon="pencil">
                Edit Template
            </flux:button>
            <flux:button variant="primary" wire:click="downloadSamplePdf" icon="arrow-down-tray">
                Download Sample PDF
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Certificate Preview -->
        <div class="lg:col-span-2">
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Certificate Preview</flux:heading>
                    <flux:badge :variant="$certificate->status === 'active' ? 'success' : ($certificate->status === 'draft' ? 'warning' : 'default')">
                        {{ ucfirst($certificate->status) }}
                    </flux:badge>
                </div>

                <!-- Canvas Preview -->
                <div class="bg-gray-50 dark:bg-gray-900 p-6 rounded-lg overflow-auto">
                    <div class="flex justify-center">
                        <div
                            x-data="{ scale: 1 }"
                            x-init="
                                const updateScale = () => {
                                    const containerWidth = $el.parentElement.clientWidth - 48;
                                    const canvasWidth = {{ $certificate->width }};
                                    scale = Math.min(1, containerWidth / canvasWidth);
                                };
                                updateScale();
                                window.addEventListener('resize', updateScale);
                            "
                            :style="`transform: scale(${scale}); transform-origin: top center;`"
                            style="margin: 0 auto;"
                        >
                            <div
                                class="relative bg-white shadow-2xl"
                                style="width: {{ $certificate->width }}px; height: {{ $certificate->height }}px; background-color: {{ $certificate->background_color }};"
                            >
                            @if($certificate->background_image)
                                <img
                                    src="{{ Storage::url($certificate->background_image) }}"
                                    alt="Background"
                                    class="absolute inset-0 w-full h-full"
                                    style="pointer-events: none; object-fit: fill;"
                                />
                            @endif

                            @foreach($certificate->elements ?? [] as $element)
                                <div
                                    class="absolute"
                                    style="
                                        left: {{ $element['x'] }}px;
                                        top: {{ $element['y'] }}px;
                                        width: {{ $element['width'] }}px;
                                        height: {{ $element['height'] }}px;
                                    "
                                >
                                    @if($element['type'] === 'text')
                                        <div
                                            style="
                                                font-size: {{ $element['fontSize'] ?? 16 }}px;
                                                font-weight: {{ $element['fontWeight'] ?? 'normal' }};
                                                font-style: {{ $element['fontStyle'] ?? 'normal' }};
                                                text-decoration: {{ $element['textDecoration'] ?? 'none' }};
                                                color: {{ $element['color'] ?? '#000000' }};
                                                text-align: {{ $element['textAlign'] ?? 'left' }};
                                                font-family: {{ $element['fontFamily'] ?? 'Arial, sans-serif' }};
                                            "
                                        >
                                            {{ $element['content'] }}
                                        </div>
                                    @elseif($element['type'] === 'dynamic')
                                        <div
                                            style="
                                                font-size: {{ $element['fontSize'] ?? 16 }}px;
                                                font-weight: {{ $element['fontWeight'] ?? 'normal' }};
                                                font-style: {{ $element['fontStyle'] ?? 'normal' }};
                                                text-decoration: {{ $element['textDecoration'] ?? 'none' }};
                                                color: {{ $element['color'] ?? '#000000' }};
                                                text-align: {{ $element['textAlign'] ?? 'left' }};
                                                font-family: {{ $element['fontFamily'] ?? 'Arial, sans-serif' }};
                                            "
                                        >
                                            {{ $element['prefix'] ?? '' }}{{ $previewData[$element['field']] ?? '' }}{{ $element['suffix'] ?? '' }}
                                        </div>
                                    @elseif($element['type'] === 'image')
                                        @if(!empty($element['src']))
                                            <img
                                                src="{{ $element['src'] }}"
                                                alt="{{ $element['alt'] ?? 'Image' }}"
                                                style="
                                                    width: 100%;
                                                    height: 100%;
                                                    object-fit: {{ $element['objectFit'] ?? 'cover' }};
                                                    opacity: {{ $element['opacity'] ?? 1 }};
                                                "
                                            />
                                        @endif
                                    @elseif($element['type'] === 'shape')
                                        <div
                                            style="
                                                width: 100%;
                                                height: 100%;
                                                background-color: {{ $element['fillColor'] ?? 'transparent' }};
                                                border: {{ $element['borderWidth'] ?? 0 }}px {{ $element['borderStyle'] ?? 'solid' }} {{ $element['borderColor'] ?? '#000000' }};
                                                border-radius: {{ $element['borderRadius'] ?? 0 }}px;
                                                opacity: {{ $element['opacity'] ?? 1 }};
                                            "
                                        ></div>
                                    @elseif($element['type'] === 'qr')
                                        <div class="flex items-center justify-center w-full h-full">
                                            <div class="text-center">
                                                <flux:icon name="qr-code" class="w-12 h-12 mx-auto mb-2 text-gray-400" />
                                                <flux:text variant="sm" class="text-gray-500">QR Code Preview</flux:text>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Certificate Information -->
        <div class="space-y-6">
            <!-- Template Details -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Template Details</flux:heading>

                <div class="space-y-3">
                    <div>
                        <flux:text variant="sm" class="font-medium text-gray-500 dark:text-gray-400">Name</flux:text>
                        <flux:text>{{ $certificate->name }}</flux:text>
                    </div>

                    @if($certificate->description)
                        <div>
                            <flux:text variant="sm" class="font-medium text-gray-500 dark:text-gray-400">Description</flux:text>
                            <flux:text>{{ $certificate->description }}</flux:text>
                        </div>
                    @endif

                    <div>
                        <flux:text variant="sm" class="font-medium text-gray-500 dark:text-gray-400">Size & Orientation</flux:text>
                        <flux:text>{{ strtoupper($certificate->size) }} - {{ ucfirst($certificate->orientation) }}</flux:text>
                    </div>

                    <div>
                        <flux:text variant="sm" class="font-medium text-gray-500 dark:text-gray-400">Dimensions</flux:text>
                        <flux:text>{{ $certificate->width }} × {{ $certificate->height }} px</flux:text>
                    </div>

                    <div>
                        <flux:text variant="sm" class="font-medium text-gray-500 dark:text-gray-400">Elements</flux:text>
                        <flux:text>{{ count($certificate->elements ?? []) }} element(s)</flux:text>
                    </div>

                    <div>
                        <flux:text variant="sm" class="font-medium text-gray-500 dark:text-gray-400">Created By</flux:text>
                        <flux:text>{{ $certificate->creator->name }}</flux:text>
                    </div>

                    <div>
                        <flux:text variant="sm" class="font-medium text-gray-500 dark:text-gray-400">Created At</flux:text>
                        <flux:text>{{ $certificate->created_at->format('M d, Y h:i A') }}</flux:text>
                    </div>
                </div>
            </flux:card>

            <!-- Sample Data Used -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Preview Sample Data</flux:heading>

                <div class="space-y-2">
                    @foreach($previewData as $key => $value)
                        <div class="flex justify-between text-sm">
                            <flux:text variant="sm" class="font-medium text-gray-500 dark:text-gray-400">
                                {{ str_replace('_', ' ', ucfirst($key)) }}:
                            </flux:text>
                            <flux:text variant="sm">{{ $value }}</flux:text>
                        </div>
                    @endforeach
                </div>
            </flux:card>

            <!-- Statistics -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Usage Statistics</flux:heading>

                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">Course Assignments</flux:text>
                        <flux:badge variant="neutral">{{ $certificate->courses()->count() }}</flux:badge>
                    </div>

                    <div class="flex justify-between items-center">
                        <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">Class Assignments</flux:text>
                        <flux:badge variant="neutral">{{ $certificate->classes()->count() }}</flux:badge>
                    </div>

                    <div class="flex justify-between items-center">
                        <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">Total Issues</flux:text>
                        <flux:badge variant="neutral">{{ $certificate->issues()->count() }}</flux:badge>
                    </div>

                    <div class="flex justify-between items-center">
                        <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">Active Issues</flux:text>
                        <flux:badge variant="success">{{ $certificate->issuedCertificates()->count() }}</flux:badge>
                    </div>
                </div>
            </flux:card>

            <!-- Quick Actions -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>

                <div class="space-y-2">
                    @if($certificate->status === 'draft')
                        <flux:button variant="primary" wire:click="$parent.activate({{ $certificate->id }})" class="w-full">
                            Activate Certificate
                        </flux:button>
                    @endif

                    @if($certificate->status === 'active')
                        <flux:button variant="outline" href="{{ route('certificates.assignments', $certificate) }}" class="w-full">
                            Manage Assignments
                        </flux:button>
                        <flux:button variant="outline" href="{{ route('certificates.issue') }}?certificate={{ $certificate->id }}" class="w-full">
                            Issue to Student
                        </flux:button>
                    @endif

                    <flux:button variant="outline" href="{{ route('certificates.edit', $certificate) }}" icon="document-duplicate" class="w-full">
                        Duplicate Template
                    </flux:button>
                </div>
            </flux:card>
        </div>
    </div>
</div>
