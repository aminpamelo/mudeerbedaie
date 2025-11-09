<?php

use App\Models\LiveSession;
use App\Models\LiveSessionAttachment;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public LiveSession $session;

    public $attachments = [];

    public $description = '';

    public bool $showStartModal = false;

    public bool $showEndModal = false;

    public bool $showCancelModal = false;

    public bool $showPreviewModal = false;

    public ?LiveSessionAttachment $previewAttachment = null;

    public function mount(LiveSession $session)
    {
        $this->session = $session->load([
            'platformAccount.platform',
            'platformAccount.user',
            'liveSchedule',
            'analytics',
            'attachments.uploader',
        ]);
    }

    public function openStartModal()
    {
        $this->showStartModal = true;
    }

    public function closeStartModal()
    {
        $this->showStartModal = false;
    }

    public function confirmStartLive()
    {
        if ($this->session->isScheduled()) {
            $this->session->startLive();
            $this->session->refresh();
            session()->flash('success', 'Live session started successfully.');
        }

        $this->showStartModal = false;
    }

    public function openEndModal()
    {
        $this->showEndModal = true;
    }

    public function closeEndModal()
    {
        $this->showEndModal = false;
    }

    public function confirmEndLive()
    {
        if ($this->session->isLive()) {
            $this->session->endLive();
            $this->session->refresh();
            session()->flash('success', 'Live session ended successfully.');
        }

        $this->showEndModal = false;
    }

    public function openCancelModal()
    {
        $this->showCancelModal = true;
    }

    public function closeCancelModal()
    {
        $this->showCancelModal = false;
    }

    public function confirmCancelSession()
    {
        if ($this->session->isScheduled()) {
            $this->session->cancel();
            $this->session->refresh();
            session()->flash('success', 'Live session cancelled.');
        }

        $this->showCancelModal = false;
    }

    public function uploadAttachments()
    {
        $this->validate([
            'attachments.*' => 'required|file|max:10240', // 10MB max
            'description' => 'nullable|string|max:500',
        ]);

        foreach ($this->attachments as $file) {
            $path = $file->store('live-sessions/'.$this->session->id, 'public');

            LiveSessionAttachment::create([
                'live_session_id' => $this->session->id,
                'uploaded_by' => auth()->id(),
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'description' => $this->description,
            ]);
        }

        $this->attachments = [];
        $this->description = '';
        $this->session->refresh();

        session()->flash('success', 'Attachments uploaded successfully.');
    }

    public function openPreview($attachmentId)
    {
        $this->previewAttachment = LiveSessionAttachment::findOrFail($attachmentId);
        $this->showPreviewModal = true;
    }

    public function closePreview()
    {
        $this->showPreviewModal = false;
        $this->previewAttachment = null;
    }

    public function deleteAttachment($attachmentId)
    {
        $attachment = LiveSessionAttachment::findOrFail($attachmentId);

        // Delete file from storage
        \Storage::disk('public')->delete($attachment->file_path);

        // Delete record
        $attachment->delete();

        $this->session->refresh();

        session()->flash('success', 'Attachment deleted successfully.');
    }
}; ?>

<div>
    <x-slot:title>{{ $session->title }}</x-slot:title>

    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $session->title }}</flux:heading>
            @if($session->description)
                <flux:text class="mt-2">{{ $session->description }}</flux:text>
            @endif
        </div>
        <flux:button variant="outline" href="{{ route('admin.live-sessions.index') }}" icon="arrow-left">
            Back to Sessions
        </flux:button>
    </div>

    <!-- Success message -->
    @if (session('success'))
        <div class="mb-6">
            <flux:callout variant="success">
                {{ session('success') }}
            </flux:callout>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main content - Left side (2 columns) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Session Details Card -->
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Session Details</flux:heading>
                    <flux:badge :variant="$session->statusColor" size="lg">
                        {{ ucfirst($session->status) }}
                    </flux:badge>
                </div>

                <div class="space-y-4">
                    <!-- Live Host Info -->
                    <div>
                        <flux:text class="font-medium text-gray-700 dark:text-gray-300">Live Host</flux:text>
                        <div class="mt-1 flex items-center gap-3">
                            <flux:avatar
                                :src="$session->platformAccount->user->profile_photo_url"
                                size="sm"
                            />
                            <div>
                                <flux:text class="font-medium">{{ $session->platformAccount->user->name }}</flux:text>
                                <flux:text class="text-sm text-gray-500">{{ $session->platformAccount->platform->name }} - {{ $session->platformAccount->name }}</flux:text>
                            </div>
                        </div>
                    </div>

                    <flux:separator />

                    <!-- Schedule Info -->
                    @if($session->liveSchedule)
                        <div>
                            <flux:text class="font-medium text-gray-700 dark:text-gray-300">Recurring Schedule</flux:text>
                            <flux:text class="mt-1">
                                {{ $session->liveSchedule->day_name }} - {{ $session->liveSchedule->time_range }}
                            </flux:text>
                        </div>
                        <flux:separator />
                    @endif

                    <!-- Scheduled Start Time -->
                    <div>
                        <flux:text class="font-medium text-gray-700 dark:text-gray-300">Scheduled Start</flux:text>
                        <flux:text class="mt-1">{{ $session->scheduled_start_at->format('F j, Y g:i A') }}</flux:text>
                        <flux:text class="text-sm text-gray-500">{{ $session->scheduled_start_at->diffForHumans() }}</flux:text>
                    </div>

                    <!-- Actual Times (if available) -->
                    @if($session->actual_start_at)
                        <flux:separator />
                        <div>
                            <flux:text class="font-medium text-gray-700 dark:text-gray-300">Actual Start Time</flux:text>
                            <flux:text class="mt-1">{{ $session->actual_start_at->format('F j, Y g:i A') }}</flux:text>
                        </div>
                    @endif

                    @if($session->actual_end_at)
                        <flux:separator />
                        <div>
                            <flux:text class="font-medium text-gray-700 dark:text-gray-300">Actual End Time</flux:text>
                            <flux:text class="mt-1">{{ $session->actual_end_at->format('F j, Y g:i A') }}</flux:text>
                        </div>

                        @if($session->duration)
                            <div class="mt-2">
                                <flux:text class="font-medium text-gray-700 dark:text-gray-300">Duration</flux:text>
                                <flux:text class="mt-1">{{ $session->duration }} minutes</flux:text>
                            </div>
                        @endif
                    @endif

                    <!-- Timestamps -->
                    <flux:separator />
                    <div class="grid grid-cols-2 gap-4 text-sm text-gray-500">
                        <div>
                            <flux:text class="font-medium">Created</flux:text>
                            <flux:text>{{ $session->created_at->format('M j, Y') }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="font-medium">Updated</flux:text>
                            <flux:text>{{ $session->updated_at->format('M j, Y') }}</flux:text>
                        </div>
                    </div>
                </div>
            </flux:card>

            <!-- Analytics Card (if available) -->
            @if($session->analytics)
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Session Analytics</flux:heading>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Viewers -->
                        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <flux:text class="text-sm text-gray-500">Peak Viewers</flux:text>
                            <flux:text class="text-2xl font-bold mt-1">{{ number_format($session->analytics->viewers_peak) }}</flux:text>
                        </div>

                        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <flux:text class="text-sm text-gray-500">Avg Viewers</flux:text>
                            <flux:text class="text-2xl font-bold mt-1">{{ number_format($session->analytics->viewers_avg) }}</flux:text>
                        </div>

                        <!-- Engagement -->
                        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <flux:text class="text-sm text-gray-500">Total Likes</flux:text>
                            <flux:text class="text-2xl font-bold mt-1">{{ number_format($session->analytics->total_likes) }}</flux:text>
                        </div>

                        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <flux:text class="text-sm text-gray-500">Total Comments</flux:text>
                            <flux:text class="text-2xl font-bold mt-1">{{ number_format($session->analytics->total_comments) }}</flux:text>
                        </div>

                        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <flux:text class="text-sm text-gray-500">Total Shares</flux:text>
                            <flux:text class="text-2xl font-bold mt-1">{{ number_format($session->analytics->total_shares) }}</flux:text>
                        </div>

                        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <flux:text class="text-sm text-gray-500">Engagement Rate</flux:text>
                            <flux:text class="text-2xl font-bold mt-1">{{ number_format($session->analytics->engagement_rate, 2) }}%</flux:text>
                        </div>

                        <!-- Gifts -->
                        @if($session->analytics->gifts_value > 0)
                            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg col-span-2">
                                <flux:text class="text-sm text-gray-500">Gifts Value</flux:text>
                                <flux:text class="text-2xl font-bold mt-1">${{ number_format($session->analytics->gifts_value, 2) }}</flux:text>
                            </div>
                        @endif
                    </div>
                </flux:card>
            @endif

            <!-- Attachments Card -->
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Session Proof & Attachments</flux:heading>
                    <flux:badge>{{ $session->attachments->count() }}</flux:badge>
                </div>

                <!-- Upload Section -->
                <div class="mb-6">
                    <form wire:submit="uploadAttachments">
                        <div class="space-y-4">
                            <div>
                                <flux:text class="font-medium mb-2">Upload Files</flux:text>
                                <input
                                    type="file"
                                    wire:model="attachments"
                                    multiple
                                    accept="image/*,video/*,.pdf,.doc,.docx"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                />
                                <flux:text class="text-xs text-gray-500 mt-1">
                                    Max size: 10MB per file. Accepted: Images, Videos, PDFs, Documents
                                </flux:text>
                                @error('attachments.*')
                                    <flux:text class="text-xs text-red-600 mt-1">{{ $message }}</flux:text>
                                @enderror
                            </div>

                            <div>
                                <flux:text class="font-medium mb-2">Description (Optional)</flux:text>
                                <flux:textarea
                                    wire:model="description"
                                    placeholder="Add a note about these files..."
                                    rows="2"
                                />
                                @error('description')
                                    <flux:text class="text-xs text-red-600 mt-1">{{ $message }}</flux:text>
                                @enderror
                            </div>

                            <div wire:loading wire:target="attachments" class="text-sm text-gray-500">
                                <flux:text>Uploading files...</flux:text>
                            </div>

                            <flux:button
                                type="submit"
                                variant="primary"
                                wire:loading.attr="disabled"
                                wire:target="attachments"
                                :disabled="empty($attachments)"
                            >
                                Upload Attachments
                            </flux:button>
                        </div>
                    </form>
                </div>

                <flux:separator class="my-6" />

                <!-- Existing Attachments -->
                <div>
                    <flux:text class="font-medium mb-4">Uploaded Files ({{ $session->attachments->count() }})</flux:text>

                    @if($session->attachments->count() > 0)
                        <div class="space-y-4">
                            @foreach($session->attachments as $attachment)
                                <div class="flex items-start gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <!-- File Icon/Preview -->
                                    <div class="flex-shrink-0">
                                        @if($attachment->isImage())
                                            <img
                                                src="{{ $attachment->file_url }}"
                                                alt="{{ $attachment->file_name }}"
                                                class="w-16 h-16 object-cover rounded"
                                            />
                                        @elseif($attachment->isVideo())
                                            <div class="w-16 h-16 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                                                <flux:icon name="video-camera" class="w-8 h-8 text-gray-400" />
                                            </div>
                                        @elseif($attachment->isPdf())
                                            <div class="w-16 h-16 bg-red-100 dark:bg-red-900 rounded flex items-center justify-center">
                                                <flux:icon name="document" class="w-8 h-8 text-red-600" />
                                            </div>
                                        @else
                                            <div class="w-16 h-16 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center">
                                                <flux:icon name="document" class="w-8 h-8 text-gray-400" />
                                            </div>
                                        @endif
                                    </div>

                                    <!-- File Info -->
                                    <div class="flex-1 min-w-0">
                                        <flux:text class="font-medium truncate">{{ $attachment->file_name }}</flux:text>
                                        <div class="flex items-center gap-2 mt-1">
                                            <flux:text class="text-xs text-gray-500">{{ $attachment->file_size_formatted }}</flux:text>
                                            <span class="text-gray-300">•</span>
                                            <flux:text class="text-xs text-gray-500">{{ $attachment->created_at->format('M j, Y g:i A') }}</flux:text>
                                        </div>
                                        @if($attachment->description)
                                            <flux:text class="text-sm text-gray-600 mt-2">{{ $attachment->description }}</flux:text>
                                        @endif
                                        <flux:text class="text-xs text-gray-500 mt-1">
                                            Uploaded by {{ $attachment->uploader->name }}
                                        </flux:text>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex-shrink-0 flex items-center gap-2">
                                        <button
                                            wire:click="openPreview({{ $attachment->id }})"
                                            class="p-2 text-green-600 hover:bg-green-50 dark:hover:bg-green-900 rounded"
                                            title="Preview"
                                        >
                                            <flux:icon name="eye" class="w-5 h-5" />
                                        </button>
                                        <a
                                            href="{{ $attachment->file_url }}"
                                            target="_blank"
                                            class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900 rounded"
                                            title="Download"
                                        >
                                            <flux:icon name="arrow-down-tray" class="w-5 h-5" />
                                        </a>
                                        <button
                                            wire:click="deleteAttachment({{ $attachment->id }})"
                                            wire:confirm="Are you sure you want to delete this attachment?"
                                            class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900 rounded"
                                            title="Delete"
                                        >
                                            <flux:icon name="trash" class="w-5 h-5" />
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <flux:icon name="photo" class="w-12 h-12 text-gray-400 mx-auto mb-3" />
                            <flux:text class="text-gray-500">No attachments uploaded yet.</flux:text>
                            <flux:text class="text-sm text-gray-400 mt-1">Upload screenshots, videos, or documents as proof of the live session.</flux:text>
                        </div>
                    @endif
                </div>
            </flux:card>
        </div>

        <!-- Actions sidebar - Right side (1 column) -->
        <div>
            <flux:card>
                <flux:heading size="lg" class="mb-4">Actions</flux:heading>

                <div class="space-y-3">
                    @if($session->isScheduled())
                        <flux:button
                            variant="primary"
                            wire:click="openStartModal"
                            icon="play"
                            class="w-full"
                        >
                            Start Live Session
                        </flux:button>

                        <flux:button
                            variant="danger"
                            wire:click="openCancelModal"
                            icon="x-mark"
                            class="w-full"
                        >
                            Cancel Session
                        </flux:button>
                    @endif

                    @if($session->isLive())
                        <flux:button
                            variant="danger"
                            wire:click="openEndModal"
                            icon="stop"
                            class="w-full"
                        >
                            End Live Session
                        </flux:button>
                    @endif

                    @if($session->isEnded() || $session->isCancelled())
                        <flux:callout variant="info">
                            This session has {{ $session->status }}. No actions available.
                        </flux:callout>
                    @endif
                </div>
            </flux:card>

            <!-- Quick Info Card -->
            <flux:card class="mt-6">
                <flux:heading size="lg" class="mb-4">Quick Info</flux:heading>

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:text class="text-sm text-gray-500">Status</flux:text>
                        <flux:badge :variant="$session->statusColor">
                            {{ ucfirst($session->status) }}
                        </flux:badge>
                    </div>

                    <div class="flex items-center justify-between">
                        <flux:text class="text-sm text-gray-500">Platform</flux:text>
                        <flux:text class="font-medium">{{ $session->platformAccount->platform->name }}</flux:text>
                    </div>

                    @if($session->analytics)
                        <flux:separator />
                        <div class="flex items-center justify-between">
                            <flux:text class="text-sm text-gray-500">Total Engagement</flux:text>
                            <flux:text class="font-medium">{{ number_format($session->analytics->total_engagement) }}</flux:text>
                        </div>
                    @endif
                </div>
            </flux:card>
        </div>
    </div>

    <!-- Start Session Confirmation Modal -->
    <flux:modal name="start-session" wire:model="showStartModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Start Live Session</flux:heading>
                <flux:text class="mt-2">
                    Are you sure you want to start this live session?
                </flux:text>
            </div>

            <div class="p-4 bg-blue-50 dark:bg-blue-900 rounded-lg">
                <flux:text class="font-medium">{{ $session->title }}</flux:text>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Platform: {{ $session->platformAccount->platform->name }} - {{ $session->platformAccount->name }}
                </flux:text>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400">
                    Scheduled: {{ $session->scheduled_start_at->format('F j, Y g:i A') }}
                </flux:text>
            </div>

            <flux:text class="text-sm text-gray-500">
                The session status will be changed to "Live" and the actual start time will be recorded.
            </flux:text>

            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" wire:click="closeStartModal">
                    Cancel
                </flux:button>
                <flux:button variant="primary" wire:click="confirmStartLive" icon="play">
                    Start Session
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- End Session Confirmation Modal -->
    <flux:modal name="end-session" wire:model="showEndModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">End Live Session</flux:heading>
                <flux:text class="mt-2">
                    Are you sure you want to end this live session?
                </flux:text>
            </div>

            <div class="p-4 bg-orange-50 dark:bg-orange-900 rounded-lg">
                <flux:text class="font-medium">{{ $session->title }}</flux:text>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Platform: {{ $session->platformAccount->platform->name }} - {{ $session->platformAccount->name }}
                </flux:text>
                @if($session->actual_start_at)
                    <flux:text class="text-sm text-gray-600 dark:text-gray-400">
                        Started: {{ $session->actual_start_at->format('F j, Y g:i A') }}
                    </flux:text>
                @endif
            </div>

            <flux:text class="text-sm text-gray-500">
                The session status will be changed to "Ended" and the actual end time will be recorded. This action cannot be undone.
            </flux:text>

            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" wire:click="closeEndModal">
                    Cancel
                </flux:button>
                <flux:button variant="danger" wire:click="confirmEndLive" icon="stop">
                    End Session
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Cancel Session Confirmation Modal -->
    <flux:modal name="cancel-session" wire:model="showCancelModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Cancel Live Session</flux:heading>
                <flux:text class="mt-2">
                    Are you sure you want to cancel this scheduled live session?
                </flux:text>
            </div>

            <div class="p-4 bg-red-50 dark:bg-red-900 rounded-lg">
                <flux:text class="font-medium">{{ $session->title }}</flux:text>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Platform: {{ $session->platformAccount->platform->name }} - {{ $session->platformAccount->name }}
                </flux:text>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400">
                    Scheduled: {{ $session->scheduled_start_at->format('F j, Y g:i A') }}
                </flux:text>
            </div>

            <flux:text class="text-sm text-red-600 dark:text-red-400 font-medium">
                ⚠️ This will permanently cancel the session. This action cannot be undone.
            </flux:text>

            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" wire:click="closeCancelModal">
                    Keep Session
                </flux:button>
                <flux:button variant="danger" wire:click="confirmCancelSession" icon="x-mark">
                    Cancel Session
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Attachment Preview Modal -->
    @if($previewAttachment)
        <flux:modal name="preview-attachment" wire:model="showPreviewModal" class="md:max-w-4xl">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $previewAttachment->file_name }}</flux:heading>
                    <div class="flex items-center gap-2 mt-2">
                        <flux:text class="text-sm text-gray-500">{{ $previewAttachment->file_size_formatted }}</flux:text>
                        <span class="text-gray-300">•</span>
                        <flux:text class="text-sm text-gray-500">{{ $previewAttachment->created_at->format('M j, Y g:i A') }}</flux:text>
                    </div>
                </div>

                <!-- Preview Content -->
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 max-h-[70vh] overflow-auto">
                    @if($previewAttachment->isImage())
                        <!-- Image Preview -->
                        <img
                            src="{{ $previewAttachment->file_url }}"
                            alt="{{ $previewAttachment->file_name }}"
                            class="max-w-full h-auto mx-auto rounded"
                        />
                    @elseif($previewAttachment->isVideo())
                        <!-- Video Preview -->
                        <video
                            controls
                            class="max-w-full h-auto mx-auto rounded"
                        >
                            <source src="{{ $previewAttachment->file_url }}" type="{{ $previewAttachment->file_type }}">
                            Your browser does not support the video tag.
                        </video>
                    @elseif($previewAttachment->isPdf())
                        <!-- PDF Preview -->
                        <iframe
                            src="{{ $previewAttachment->file_url }}"
                            class="w-full h-[60vh] rounded"
                            frameborder="0"
                        ></iframe>
                    @else
                        <!-- Other File Types -->
                        <div class="text-center py-12">
                            <flux:icon name="document" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
                            <flux:text class="text-gray-500 mb-2">Preview not available for this file type</flux:text>
                            <flux:text class="text-sm text-gray-400">{{ $previewAttachment->file_type }}</flux:text>
                            <div class="mt-6">
                                <a
                                    href="{{ $previewAttachment->file_url }}"
                                    target="_blank"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                                >
                                    <flux:icon name="arrow-down-tray" class="w-4 h-4" />
                                    Download File
                                </a>
                            </div>
                        </div>
                    @endif
                </div>

                @if($previewAttachment->description)
                    <div class="p-4 bg-blue-50 dark:bg-blue-900 rounded-lg">
                        <flux:text class="font-medium text-sm">Description</flux:text>
                        <flux:text class="text-sm mt-1">{{ $previewAttachment->description }}</flux:text>
                    </div>
                @endif

                <div class="flex items-center justify-between">
                    <flux:text class="text-sm text-gray-500">
                        Uploaded by {{ $previewAttachment->uploader->name }}
                    </flux:text>
                    <div class="flex gap-2">
                        <a
                            href="{{ $previewAttachment->file_url }}"
                            target="_blank"
                            class="inline-flex items-center gap-2 px-3 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700"
                        >
                            <flux:icon name="arrow-down-tray" class="w-4 h-4" />
                            Download
                        </a>
                        <flux:button variant="ghost" wire:click="closePreview">
                            Close
                        </flux:button>
                    </div>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
