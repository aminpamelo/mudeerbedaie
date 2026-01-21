<?php

use App\Models\NotificationTemplate;
use App\Models\EmailStarterTemplate;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new
#[Layout('components.layouts.email-builder')]
class extends Component {
    public NotificationTemplate $template;
    public string $designJson = '';
    public string $initialHtml = '';
    public string $previewMode = 'desktop';
    public bool $showPreview = false;
    public string $previewHtml = '';

    // Auto-save
    public bool $autoSaveEnabled = true;
    public ?string $lastSavedAt = null;
    public bool $hasUnsavedChanges = false;

    // Gallery
    public bool $showGallery = false;
    public string $galleryCategory = 'all';

    public function mount(NotificationTemplate $template): void
    {
        // Only allow email templates
        if ($template->channel !== 'email') {
            session()->flash('error', 'Visual builder hanya tersedia untuk templat e-mel.');
            $this->redirect(route('admin.settings.notifications'));
            return;
        }

        $this->template = $template;

        // Don't load design_json directly - it can cause browser freeze due to circular references
        // Instead, load HTML content which is safer and more reliable
        $this->designJson = '';
        $this->initialHtml = $template->html_content ?? '';

        // Show gallery if template has no content
        if (empty($template->html_content) && empty($template->design_json)) {
            $this->showGallery = true;
        }
    }

    public function saveDesign(string $designJson, string $html, string $css): void
    {
        $finalHtml = $this->compileEmailHtml($html, $css);

        $this->template->update([
            'design_json' => json_decode($designJson, true),
            'html_content' => $finalHtml,
            'editor_type' => 'visual',
        ]);

        $this->lastSavedAt = now()->format('H:i');
        $this->hasUnsavedChanges = false;

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Templat berjaya disimpan!',
        ]);
    }

    public function autoSave(string $designJson, string $html, string $css): void
    {
        if (!$this->autoSaveEnabled) return;

        $finalHtml = $this->compileEmailHtml($html, $css);

        $this->template->update([
            'design_json' => json_decode($designJson, true),
            'html_content' => $finalHtml,
            'editor_type' => 'visual',
        ]);

        $this->lastSavedAt = now()->format('H:i');
        $this->hasUnsavedChanges = false;
    }

    public function toggleAutoSave(): void
    {
        $this->autoSaveEnabled = !$this->autoSaveEnabled;
    }

    public function previewEmail(): void
    {
        $html = $this->template->html_content ?? '';

        $sampleData = [
            '{{student_name}}' => 'Ahmad bin Abdullah',
            '{{teacher_name}}' => 'Ustaz Muhammad',
            '{{class_name}}' => 'Kelas Tajwid Asas',
            '{{course_name}}' => 'Kursus Al-Quran',
            '{{session_date}}' => now()->addDay()->format('d M Y'),
            '{{session_time}}' => '10:00 AM',
            '{{session_datetime}}' => now()->addDay()->format('d M Y, g:i A'),
            '{{location}}' => 'Bilik 101, Bangunan A',
            '{{meeting_url}}' => 'https://meet.google.com/abc-defg-hij',
            '{{whatsapp_link}}' => 'https://chat.whatsapp.com/invite/abc123',
            '{{duration}}' => '2 jam',
            '{{remaining_sessions}}' => '8',
            '{{total_sessions}}' => '12',
            '{{attendance_rate}}' => '85',
        ];

        foreach ($sampleData as $placeholder => $value) {
            $html = str_replace($placeholder, $value, $html);
        }

        $this->previewHtml = $html;
        $this->showPreview = true;
    }

    public function closePreview(): void
    {
        $this->showPreview = false;
        $this->previewHtml = '';
    }

    public function openGallery(): void
    {
        $this->showGallery = true;
    }

    public function closeGallery(): void
    {
        $this->showGallery = false;
    }

    public function selectStarterTemplate(int $starterId): void
    {
        $starter = EmailStarterTemplate::find($starterId);

        if ($starter) {
            // Use HTML content instead of design_json for better compatibility
            // design_json will be populated when user saves
            $this->designJson = '';
            $this->dispatch('load-starter-html', html: $starter->html_content ?? '');
        }

        $this->showGallery = false;
    }

    public function startBlank(): void
    {
        $this->designJson = '';
        $this->dispatch('load-design', design: []);
        $this->showGallery = false;
    }

    public function getPlaceholdersProperty(): array
    {
        return NotificationTemplate::getAvailablePlaceholders();
    }

    public function getStarterTemplatesProperty()
    {
        if (!class_exists(EmailStarterTemplate::class)) {
            return collect([]);
        }

        return EmailStarterTemplate::query()
            ->where('is_active', true)
            ->when($this->galleryCategory !== 'all', fn($q) =>
                $q->where('category', $this->galleryCategory)
            )
            ->orderBy('sort_order')
            ->get();
    }

    public function getGalleryCategoriesProperty(): array
    {
        return [
            'all' => 'Semua',
            'blank' => 'Kosong',
            'reminder' => 'Peringatan',
            'welcome' => 'Selamat Datang',
            'followup' => 'Susulan',
            'marketing' => 'Pemasaran',
        ];
    }

    protected function compileEmailHtml(string $html, string $css): string
    {
        $emailHtml = <<<HTML
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{$this->template->name}</title>
    <style type="text/css">
        /* Reset styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        /* Custom styles */
        {$css}
    </style>
    <!--[if mso]>
    <style type="text/css">
        body, table, td {
            font-family: Arial, Helvetica, sans-serif !important;
        }
    </style>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="background-color: #ffffff; max-width: 600px;">
                    <tr>
                        <td style="padding: 0;">
                            {$html}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

        return $emailHtml;
    }
}
?>

<div class="email-builder-container" x-data="emailBuilder(@js($initialHtml), @js($autoSaveEnabled))">
    {{-- Header --}}
    <header class="builder-header">
        <div class="builder-header-left">
            <a href="{{ route('admin.settings.notifications') }}" class="builder-back-btn" title="Kembali">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div class="builder-header-divider"></div>
            <div class="builder-title">
                <h1>{{ $template->name }}</h1>
                <span class="builder-subtitle">{{ $template->type }} &bull; {{ strtoupper($template->language) }}</span>
            </div>
        </div>

        <div class="builder-header-center">
            {{-- Device Toggle --}}
            <div class="builder-device-toggle">
                <button
                    type="button"
                    :class="{ 'active': deviceMode === 'desktop' }"
                    @click="setDevice('desktop')"
                    title="Desktop"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </button>
                <button
                    type="button"
                    :class="{ 'active': deviceMode === 'tablet' }"
                    @click="setDevice('tablet')"
                    title="Tablet"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                </button>
                <button
                    type="button"
                    :class="{ 'active': deviceMode === 'mobile' }"
                    @click="setDevice('mobile')"
                    title="Mobile"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                </button>
            </div>
        </div>

        <div class="builder-header-right">
            {{-- Actions --}}
            <div class="builder-actions">
                <button type="button" @click="undo()" class="builder-action-btn" title="Undo (Ctrl+Z)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                    </svg>
                </button>
                <button type="button" @click="redo()" class="builder-action-btn" title="Redo (Ctrl+Y)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"></path>
                    </svg>
                </button>
                <button type="button" @click="clear()" class="builder-action-btn builder-action-btn-danger" title="Kosongkan">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </div>

            <div class="builder-header-divider"></div>

            {{-- Auto-save Status --}}
            <div class="builder-autosave" x-show="autoSaveEnabled">
                <template x-if="isAutoSaving">
                    <span class="builder-autosave-saving">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Menyimpan...
                    </span>
                </template>
                <template x-if="!isAutoSaving && lastSaved">
                    <span class="builder-autosave-saved">
                        <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Disimpan <span x-text="lastSaved"></span>
                    </span>
                </template>
            </div>

            <button
                type="button"
                wire:click="openGallery"
                class="builder-btn builder-btn-outline"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                </svg>
                Templat
            </button>

            <button
                type="button"
                wire:click="previewEmail"
                class="builder-btn builder-btn-outline"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                Pratonton
            </button>

            <button
                type="button"
                @click="save()"
                :disabled="isSaving"
                class="builder-btn builder-btn-primary"
            >
                <template x-if="isSaving">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </template>
                <template x-if="!isSaving">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                    </svg>
                </template>
                <span x-text="isSaving ? 'Menyimpan...' : 'Simpan'"></span>
            </button>
        </div>
    </header>

    {{-- Main Content - Three Panel Layout --}}
    <main class="builder-main">
        {{-- Left Panel - Blocks --}}
        <aside class="builder-panel builder-panel-left" :class="{ 'collapsed': leftPanelCollapsed }">
            <div class="builder-panel-header">
                <span class="builder-panel-title">Blok</span>
                <button type="button" @click="leftPanelCollapsed = !leftPanelCollapsed" class="builder-panel-toggle">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" :class="{ 'rotate-180': leftPanelCollapsed }">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </button>
            </div>

            <div class="builder-panel-content" x-show="!leftPanelCollapsed">
                {{-- Block Search --}}
                <div class="builder-search">
                    <svg class="builder-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input
                        type="text"
                        x-model="blockSearch"
                        placeholder="Cari blok..."
                        class="builder-search-input"
                    >
                </div>

                {{-- GrapeJS Blocks Container - wire:ignore prevents Livewire from wiping it on re-render --}}
                <div x-ref="blocksContainer" class="builder-blocks" wire:ignore></div>
            </div>
        </aside>

        {{-- Center - Canvas --}}
        <div class="builder-canvas-container">
            <div class="builder-canvas" wire:ignore>
                <div x-ref="editorCanvas" class="builder-canvas-inner"></div>
            </div>
        </div>

        {{-- Right Panel - Properties & Placeholders --}}
        <aside class="builder-panel builder-panel-right" :class="{ 'collapsed': rightPanelCollapsed }">
            <div class="builder-panel-header">
                <span class="builder-panel-title">Properties</span>
                <button type="button" @click="rightPanelCollapsed = !rightPanelCollapsed" class="builder-panel-toggle">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" :class="{ 'rotate-180': !rightPanelCollapsed }">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>

            <div class="builder-panel-content" x-show="!rightPanelCollapsed">
                {{-- Panel Tabs --}}
                <div class="builder-panel-tabs">
                    <button
                        type="button"
                        @click="rightPanelTab = 'traits'"
                        :class="{ 'active': rightPanelTab === 'traits' }"
                        class="builder-panel-tab"
                    >
                        Tetapan
                    </button>
                    <button
                        type="button"
                        @click="rightPanelTab = 'styles'"
                        :class="{ 'active': rightPanelTab === 'styles' }"
                        class="builder-panel-tab"
                    >
                        Gaya
                    </button>
                    <button
                        type="button"
                        @click="rightPanelTab = 'placeholders'"
                        :class="{ 'active': rightPanelTab === 'placeholders' }"
                        class="builder-panel-tab"
                    >
                        Placeholder
                    </button>
                </div>

                {{-- Traits Panel - wire:ignore prevents Livewire from wiping it on re-render --}}
                <div x-show="rightPanelTab === 'traits'" x-ref="traitsContainer" class="builder-traits" wire:ignore></div>

                {{-- Styles Panel - wire:ignore prevents Livewire from wiping it on re-render --}}
                <div x-show="rightPanelTab === 'styles'" x-ref="stylesContainer" class="builder-styles" wire:ignore></div>

                {{-- Placeholders Panel --}}
                <div x-show="rightPanelTab === 'placeholders'" class="builder-placeholders">
                    <p class="text-xs text-gray-500 mb-3 px-3">Klik untuk memasukkan ke komponen yang dipilih:</p>
                    @foreach($this->placeholders as $placeholder => $description)
                        <button
                            type="button"
                            class="builder-placeholder-item"
                            @click="insertPlaceholder('{{ $placeholder }}')"
                        >
                            <code>{{ $placeholder }}</code>
                            <span>{{ $description }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </aside>
    </main>

    {{-- Template Gallery Modal --}}
    @if($showGallery)
        <div class="builder-modal-overlay" @click.self="$wire.closeGallery()">
            <div class="builder-modal builder-modal-gallery">
                <div class="builder-modal-header">
                    <h2>Pilih Templat</h2>
                    <button type="button" wire:click="closeGallery" class="builder-modal-close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="builder-modal-body">
                    {{-- Category Tabs --}}
                    <div class="builder-gallery-tabs">
                        @foreach($this->galleryCategories as $key => $label)
                            <button
                                type="button"
                                wire:click="$set('galleryCategory', '{{ $key }}')"
                                class="builder-gallery-tab {{ $galleryCategory === $key ? 'active' : '' }}"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Templates Grid --}}
                    <div class="builder-gallery-grid">
                        {{-- Blank Template --}}
                        <button
                            type="button"
                            wire:click="startBlank"
                            class="builder-gallery-item builder-gallery-item-blank"
                        >
                            <div class="builder-gallery-preview">
                                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </div>
                            <div class="builder-gallery-info">
                                <h3>Mula Kosong</h3>
                                <p>Bina dari awal dengan kanvas kosong</p>
                            </div>
                        </button>

                        @foreach($this->starterTemplates as $starter)
                            <button
                                type="button"
                                wire:click="selectStarterTemplate({{ $starter->id }})"
                                class="builder-gallery-item"
                            >
                                <div class="builder-gallery-preview">
                                    @if($starter->thumbnail)
                                        <img src="{{ $starter->thumbnail }}" alt="{{ $starter->name }}">
                                    @else
                                        <div class="builder-gallery-preview-placeholder">
                                            <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="builder-gallery-info">
                                    <h3>{{ $starter->name }}</h3>
                                    <p>{{ $starter->description ?? 'Templat ' . $starter->category }}</p>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Preview Modal --}}
    @if($showPreview)
        <div class="builder-modal-overlay" @click.self="$wire.closePreview()">
            <div class="builder-modal builder-modal-preview">
                <div class="builder-modal-header">
                    <h2>Pratonton E-mel</h2>
                    <button type="button" wire:click="closePreview" class="builder-modal-close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="builder-modal-body builder-preview-body">
                    <div class="builder-preview-frame">
                        <iframe
                            srcdoc="{{ $previewHtml }}"
                            class="builder-preview-iframe"
                            sandbox="allow-same-origin"
                        ></iframe>
                    </div>
                </div>
                <div class="builder-modal-footer">
                    <button type="button" wire:click="closePreview" class="builder-btn builder-btn-outline">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Unsaved Changes Toast --}}
    <div
        x-show="hasChanges && !autoSaveEnabled"
        x-transition
        class="builder-toast builder-toast-warning"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <span>Terdapat perubahan yang belum disimpan</span>
    </div>
</div>
