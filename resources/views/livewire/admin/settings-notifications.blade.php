<?php

use App\Models\NotificationTemplate;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public bool $showEditModal = false;
    public bool $showPreviewModal = false;
    public ?NotificationTemplate $editingTemplate = null;

    // Form fields
    public string $name = '';
    public string $slug = '';
    public string $type = 'session_reminder';
    public string $channel = 'email';
    public string $subject = '';
    public string $content = '';
    public string $language = 'ms';
    public bool $is_active = true;

    // Preview
    public string $previewSubject = '';
    public string $previewContent = '';
    public bool $previewIsVisual = false;

    public function getTemplatesProperty()
    {
        return NotificationTemplate::query()
            ->orderBy('type')
            ->orderBy('language')
            ->orderBy('name')
            ->paginate(15);
    }

    public function createTemplate(): void
    {
        $this->resetForm();
        $this->showEditModal = true;
    }

    public function editTemplate(NotificationTemplate $template): void
    {
        $this->editingTemplate = $template;
        $this->name = $template->name;
        $this->slug = $template->slug;
        $this->type = $template->type;
        $this->channel = $template->channel;
        $this->subject = $template->subject ?? '';
        $this->content = $template->content;
        $this->language = $template->language;
        $this->is_active = $template->is_active;
        $this->showEditModal = true;
    }

    public function saveTemplate(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:notification_templates,slug,' . ($this->editingTemplate?->id ?? ''),
            'type' => 'required|in:session_reminder,session_followup,class_update,enrollment_welcome,class_completed',
            'channel' => 'required|in:email,whatsapp,sms',
            'subject' => 'nullable|string|max:255',
            'content' => 'required|string',
            'language' => 'required|in:ms,en',
        ]);

        $data = [
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'channel' => $this->channel,
            'subject' => $this->subject ?: null,
            'content' => $this->content,
            'language' => $this->language,
            'is_active' => $this->is_active,
            'is_system' => false,
        ];

        if ($this->editingTemplate) {
            $this->editingTemplate->update($data);
            $message = 'Templat notifikasi berjaya dikemaskini';
        } else {
            NotificationTemplate::create($data);
            $message = 'Templat notifikasi berjaya dicipta';
        }

        $this->showEditModal = false;
        $this->resetForm();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    public function toggleActive(NotificationTemplate $template): void
    {
        $template->update(['is_active' => !$template->is_active]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $template->is_active
                ? 'Templat telah diaktifkan'
                : 'Templat telah dinyahaktifkan',
        ]);
    }

    public function duplicateTemplate(NotificationTemplate $template): void
    {
        $newTemplate = $template->replicate();
        $newTemplate->name = $template->name . ' (Salinan)';
        $newTemplate->slug = $template->slug . '-copy-' . now()->timestamp;
        $newTemplate->is_system = false;
        $newTemplate->save();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Templat berjaya disalin',
        ]);
    }

    public function deleteTemplate(NotificationTemplate $template): void
    {
        if ($template->is_system) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Templat sistem tidak boleh dipadam',
            ]);
            return;
        }

        $template->delete();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Templat berjaya dipadam',
        ]);
    }

    public function previewTemplate(NotificationTemplate $template): void
    {
        $placeholders = [
            '{{student_name}}' => 'Ahmad bin Ali',
            '{{teacher_name}}' => 'Ustaz Muhammad',
            '{{class_name}}' => 'Kelas Tajwid Asas',
            '{{course_name}}' => 'Tajwid Al-Quran',
            '{{session_date}}' => now()->addDay()->format('d M Y'),
            '{{session_time}}' => '8:00 PM',
            '{{session_datetime}}' => now()->addDay()->format('d M Y') . ' - 8:00 PM',
            '{{location}}' => 'Bilik 101, Masjid Al-Falah',
            '{{meeting_url}}' => 'https://meet.google.com/abc-defg-hij',
            '{{whatsapp_link}}' => 'https://chat.whatsapp.com/example',
            '{{duration}}' => '1 jam 30 minit',
            '{{remaining_sessions}}' => '8',
            '{{total_sessions}}' => '12',
            '{{attendance_rate}}' => '95%',
        ];

        $this->previewSubject = str_replace(
            array_keys($placeholders),
            array_values($placeholders),
            $template->subject ?? ''
        );

        // Check if template uses visual editor
        $this->previewIsVisual = $template->isVisualEditor() && $template->html_content;

        if ($this->previewIsVisual) {
            $this->previewContent = str_replace(
                array_keys($placeholders),
                array_values($placeholders),
                $template->html_content
            );
        } else {
            $this->previewContent = str_replace(
                array_keys($placeholders),
                array_values($placeholders),
                $template->content
            );
        }

        $this->showPreviewModal = true;
    }

    public function resetForm(): void
    {
        $this->editingTemplate = null;
        $this->name = '';
        $this->slug = '';
        $this->type = 'session_reminder';
        $this->channel = 'email';
        $this->subject = '';
        $this->content = '';
        $this->language = 'ms';
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function getTypeOptions(): array
    {
        return NotificationTemplate::getNotificationTypes();
    }

    public function getChannelOptions(): array
    {
        return NotificationTemplate::getChannels();
    }

    public function getLanguageOptions(): array
    {
        return [
            'ms' => 'Bahasa Melayu',
            'en' => 'English',
        ];
    }

    public function layout(): string
    {
        return 'components.admin.settings-layout';
    }

    public function layoutData(): array
    {
        return [
            'title' => 'Notification Templates',
            'activeTab' => 'notifications',
        ];
    }
}
?>

<div>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg">Templat Notifikasi</flux:heading>
                <flux:text class="mt-1 text-gray-500">
                    Urus templat notifikasi global untuk digunakan dalam semua kelas.
                </flux:text>
            </div>
            <flux:button variant="primary" wire:click="createTemplate" icon="plus">
                Cipta Templat
            </flux:button>
        </div>

        <!-- Placeholder Reference -->
        <flux:card>
            <div class="p-4">
                <div class="flex items-center justify-between mb-3">
                    <flux:heading size="sm">Placeholder yang Tersedia</flux:heading>
                    <flux:text class="text-xs text-gray-400">Klik untuk salin</flux:text>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 text-sm">
                    @foreach(NotificationTemplate::getAvailablePlaceholders() as $placeholder => $description)
                        <div
                            x-data="{ copied: false }"
                            class="flex items-start gap-2 group cursor-pointer p-1.5 -m-1.5 rounded hover:bg-gray-50 transition-colors"
                            @click="
                                navigator.clipboard.writeText('{{ $placeholder }}');
                                copied = true;
                                setTimeout(() => copied = false, 1500);
                            "
                        >
                            <code
                                class="text-xs px-1.5 py-0.5 rounded shrink-0 transition-colors"
                                :class="copied ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700 group-hover:bg-blue-100 group-hover:text-blue-700'"
                                x-text="copied ? 'Disalin!' : '{{ $placeholder }}'"
                            ></code>
                            <span class="text-gray-500 text-xs" x-show="!copied">{{ $description }}</span>
                            <flux:icon name="check" class="w-3 h-3 text-green-600" x-show="copied" x-cloak />
                        </div>
                    @endforeach
                </div>
            </div>
        </flux:card>

        <!-- Templates Table -->
        <flux:card>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 dark:bg-zinc-700/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nama</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jenis</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Saluran</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bahasa</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-zinc-700">
                        @forelse($this->templates as $template)
                            <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700/50">
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="font-medium text-gray-900">{{ $template->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $template->slug }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge color="zinc" size="sm">
                                        {{ $this->getTypeOptions()[$template->type] ?? $template->type }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $channelIcon = match($template->channel) {
                                            'email' => 'envelope',
                                            'whatsapp' => 'chat-bubble-left-right',
                                            'sms' => 'device-phone-mobile',
                                            default => 'bell',
                                        };
                                    @endphp
                                    <div class="flex items-center gap-1.5">
                                        <flux:icon :name="$channelIcon" class="w-4 h-4 text-gray-400" />
                                        <span class="text-sm text-gray-600">{{ $this->getChannelOptions()[$template->channel] ?? $template->channel }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm">{{ strtoupper($template->language) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        @if($template->is_active)
                                            <flux:badge color="green" size="sm">Aktif</flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm">Tidak Aktif</flux:badge>
                                        @endif
                                        @if($template->is_system)
                                            <flux:badge color="blue" size="sm">Sistem</flux:badge>
                                        @endif
                                        @if($template->editor_type === 'visual')
                                            <flux:badge color="purple" size="sm">Visual</flux:badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="previewTemplate({{ $template->id }})"
                                            icon="eye"
                                            title="Pratonton"
                                        />
                                        @if($template->channel === 'email')
                                            <a
                                                href="{{ route('admin.settings.notifications.builder', $template) }}"
                                                class="inline-flex items-center justify-center rounded-lg text-sm font-medium transition-colors h-8 px-2 text-purple-600 hover:text-purple-800 hover:bg-purple-50"
                                                title="Visual Builder"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                                                </svg>
                                            </a>
                                        @endif
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="editTemplate({{ $template->id }})"
                                            icon="pencil"
                                            title="Edit"
                                        />
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="duplicateTemplate({{ $template->id }})"
                                            icon="document-duplicate"
                                            title="Salin"
                                        />
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="toggleActive({{ $template->id }})"
                                            :icon="$template->is_active ? 'eye-slash' : 'eye'"
                                            :title="$template->is_active ? 'Nyahaktifkan' : 'Aktifkan'"
                                        />
                                        @unless($template->is_system)
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                wire:click="deleteTemplate({{ $template->id }})"
                                                wire:confirm="Adakah anda pasti untuk memadam templat ini?"
                                                icon="trash"
                                                class="text-red-600 hover:text-red-800"
                                                title="Padam"
                                            />
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    <flux:icon.document-text class="w-8 h-8 mx-auto mb-2 text-gray-300" />
                                    <p>Tiada templat notifikasi.</p>
                                    <p class="text-sm mt-1">Klik butang "Cipta Templat" untuk mula.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($this->templates->hasPages())
                <div class="px-4 py-3 border-t border-gray-200 dark:border-zinc-700">
                    {{ $this->templates->links() }}
                </div>
            @endif
        </flux:card>
    </div>

    <!-- Edit/Create Modal -->
    <flux:modal wire:model="showEditModal" class="max-w-3xl">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">
                {{ $editingTemplate ? 'Edit Templat Notifikasi' : 'Cipta Templat Notifikasi' }}
            </flux:heading>

            <form wire:submit="saveTemplate" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Nama Templat *</flux:label>
                        <flux:input wire:model="name" placeholder="Peringatan Sesi 24 Jam" />
                        <flux:error name="name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Slug *</flux:label>
                        <flux:input wire:model="slug" placeholder="session-reminder-24h-ms" />
                        <flux:error name="slug" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <flux:field>
                        <flux:label>Jenis *</flux:label>
                        <flux:select wire:model="type">
                            @foreach($this->getTypeOptions() as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="type" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Saluran *</flux:label>
                        <flux:select wire:model="channel">
                            @foreach($this->getChannelOptions() as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="channel" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Bahasa *</flux:label>
                        <flux:select wire:model="language">
                            @foreach($this->getLanguageOptions() as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="language" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Subjek (untuk E-mel)</flux:label>
                    <flux:input wire:model="subject" placeholder="Peringatan: Kelas anda esok pada @{{session_time}}" />
                    <flux:error name="subject" />
                </flux:field>

                <flux:field>
                    <flux:label>Kandungan *</flux:label>
                    <flux:textarea wire:model="content" rows="10" placeholder="Assalamualaikum @{{student_name}}..." />
                    <flux:description>
                        Gunakan placeholder seperti @{{student_name}}, @{{class_name}}, @{{session_date}}, dll.
                    </flux:description>
                    <flux:error name="content" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model="is_active" label="Aktifkan templat ini" />
                </flux:field>

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button variant="ghost" type="button" wire:click="$set('showEditModal', false)">Batal</flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ $editingTemplate ? 'Kemaskini' : 'Cipta' }} Templat
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Preview Modal -->
    <flux:modal wire:model="showPreviewModal" class="{{ $previewIsVisual ? 'max-w-4xl' : 'max-w-2xl' }}">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Pratonton Templat</flux:heading>
                @if($previewIsVisual)
                    <flux:badge color="purple" size="sm">Visual Template</flux:badge>
                @endif
            </div>

            @if($previewSubject)
                <div class="mb-4">
                    <flux:label class="mb-1">Subjek:</flux:label>
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                        {{ $previewSubject }}
                    </div>
                </div>
            @endif

            <div class="mb-4">
                <flux:label class="mb-1">Kandungan:</flux:label>
                @if($previewIsVisual)
                    <div class="bg-gray-100 rounded-lg border border-gray-200 overflow-hidden">
                        <iframe
                            srcdoc="{{ $previewContent }}"
                            class="w-full border-0"
                            style="height: 500px;"
                            sandbox="allow-same-origin"
                        ></iframe>
                    </div>
                @else
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 prose prose-sm max-w-none">
                        {!! nl2br(e($previewContent)) !!}
                    </div>
                @endif
            </div>

            <div class="flex justify-end">
                <flux:button variant="ghost" wire:click="$set('showPreviewModal', false)">Tutup</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
