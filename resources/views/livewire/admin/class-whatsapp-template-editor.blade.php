<?php

use App\Models\ClassModel;
use App\Models\ClassNotificationSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ClassModel $class;
    public ClassNotificationSetting $setting;

    public string $whatsappContent = '';
    public bool $useCustomWhatsappTemplate = true;
    public $whatsappImage = null;
    public ?string $existingImagePath = null;

    // Placeholders
    public array $placeholders = [
        '{{student_name}}' => 'Nama pelajar',
        '{{teacher_name}}' => 'Nama guru',
        '{{class_name}}' => 'Nama kelas',
        '{{course_name}}' => 'Nama kursus',
        '{{session_date}}' => 'Tarikh sesi',
        '{{session_time}}' => 'Masa sesi',
        '{{session_datetime}}' => 'Tarikh & masa',
        '{{location}}' => 'Lokasi',
        '{{meeting_url}}' => 'URL mesyuarat',
        '{{whatsapp_link}}' => 'Pautan WhatsApp',
        '{{duration}}' => 'Tempoh',
        '{{remaining_sessions}}' => 'Sesi berbaki',
        '{{total_sessions}}' => 'Jumlah sesi',
        '{{attendance_rate}}' => 'Kadar kehadiran',
    ];

    // Sample data for preview
    public array $sampleData = [];

    public function mount(ClassModel $class, int $settingId): void
    {
        $this->class = $class;
        $this->setting = ClassNotificationSetting::where('id', $settingId)
            ->where('class_id', $class->id)
            ->firstOrFail();

        $this->whatsappContent = $this->setting->whatsapp_content ?? '';
        $this->useCustomWhatsappTemplate = $this->setting->use_custom_whatsapp_template ?? true;
        $this->existingImagePath = $this->setting->whatsapp_image_path;

        $this->sampleData = [
            '{{student_name}}' => 'Ahmad bin Ali (Contoh)',
            '{{teacher_name}}' => $this->class->teacher?->user?->name ?? 'Guru',
            '{{class_name}}' => $this->class->title,
            '{{course_name}}' => $this->class->course?->name ?? '',
            '{{session_date}}' => now()->addDay()->format('d M Y'),
            '{{session_time}}' => '8:00 PM',
            '{{session_datetime}}' => now()->addDay()->format('d M Y') . ' - 8:00 PM',
            '{{location}}' => $this->class->location ?? 'TBA',
            '{{meeting_url}}' => $this->class->meeting_url ?? 'https://meet.example.com',
            '{{whatsapp_link}}' => $this->class->whatsapp_group_link ?? '',
            '{{duration}}' => '1 jam 30 minit',
            '{{remaining_sessions}}' => '8',
            '{{total_sessions}}' => '12',
            '{{attendance_rate}}' => '95%',
        ];
    }

    public function getNotificationTypeLabelProperty(): array
    {
        $labels = ClassNotificationSetting::getNotificationTypeLabels();
        return $labels[$this->setting->notification_type] ?? ['name' => $this->setting->notification_type, 'description' => ''];
    }

    public function getPreviewContentProperty(): string
    {
        $content = $this->whatsappContent;
        return str_replace(
            array_keys($this->sampleData),
            array_values($this->sampleData),
            $content
        );
    }

    public function getCharacterCountProperty(): int
    {
        return mb_strlen($this->whatsappContent);
    }

    public function getImagePreviewUrlProperty(): ?string
    {
        if ($this->whatsappImage) {
            return $this->whatsappImage->temporaryUrl();
        }

        if ($this->existingImagePath) {
            return Storage::disk('public')->url($this->existingImagePath);
        }

        return null;
    }

    public function insertPlaceholder(string $placeholder): void
    {
        $this->whatsappContent .= $placeholder;
    }

    public function removeImage(): void
    {
        $this->whatsappImage = null;
        $this->existingImagePath = null;
    }

    public function save(): void
    {
        $this->validate([
            'whatsappContent' => 'required|string|max:4096',
            'whatsappImage' => 'nullable|image|max:5120', // 5MB max
        ]);

        $imagePath = $this->existingImagePath;

        // Handle image upload
        if ($this->whatsappImage) {
            // Delete old image if exists
            if ($this->existingImagePath) {
                Storage::disk('public')->delete($this->existingImagePath);
            }

            $imagePath = $this->whatsappImage->store('whatsapp-images', 'public');
        } elseif (!$this->existingImagePath && $this->setting->whatsapp_image_path) {
            // User removed image
            Storage::disk('public')->delete($this->setting->whatsapp_image_path);
            $imagePath = null;
        }

        $this->setting->update([
            'whatsapp_content' => $this->whatsappContent,
            'use_custom_whatsapp_template' => $this->useCustomWhatsappTemplate,
            'whatsapp_image_path' => $imagePath,
            'whatsapp_enabled' => true, // Enable WhatsApp when saving custom template
        ]);

        $this->existingImagePath = $imagePath;
        $this->whatsappImage = null;

        $this->dispatch('notify',
            type: 'success',
            message: 'Templat WhatsApp berjaya disimpan',
        );
    }

    public function sendTestMessage(): void
    {
        $user = Auth::user();
        $phoneNumber = $user->phone_number ?? $user->phone;

        if (empty($phoneNumber)) {
            $this->dispatch('notify',
                type: 'error',
                message: 'Nombor telefon anda tidak ditetapkan. Sila kemaskini profil anda terlebih dahulu.',
            );
            return;
        }

        // Update config before instantiating service
        $apiToken = app(\App\Services\SettingsService::class)->get('whatsapp_api_token');
        if (!empty($apiToken)) {
            config(['services.onsend.api_token' => $apiToken]);
            config(['services.onsend.enabled' => true]);
        }

        $whatsApp = new \App\Services\WhatsAppService();

        if (!$whatsApp->isEnabled()) {
            $this->dispatch('notify',
                type: 'error',
                message: 'Perkhidmatan WhatsApp tidak diaktifkan. Sila konfigurasikan dalam Tetapan > WhatsApp.',
            );
            return;
        }

        // Normalize phone number
        $normalizedPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (str_starts_with($normalizedPhone, '0')) {
            $normalizedPhone = '60' . substr($normalizedPhone, 1);
        } elseif (!str_starts_with($normalizedPhone, '60')) {
            $normalizedPhone = '60' . $normalizedPhone;
        }

        $message = "[UJIAN] " . $this->notificationTypeLabel['name'] . "\n\n" . $this->previewContent . "\n\n— Ini adalah mesej ujian —";

        // Send image first if exists
        $imageUrl = null;
        if ($this->whatsappImage) {
            $tempPath = $this->whatsappImage->store('whatsapp-images/temp', 'public');
            $imageUrl = Storage::disk('public')->url($tempPath);
        } elseif ($this->existingImagePath) {
            $imageUrl = Storage::disk('public')->url($this->existingImagePath);
        }

        if ($imageUrl) {
            $whatsApp->sendImage($normalizedPhone, $imageUrl);
            usleep(500000); // 0.5 second delay
        }

        $result = $whatsApp->send($normalizedPhone, $message);

        if ($result['success']) {
            $this->dispatch('notify',
                type: 'success',
                message: "Mesej WhatsApp ujian telah dihantar ke {$normalizedPhone}",
            );
        } else {
            $this->dispatch('notify',
                type: 'error',
                message: 'Gagal menghantar mesej ujian: ' . ($result['error'] ?? 'Unknown error'),
            );
        }
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <flux:button
                variant="ghost"
                href="{{ route('classes.show', ['class' => $class->id, 'tab' => 'notifications']) }}"
            >
                <flux:icon.arrow-left class="w-5 h-5" />
            </flux:button>
            <div>
                <flux:heading size="xl">Editor Templat WhatsApp</flux:heading>
                <flux:text class="text-gray-500">{{ $class->title }} - {{ $this->notificationTypeLabel['name'] }}</flux:text>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Editor Panel -->
                <flux:card>
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <flux:heading size="lg">
                                <flux:icon.device-phone-mobile class="w-5 h-5 inline mr-2 text-green-600" />
                                Kandungan Mesej
                            </flux:heading>
                            <div class="text-sm {{ $this->characterCount > 4000 ? 'text-red-600' : ($this->characterCount > 3500 ? 'text-amber-600' : 'text-gray-500') }}">
                                {{ number_format($this->characterCount) }} / 4,096
                            </div>
                        </div>

                        <!-- Message Editor -->
                        <div class="space-y-4">
                            <flux:textarea
                                wire:model.live="whatsappContent"
                                rows="12"
                                placeholder="Tulis mesej WhatsApp anda di sini..."
                                class="font-mono text-sm"
                            />

                            <!-- Character limit warning -->
                            @if($this->characterCount > 4000)
                                <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                                    <div class="flex items-center gap-2 text-red-800">
                                        <flux:icon.exclamation-triangle class="w-4 h-4" />
                                        <span class="text-sm font-medium">Amaran: Mesej terlalu panjang!</span>
                                    </div>
                                    <p class="text-xs text-red-600 mt-1">WhatsApp mempunyai had 4,096 aksara. Sila pendekkan mesej anda.</p>
                                </div>
                            @endif

                            <!-- Placeholders -->
                            <div>
                                <flux:text class="text-sm font-medium text-gray-700 mb-2">Placeholder (Klik untuk tambah)</flux:text>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($placeholders as $placeholder => $label)
                                        <button
                                            type="button"
                                            wire:click="insertPlaceholder('{{ $placeholder }}')"
                                            class="inline-flex items-center px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs rounded-md transition-colors"
                                            title="{{ $label }}"
                                        >
                                            {{ $placeholder }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Formatting Guide -->
                            <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                                <flux:text class="text-sm font-medium text-green-800 mb-2">Panduan Format WhatsApp</flux:text>
                                <div class="grid grid-cols-2 gap-2 text-xs text-green-700">
                                    <div><code>*bold*</code> = <strong>bold</strong></div>
                                    <div><code>_italic_</code> = <em>italic</em></div>
                                    <div><code>~strikethrough~</code> = <del>strikethrough</del></div>
                                    <div><code>```code```</code> = <code>code</code></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </flux:card>

                <!-- Preview & Image Panel -->
                <div class="space-y-6">
                    <!-- Preview -->
                    <flux:card>
                        <div class="p-6">
                            <flux:heading size="lg" class="mb-4">
                                <flux:icon.eye class="w-5 h-5 inline mr-2 text-gray-600" />
                                Pratonton Mesej
                            </flux:heading>

                            <!-- WhatsApp Style Preview -->
                            <div class="bg-[#e5ddd5] rounded-lg p-4 min-h-[300px]">
                                <div class="max-w-[85%] ml-auto">
                                    <!-- Image Preview -->
                                    @if($this->imagePreviewUrl)
                                        <div class="bg-white rounded-lg rounded-br-none shadow-sm mb-1 overflow-hidden">
                                            <img
                                                src="{{ $this->imagePreviewUrl }}"
                                                alt="WhatsApp Image"
                                                class="w-full h-auto max-h-48 object-cover"
                                            />
                                        </div>
                                    @endif

                                    <!-- Message Bubble -->
                                    <div class="bg-[#dcf8c6] rounded-lg rounded-br-none p-3 shadow-sm">
                                        <p class="text-sm text-gray-800 whitespace-pre-wrap break-words">{!! nl2br(e($this->previewContent)) !!}</p>
                                        <div class="flex items-center justify-end gap-1 mt-1">
                                            <span class="text-[10px] text-gray-500">{{ now()->format('H:i') }}</span>
                                            <svg class="w-4 h-4 text-blue-500" viewBox="0 0 16 15" fill="currentColor">
                                                <path d="M15.01 3.316l-.478-.372a.365.365 0 0 0-.51.063L8.666 9.879a.32.32 0 0 1-.484.033l-.358-.325a.319.319 0 0 0-.484.032l-.378.483a.418.418 0 0 0 .036.541l1.32 1.266c.143.14.361.125.484-.033l6.272-8.048a.366.366 0 0 0-.064-.512zm-4.1 0l-.478-.372a.365.365 0 0 0-.51.063L4.566 9.879a.32.32 0 0 1-.484.033L1.891 7.769a.366.366 0 0 0-.515.006l-.423.433a.364.364 0 0 0 .006.514l3.258 3.185c.143.14.361.125.484-.033l6.272-8.048a.365.365 0 0 0-.063-.51z"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <p class="text-xs text-gray-500 mt-2 text-center">
                                <flux:icon.information-circle class="w-3 h-3 inline" />
                                Ini adalah pratonton dengan data contoh. Data sebenar akan digunakan semasa penghantaran.
                            </p>
                        </div>
                    </flux:card>

                    <!-- Image Upload -->
                    <flux:card>
                        <div class="p-6">
                            <flux:heading size="lg" class="mb-4">
                                <flux:icon.photo class="w-5 h-5 inline mr-2 text-gray-600" />
                                Gambar Lampiran
                            </flux:heading>

                            @if($this->imagePreviewUrl)
                                <div class="relative mb-4">
                                    <img
                                        src="{{ $this->imagePreviewUrl }}"
                                        alt="WhatsApp Image"
                                        class="w-full h-48 object-cover rounded-lg border border-gray-200"
                                    />
                                    <button
                                        type="button"
                                        wire:click="removeImage"
                                        class="absolute top-2 right-2 p-1.5 bg-red-500 hover:bg-red-600 text-white rounded-full shadow-lg transition-colors"
                                        title="Buang gambar"
                                    >
                                        <flux:icon.x-mark class="w-4 h-4" />
                                    </button>
                                </div>
                            @endif

                            <div
                                x-data="{ isDragging: false }"
                                x-on:dragover.prevent="isDragging = true"
                                x-on:dragleave.prevent="isDragging = false"
                                x-on:drop.prevent="isDragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                                :class="isDragging ? 'border-green-500 bg-green-50' : 'border-gray-300'"
                                class="border-2 border-dashed rounded-lg p-6 text-center transition-colors"
                            >
                                <input
                                    type="file"
                                    wire:model="whatsappImage"
                                    accept="image/jpeg,image/png,image/gif"
                                    class="hidden"
                                    x-ref="fileInput"
                                    id="whatsappImageInput"
                                />
                                <label for="whatsappImageInput" class="cursor-pointer">
                                    <flux:icon.cloud-arrow-up class="w-10 h-10 mx-auto text-gray-400 mb-2" />
                                    <p class="text-sm text-gray-600">
                                        <span class="text-green-600 font-medium">Klik untuk muat naik</span>
                                        atau seret dan lepas
                                    </p>
                                    <p class="text-xs text-gray-400 mt-1">JPG, PNG, GIF (Maks 5MB)</p>
                                </label>

                                <div wire:loading wire:target="whatsappImage" class="mt-3">
                                    <div class="flex items-center justify-center gap-2 text-green-600">
                                        <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-sm">Memuat naik...</span>
                                    </div>
                                </div>
                            </div>

                            @error('whatsappImage')
                                <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                            @enderror
                        </div>
                    </flux:card>

                    <!-- Actions -->
                    <div class="flex items-center justify-between">
                        <flux:button
                            variant="outline"
                            wire:click="sendTestMessage"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="sendTestMessage">
                                <flux:icon.paper-airplane class="w-4 h-4 mr-2" />
                                Hantar Mesej Ujian
                            </span>
                            <span wire:loading wire:target="sendTestMessage">
                                <svg class="animate-spin h-4 w-4 mr-2 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Menghantar...
                            </span>
                        </flux:button>

                        <flux:button
                            variant="primary"
                            wire:click="save"
                            wire:loading.attr="disabled"
                            class="bg-green-600 hover:bg-green-700"
                        >
                            <span wire:loading.remove wire:target="save">
                                <flux:icon.check class="w-4 h-4 mr-2" />
                                Simpan Templat
                            </span>
                            <span wire:loading wire:target="save">
                                Menyimpan...
                            </span>
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div
        x-data="{ show: false, message: '', type: 'success' }"
        x-on:notify.window="
            show = true;
            message = $event.detail.message || 'Operasi berjaya';
            type = $event.detail.type || 'success';
            setTimeout(() => show = false, 4000)
        "
        x-show="show"
        x-transition
        class="fixed bottom-4 right-4 z-50"
        style="display: none;"
    >
        <div
            x-show="type === 'success'"
            class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg shadow-lg"
        >
            <flux:icon.check-circle class="w-5 h-5 text-green-600" />
            <span x-text="message"></span>
        </div>
        <div
            x-show="type === 'error'"
            class="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg shadow-lg"
        >
            <flux:icon.exclamation-circle class="w-5 h-5 text-red-600" />
            <span x-text="message"></span>
        </div>
    </div>
</div>
