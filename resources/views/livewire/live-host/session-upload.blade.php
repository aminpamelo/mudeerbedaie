<?php

use App\Models\LiveSession;
use App\Models\LiveSchedule;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads, WithPagination;

    public string $activeTab = 'pending';

    // Upload form properties
    public bool $showUploadModal = false;
    public ?int $editingSessionId = null;
    public string $actualStartTime = '';
    public string $actualEndTime = '';
    public $sessionImage = null;
    public string $videoLink = '';
    public string $remarks = '';

    // Filters
    public string $filterDate = '';

    public function getPendingSessionsProperty()
    {
        return LiveSession::query()
            ->where('live_host_id', auth()->id())
            ->where('status', 'ended')
            ->whereNull('uploaded_at')
            ->with(['platformAccount.platform', 'liveSchedule'])
            ->orderByDesc('scheduled_start_at')
            ->paginate(10, ['*'], 'pendingPage');
    }

    public function getUploadedSessionsProperty()
    {
        return LiveSession::query()
            ->where('live_host_id', auth()->id())
            ->whereNotNull('uploaded_at')
            ->with(['platformAccount.platform', 'liveSchedule'])
            ->when($this->filterDate, fn($q) => $q->whereDate('scheduled_start_at', $this->filterDate))
            ->orderByDesc('uploaded_at')
            ->paginate(10, ['*'], 'uploadedPage');
    }

    public function getStatsProperty(): array
    {
        $hostId = auth()->id();

        return [
            'pending' => LiveSession::where('live_host_id', $hostId)
                ->where('status', 'ended')
                ->whereNull('uploaded_at')
                ->count(),
            'uploaded' => LiveSession::where('live_host_id', $hostId)
                ->whereNotNull('uploaded_at')
                ->count(),
            'thisWeek' => LiveSession::where('live_host_id', $hostId)
                ->whereNotNull('uploaded_at')
                ->whereBetween('uploaded_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'totalMinutes' => LiveSession::where('live_host_id', $hostId)
                ->whereNotNull('uploaded_at')
                ->sum('duration_minutes') ?? 0,
        ];
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function openUploadModal(int $sessionId): void
    {
        $session = LiveSession::find($sessionId);

        if (!$session || $session->live_host_id !== auth()->id()) {
            session()->flash('error', 'Session not found or you do not have permission.');
            return;
        }

        if ($session->isUploaded()) {
            session()->flash('error', 'This session has already been uploaded.');
            return;
        }

        $this->editingSessionId = $session->id;
        $this->actualStartTime = $session->actual_start_at?->format('H:i') ?? $session->scheduled_start_at->format('H:i');
        $this->actualEndTime = $session->actual_end_at?->format('H:i') ?? '';
        $this->videoLink = $session->video_link ?? '';
        $this->remarks = $session->remarks ?? '';
        $this->sessionImage = null;

        $this->showUploadModal = true;
    }

    public function uploadSession(): void
    {
        $this->validate([
            'actualStartTime' => 'required',
            'actualEndTime' => 'required|after:actualStartTime',
            'sessionImage' => 'required|image|max:5120', // 5MB max
            'videoLink' => 'required|url|max:500',
            'remarks' => 'nullable|string|max:1000',
        ], [
            'actualEndTime.after' => 'End time must be after start time.',
            'sessionImage.required' => 'Please upload a screenshot of your live session.',
            'sessionImage.max' => 'Image must be less than 5MB.',
            'videoLink.required' => 'Please enter the video link.',
            'videoLink.url' => 'Please enter a valid URL for the video link.',
        ]);

        $session = LiveSession::find($this->editingSessionId);

        if (!$session || $session->live_host_id !== auth()->id()) {
            session()->flash('error', 'Session not found.');
            return;
        }

        // Store the image
        $imagePath = $this->sessionImage->store('live-sessions', 'public');

        // Get the session date for datetime construction
        $sessionDate = $session->scheduled_start_at->format('Y-m-d');

        $session->uploadDetails([
            'actual_start_at' => $sessionDate . ' ' . $this->actualStartTime,
            'actual_end_at' => $sessionDate . ' ' . $this->actualEndTime,
            'image_path' => $imagePath,
            'video_link' => $this->videoLink ?: null,
            'remarks' => $this->remarks,
        ]);

        $this->closeModal();
        session()->flash('success', 'Session uploaded successfully!');
    }

    public function closeModal(): void
    {
        $this->showUploadModal = false;
        $this->editingSessionId = null;
        $this->actualStartTime = '';
        $this->actualEndTime = '';
        $this->sessionImage = null;
        $this->videoLink = '';
        $this->remarks = '';
        $this->resetValidation();
    }

    public function viewSession(int $sessionId): void
    {
        $this->redirect(route('live-host.sessions.show', $sessionId), navigate: true);
    }
}
?>

<div class="pb-20 lg:pb-6">
    <!-- Header -->
    <div class="mb-6">
        <flux:heading size="xl">Session Slots</flux:heading>
        <flux:text class="mt-2">Upload your live streaming session details</flux:text>
    </div>

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
            <div class="flex items-center gap-2 text-green-800 dark:text-green-300">
                <flux:icon.check-circle class="w-5 h-5" />
                <span class="text-sm font-medium">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
            <div class="flex items-center gap-2 text-red-800 dark:text-red-300">
                <flux:icon.exclamation-circle class="w-5 h-5" />
                <span class="text-sm font-medium">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-yellow-100 dark:bg-yellow-800/50 rounded-lg">
                    <flux:icon.clock class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-yellow-900 dark:text-yellow-100">{{ $this->stats['pending'] }}</div>
                    <div class="text-xs text-yellow-600 dark:text-yellow-400 font-medium">Pending Upload</div>
                </div>
            </div>
        </div>
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-green-100 dark:bg-green-800/50 rounded-lg">
                    <flux:icon.check-circle class="w-5 h-5 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-green-900 dark:text-green-100">{{ $this->stats['uploaded'] }}</div>
                    <div class="text-xs text-green-600 dark:text-green-400 font-medium">Uploaded</div>
                </div>
            </div>
        </div>
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-100 dark:bg-blue-800/50 rounded-lg">
                    <flux:icon.calendar class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ $this->stats['thisWeek'] }}</div>
                    <div class="text-xs text-blue-600 dark:text-blue-400 font-medium">This Week</div>
                </div>
            </div>
        </div>
        <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-xl p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-100 dark:bg-purple-800/50 rounded-lg">
                    <flux:icon.play class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-purple-900 dark:text-purple-100">{{ number_format($this->stats['totalMinutes'] / 60, 1) }}h</div>
                    <div class="text-xs text-purple-600 dark:text-purple-400 font-medium">Total Hours</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
        <nav class="flex gap-6" aria-label="Tabs">
            <button
                wire:click="setActiveTab('pending')"
                class="py-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'pending' ? 'border-yellow-500 text-yellow-600 dark:text-yellow-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600' }}"
            >
                Pending Upload
                @if($this->stats['pending'] > 0)
                    <span class="ml-2 px-2.5 py-0.5 text-xs rounded-full {{ $activeTab === 'pending' ? 'bg-yellow-100 dark:bg-yellow-800/50 text-yellow-600 dark:text-yellow-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                        {{ $this->stats['pending'] }}
                    </span>
                @endif
            </button>
            <button
                wire:click="setActiveTab('uploaded')"
                class="py-3 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'uploaded' ? 'border-green-500 text-green-600 dark:text-green-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600' }}"
            >
                Uploaded
                @if($this->stats['uploaded'] > 0)
                    <span class="ml-2 px-2.5 py-0.5 text-xs rounded-full {{ $activeTab === 'uploaded' ? 'bg-green-100 dark:bg-green-800/50 text-green-600 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                        {{ $this->stats['uploaded'] }}
                    </span>
                @endif
            </button>
        </nav>
    </div>

    <!-- Pending Upload Tab -->
    @if($activeTab === 'pending')
        @if($this->stats['pending'] > 0)
            <div class="mb-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                <div class="flex items-center gap-2 text-yellow-800 dark:text-yellow-300">
                    <flux:icon.exclamation-triangle class="w-5 h-5" />
                    <span class="text-sm">You have <strong>{{ $this->stats['pending'] }}</strong> session(s) waiting for upload. Please upload the session details.</span>
                </div>
            </div>
        @endif

        <div class="space-y-4">
            @forelse($this->pendingSessions as $session)
                <div class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl shadow-sm hover:shadow-md dark:hover:shadow-zinc-900/50 transition-all">
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-800/50 text-yellow-700 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-700">
                                        <flux:icon.clock class="w-3 h-3 mr-1" />
                                        Pending Upload
                                    </span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $session->scheduled_start_at->format('D, M d, Y') }}</span>
                                </div>
                                <h3 class="font-semibold text-gray-900 dark:text-white text-lg truncate">{{ $session->title }}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    {{ $session->platformAccount?->name }} - {{ $session->platformAccount?->platform?->name }}
                                </p>
                                <div class="flex items-center gap-2 mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    <flux:icon.clock class="w-4 h-4" />
                                    <span>Scheduled: {{ $session->scheduled_start_at->format('g:i A') }}</span>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <flux:button variant="primary" wire:click="openUploadModal({{ $session->id }})" size="sm">
                                    <div class="flex items-center justify-center">
                                        <flux:icon.arrow-up-tray class="w-4 h-4 mr-1.5" />
                                        <span>Upload</span>
                                    </div>
                                </flux:button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-16 bg-gray-50 dark:bg-zinc-800/50 rounded-xl border border-gray-200 dark:border-zinc-700">
                    <div class="p-4 bg-green-100 dark:bg-green-800/30 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                        <flux:icon.check-circle class="w-8 h-8 text-green-600 dark:text-green-400" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">All Caught Up!</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No sessions pending upload.</p>
                </div>
            @endforelse

            @if($this->pendingSessions->hasPages())
                <div class="mt-6">
                    {{ $this->pendingSessions->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- Uploaded Tab -->
    @if($activeTab === 'uploaded')
        <!-- Filter -->
        <div class="mb-6">
            <flux:input type="date" wire:model.live="filterDate" placeholder="Filter by date" class="w-48" />
        </div>

        <div class="space-y-4">
            @forelse($this->uploadedSessions as $session)
                <div class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden">
                    <div class="p-5">
                        <div class="flex items-start gap-4">
                            <!-- Thumbnail -->
                            @if($session->image_path)
                                <div class="flex-shrink-0">
                                    <img
                                        src="{{ Storage::url($session->image_path) }}"
                                        alt="Session screenshot"
                                        class="w-24 h-24 object-cover rounded-lg border border-gray-200 dark:border-zinc-600"
                                    />
                                </div>
                            @else
                                <div class="flex-shrink-0 w-24 h-24 bg-gray-100 dark:bg-zinc-700 rounded-lg flex items-center justify-center">
                                    <flux:icon.photo class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                                </div>
                            @endif

                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-800/50 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-700">
                                        <flux:icon.check class="w-3 h-3 mr-1" />
                                        Uploaded
                                    </span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $session->scheduled_start_at->format('D, M d, Y') }}</span>
                                </div>
                                <h3 class="font-semibold text-gray-900 dark:text-white text-lg truncate">{{ $session->title }}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    {{ $session->platformAccount?->name }}
                                </p>
                                <div class="flex flex-wrap items-center gap-4 mt-3 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="flex items-center gap-1">
                                        <flux:icon.clock class="w-4 h-4" />
                                        {{ $session->actual_start_at?->format('g:i A') }} - {{ $session->actual_end_at?->format('g:i A') }}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <flux:icon.play class="w-4 h-4" />
                                        {{ $session->duration_minutes }} min
                                    </span>
                                    @if($session->video_link)
                                        <a href="{{ $session->video_link }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-1 text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors">
                                            <flux:icon.link class="w-4 h-4" />
                                            <span>View Video</span>
                                        </a>
                                    @endif
                                </div>
                                @if($session->remarks)
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-3 italic bg-gray-50 dark:bg-zinc-700/50 p-2 rounded-lg">"{{ Str::limit($session->remarks, 100) }}"</p>
                                @endif
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-3">
                                    Uploaded {{ $session->uploaded_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-16 bg-gray-50 dark:bg-zinc-800/50 rounded-xl border border-gray-200 dark:border-zinc-700">
                    <div class="p-4 bg-gray-100 dark:bg-zinc-700 rounded-full w-16 h-16 mx-auto mb-4 flex items-center justify-center">
                        <flux:icon.document class="w-8 h-8 text-gray-400 dark:text-zinc-500" />
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No Uploaded Sessions</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Your uploaded sessions will appear here.</p>
                </div>
            @endforelse

            @if($this->uploadedSessions->hasPages())
                <div class="mt-6">
                    {{ $this->uploadedSessions->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- Upload Modal -->
    <flux:modal wire:model="showUploadModal" class="max-w-lg">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="p-2 bg-blue-100 dark:bg-blue-800/50 rounded-lg">
                    <flux:icon.arrow-up-tray class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <flux:heading size="lg">Upload Session Details</flux:heading>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Fill in the actual session information</p>
                </div>
            </div>

            @if($editingSessionId)
                @php
                    $session = \App\Models\LiveSession::find($editingSessionId);
                @endphp

                @if($session)
                    <div class="mb-6 p-4 bg-gray-50 dark:bg-zinc-700/50 rounded-xl border border-gray-200 dark:border-zinc-600">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-white dark:bg-zinc-600 rounded-lg shadow-sm">
                                <flux:icon.video-camera class="w-5 h-5 text-gray-600 dark:text-gray-300" />
                            </div>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ $session->title }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $session->platformAccount?->name }} - {{ $session->scheduled_start_at->format('D, M d, Y') }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            @endif

            <div class="space-y-5">
                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Actual Start Time *</flux:label>
                        <flux:input type="time" wire:model="actualStartTime" />
                        @error('actualStartTime') <flux:error>{{ $message }}</flux:error> @enderror
                    </flux:field>

                    <flux:field>
                        <flux:label>Actual End Time *</flux:label>
                        <flux:input type="time" wire:model="actualEndTime" />
                        @error('actualEndTime') <flux:error>{{ $message }}</flux:error> @enderror
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Session Screenshot *</flux:label>
                    <div class="mt-2">
                        <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 dark:border-zinc-600 rounded-xl cursor-pointer bg-gray-50 dark:bg-zinc-700/50 hover:bg-gray-100 dark:hover:bg-zinc-700 transition-colors">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <flux:icon.cloud-arrow-up class="w-8 h-8 text-gray-400 dark:text-zinc-500 mb-2" />
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    <span class="font-medium text-blue-600 dark:text-blue-400">Click to upload</span> or drag and drop
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">PNG, JPG up to 5MB</p>
                            </div>
                            <input
                                type="file"
                                wire:model="sessionImage"
                                accept="image/*"
                                class="hidden"
                            />
                        </label>
                    </div>
                    @error('sessionImage') <flux:error>{{ $message }}</flux:error> @enderror

                    @if($sessionImage)
                        <div class="mt-3 relative">
                            <img src="{{ $sessionImage->temporaryUrl() }}" class="w-full max-h-48 object-contain rounded-lg border border-gray-200 dark:border-zinc-600" />
                            <button
                                type="button"
                                wire:click="$set('sessionImage', null)"
                                class="absolute top-2 right-2 p-1.5 bg-red-500 hover:bg-red-600 text-white rounded-full shadow-lg transition-colors"
                            >
                                <flux:icon.x-mark class="w-4 h-4" />
                            </button>
                        </div>
                    @endif

                    <div wire:loading wire:target="sessionImage" class="mt-2 flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400">
                        <flux:icon.arrow-path class="w-4 h-4 animate-spin" />
                        <span>Uploading...</span>
                    </div>
                </flux:field>

                <flux:field>
                    <flux:label>Video Link *</flux:label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <flux:icon.link class="w-4 h-4 text-gray-400 dark:text-zinc-500" />
                        </div>
                        <flux:input type="url" wire:model="videoLink" placeholder="https://..." class="pl-10" />
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Link to recorded video (TikTok, Facebook, YouTube, etc.)</p>
                    @error('videoLink') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Remarks (Optional)</flux:label>
                    <flux:textarea wire:model="remarks" rows="3" placeholder="Any notes about this session..." />
                    @error('remarks') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
            </div>

            <div class="mt-8 flex gap-3">
                <flux:button variant="primary" wire:click="uploadSession" class="flex-1">
                    <div class="flex items-center justify-center">
                        <flux:icon.arrow-up-tray class="w-4 h-4 mr-1.5" />
                        <span>Upload Session</span>
                    </div>
                </flux:button>
                <flux:button variant="ghost" wire:click="closeModal">
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Bottom Navigation -->
    <x-live-host-nav />
</div>
