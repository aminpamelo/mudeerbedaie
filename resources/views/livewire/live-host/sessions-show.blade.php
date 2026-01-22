<?php

use App\Models\LiveSession;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public LiveSession $session;

    public bool $showStartModal = false;
    public bool $showEndModal = false;
    public bool $showCancelModal = false;

    public function mount(LiveSession $session)
    {
        // Verify the session belongs to this live host
        if ($session->live_host_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this session.');
        }

        $this->session = $session->load([
            'platformAccount.platform',
            'liveSchedule',
            'analytics',
            'attachments.uploader'
        ]);
    }

    public function startLive()
    {
        if (!$this->session->isScheduled()) {
            session()->flash('error', 'Session can only be started when in scheduled status.');
            return;
        }

        $this->session->startLive();
        $this->session->refresh();
        $this->showStartModal = false;

        session()->flash('success', 'Live session started successfully!');
    }

    public function endLive()
    {
        if (!$this->session->isLive()) {
            session()->flash('error', 'Session can only be ended when live.');
            return;
        }

        $this->session->endLive();
        $this->session->refresh();
        $this->showEndModal = false;

        session()->flash('success', 'Live session ended successfully!');
    }

    public function cancelSession()
    {
        if ($this->session->isEnded() || $this->session->isCancelled()) {
            session()->flash('error', 'Session cannot be cancelled.');
            return;
        }

        $this->session->cancel();
        $this->session->refresh();
        $this->showCancelModal = false;

        session()->flash('success', 'Session cancelled successfully.');
    }

    public function getFormattedFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $session->title }}</flux:heading>
            <flux:text class="mt-2">Session details and analytics</flux:text>
        </div>
        <div class="flex gap-3">
            <flux:button variant="ghost" href="{{ route('live-host.sessions.index') }}" wire:navigate>
                <div class="flex items-center">
                    <flux:icon.chevron-left class="w-4 h-4 mr-1" />
                    Back to Sessions
                </div>
            </flux:button>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session('success'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
            <div class="flex items-center gap-2">
                <flux:icon.check-circle class="w-5 h-5 text-green-600" />
                <p class="text-sm text-green-700">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-center gap-2">
                <flux:icon.x-circle class="w-5 h-5 text-red-600" />
                <p class="text-sm text-red-700">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <!-- Session Status Control Card -->
    <flux:card class="mb-6">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">Current Status</p>
                        <flux:badge variant="filled" :color="$session->status_color" size="lg">
                            {{ ucfirst($session->status) }}
                        </flux:badge>
                    </div>

                    @if ($session->isLive() && $session->actual_start_at)
                        <div class="pl-4 border-l border-gray-200">
                            <p class="text-sm font-medium text-gray-500 mb-1">Live Duration</p>
                            <p class="text-lg font-bold text-green-600">
                                {{ $session->actual_start_at->diffForHumans(null, true) }}
                            </p>
                        </div>
                    @endif
                </div>

                <div class="flex gap-3">
                    @if ($session->isScheduled())
                        <flux:button variant="primary" wire:click="$set('showStartModal', true)">
                            <div class="flex items-center">
                                <flux:icon.play class="w-4 h-4 mr-2" />
                                Start Live
                            </div>
                        </flux:button>
                        <flux:button variant="ghost" wire:click="$set('showCancelModal', true)">
                            <div class="flex items-center">
                                <flux:icon.x-mark class="w-4 h-4 mr-2" />
                                Cancel
                            </div>
                        </flux:button>
                    @elseif ($session->isLive())
                        <flux:button variant="danger" wire:click="$set('showEndModal', true)">
                            <div class="flex items-center">
                                <flux:icon.stop class="w-4 h-4 mr-2" />
                                End Live
                            </div>
                        </flux:button>
                    @elseif ($session->isEnded())
                        <flux:button variant="primary" href="{{ route('live-host.session-slots') }}" wire:navigate>
                            <div class="flex items-center">
                                <flux:icon.arrow-up-tray class="w-4 h-4 mr-2" />
                                Upload Details
                            </div>
                        </flux:button>
                    @elseif ($session->isCancelled())
                        <div class="text-sm text-gray-500 italic">Session was cancelled</div>
                    @endif
                </div>
            </div>

            <!-- Status Progress Bar -->
            <div class="mt-6">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium {{ $session->isScheduled() || $session->isLive() || $session->isEnded() ? 'text-blue-600' : 'text-gray-400' }}">Scheduled</span>
                    <span class="text-xs font-medium {{ $session->isLive() || $session->isEnded() ? 'text-green-600' : 'text-gray-400' }}">Live</span>
                    <span class="text-xs font-medium {{ $session->isEnded() ? 'text-gray-600' : 'text-gray-400' }}">Ended</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    @if ($session->isCancelled())
                        <div class="bg-red-500 h-2 rounded-full" style="width: 100%"></div>
                    @elseif ($session->isEnded())
                        <div class="bg-gray-600 h-2 rounded-full" style="width: 100%"></div>
                    @elseif ($session->isLive())
                        <div class="bg-green-500 h-2 rounded-full animate-pulse" style="width: 66%"></div>
                    @else
                        <div class="bg-blue-500 h-2 rounded-full" style="width: 33%"></div>
                    @endif
                </div>
            </div>
        </div>
    </flux:card>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Session Information -->
        <div class="lg:col-span-2">
            <flux:card>
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">Session Information</flux:heading>

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Platform</p>
                                <p class="text-sm text-gray-900 mt-1">{{ $session->platformAccount->platform->name }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Account</p>
                                <p class="text-sm text-gray-900 mt-1">{{ $session->platformAccount->account_name }}</p>
                            </div>
                        </div>

                        @if ($session->description)
                            <div>
                                <p class="text-sm font-medium text-gray-500">Description</p>
                                <p class="text-sm text-gray-900 mt-1">{{ $session->description }}</p>
                            </div>
                        @endif

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Scheduled Start</p>
                                <p class="text-sm text-gray-900 mt-1">
                                    {{ $session->scheduled_start_at->format('M d, Y h:i A') }}
                                </p>
                                <p class="text-xs text-gray-500">{{ $session->scheduled_start_at->diffForHumans() }}</p>
                            </div>
                            @if ($session->actual_start_at)
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Actual Start</p>
                                    <p class="text-sm text-gray-900 mt-1">
                                        {{ $session->actual_start_at->format('M d, Y h:i A') }}
                                    </p>
                                </div>
                            @endif
                        </div>

                        @if ($session->actual_end_at)
                            <div>
                                <p class="text-sm font-medium text-gray-500">Ended At</p>
                                <p class="text-sm text-gray-900 mt-1">
                                    {{ $session->actual_end_at->format('M d, Y h:i A') }}
                                </p>
                                @if ($session->duration)
                                    <p class="text-xs text-gray-500">Duration: {{ $session->duration }} minutes</p>
                                @endif
                            </div>
                        @endif

                        @if ($session->liveSchedule)
                            <div>
                                <p class="text-sm font-medium text-gray-500">Linked Schedule</p>
                                <p class="text-sm text-gray-900 mt-1">
                                    {{ $session->liveSchedule->day_name }} at {{ $session->liveSchedule->time_range }}
                                </p>
                                <div class="flex gap-2 mt-1">
                                    @if ($session->liveSchedule->is_recurring)
                                        <flux:badge variant="outline" color="purple" size="sm">
                                            Recurring
                                        </flux:badge>
                                    @endif
                                    @if ($session->isAdminAssigned())
                                        <flux:badge variant="outline" color="blue" size="sm">Admin Assigned</flux:badge>
                                    @else
                                        <flux:badge variant="outline" color="purple" size="sm">Self Scheduled</flux:badge>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Session Timeline -->
        <div>
            <flux:card>
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">Timeline</flux:heading>

                    <div class="space-y-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full {{ $session->isScheduled() || $session->isLive() || $session->isEnded() ? 'bg-blue-100' : 'bg-gray-100' }}">
                                    <flux:icon.calendar class="w-4 h-4 {{ $session->isScheduled() || $session->isLive() || $session->isEnded() ? 'text-blue-600' : 'text-gray-400' }}" />
                                </div>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="text-sm font-medium text-gray-900">Scheduled</p>
                                <p class="text-xs text-gray-500">{{ $session->scheduled_start_at->format('M d, Y h:i A') }}</p>
                            </div>
                        </div>

                        @if ($session->actual_start_at || $session->isLive() || $session->isEnded())
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-full {{ $session->isLive() || $session->isEnded() ? 'bg-green-100' : 'bg-gray-100' }}">
                                        <flux:icon.signal class="w-4 h-4 {{ $session->isLive() || $session->isEnded() ? 'text-green-600' : 'text-gray-400' }}" />
                                    </div>
                                </div>
                                <div class="ml-3 flex-1">
                                    <p class="text-sm font-medium text-gray-900">
                                        {{ $session->isLive() ? 'Live Now' : 'Started' }}
                                    </p>
                                    @if ($session->actual_start_at)
                                        <p class="text-xs text-gray-500">{{ $session->actual_start_at->format('M d, Y h:i A') }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($session->actual_end_at || $session->isEnded())
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-full {{ $session->isEnded() ? 'bg-gray-100' : 'bg-gray-50' }}">
                                        <flux:icon.check-circle class="w-4 h-4 {{ $session->isEnded() ? 'text-gray-600' : 'text-gray-400' }}" />
                                    </div>
                                </div>
                                <div class="ml-3 flex-1">
                                    <p class="text-sm font-medium text-gray-900">Ended</p>
                                    @if ($session->actual_end_at)
                                        <p class="text-xs text-gray-500">{{ $session->actual_end_at->format('M d, Y h:i A') }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($session->isCancelled())
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-red-100">
                                        <flux:icon.x-circle class="w-4 h-4 text-red-600" />
                                    </div>
                                </div>
                                <div class="ml-3 flex-1">
                                    <p class="text-sm font-medium text-gray-900">Cancelled</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </flux:card>
        </div>
    </div>

    <!-- Analytics -->
    @if ($session->analytics)
        <flux:card class="mb-6">
            <div class="p-6">
                <flux:heading size="lg" class="mb-4">Session Analytics</flux:heading>

                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm font-medium text-gray-500">Peak Viewers</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($session->analytics->viewers_peak) }}</p>
                    </div>

                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm font-medium text-gray-500">Avg Viewers</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($session->analytics->viewers_avg) }}</p>
                    </div>

                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm font-medium text-gray-500">Total Likes</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($session->analytics->total_likes) }}</p>
                    </div>

                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm font-medium text-gray-500">Total Comments</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($session->analytics->total_comments) }}</p>
                    </div>

                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm font-medium text-gray-500">Total Shares</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($session->analytics->total_shares) }}</p>
                    </div>

                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm font-medium text-gray-500">Gifts Value</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1">RM {{ number_format($session->analytics->gifts_value, 2) }}</p>
                    </div>
                </div>

                <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-blue-900">Engagement Rate</p>
                            <p class="text-xs text-blue-700 mt-1">
                                Based on likes, comments, and shares vs average viewers
                            </p>
                        </div>
                        <p class="text-3xl font-bold text-blue-900">{{ number_format($session->analytics->engagement_rate, 1) }}%</p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 border border-gray-200 rounded-lg">
                        <p class="text-sm font-medium text-gray-500">Total Engagement</p>
                        <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($session->analytics->total_engagement) }}</p>
                        <p class="text-xs text-gray-500 mt-1">Likes + Comments + Shares</p>
                    </div>

                    <div class="p-4 border border-gray-200 rounded-lg">
                        <p class="text-sm font-medium text-gray-500">Duration</p>
                        <p class="text-xl font-bold text-gray-900 mt-1">{{ $session->analytics->duration_minutes }} minutes</p>
                        @if ($session->actual_start_at && $session->actual_end_at)
                            <p class="text-xs text-gray-500 mt-1">
                                {{ $session->actual_start_at->format('h:i A') }} - {{ $session->actual_end_at->format('h:i A') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </flux:card>
    @elseif ($session->isEnded())
        <flux:card class="mb-6">
            <div class="p-6">
                <div class="text-center py-8">
                    <flux:icon.chart-bar class="mx-auto h-12 w-12 text-gray-400 mb-3" />
                    <p class="text-gray-500">No analytics data available yet</p>
                    <p class="text-sm text-gray-400 mt-1">Upload your session details to add analytics</p>
                    <flux:button variant="primary" href="{{ route('live-host.session-slots') }}" wire:navigate class="mt-4">
                        <div class="flex items-center">
                            <flux:icon.arrow-up-tray class="w-4 h-4 mr-2" />
                            Upload Details
                        </div>
                    </flux:button>
                </div>
            </div>
        </flux:card>
    @endif

    <!-- Attachments -->
    <flux:card class="mb-20 lg:mb-6">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Attachments</flux:heading>
                @if ($session->attachments->count() > 0)
                    <flux:badge variant="outline">{{ $session->attachments->count() }} files</flux:badge>
                @endif
            </div>

            @if ($session->attachments->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($session->attachments as $attachment)
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    @if (str_starts_with($attachment->file_type, 'image/'))
                                        <flux:icon.photo class="w-5 h-5 text-blue-600" />
                                    @elseif (str_starts_with($attachment->file_type, 'video/'))
                                        <flux:icon.video-camera class="w-5 h-5 text-purple-600" />
                                    @elseif (str_contains($attachment->file_type, 'pdf'))
                                        <flux:icon.document-text class="w-5 h-5 text-red-600" />
                                    @else
                                        <flux:icon.document class="w-5 h-5 text-gray-600" />
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $attachment->file_name }}</p>
                                    </div>
                                </div>
                            </div>

                            <p class="text-xs text-gray-500 mb-2">{{ $this->getFormattedFileSize($attachment->file_size) }}</p>

                            @if ($attachment->description)
                                <p class="text-sm text-gray-600 mb-2">{{ $attachment->description }}</p>
                            @endif

                            <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                                <p class="text-xs text-gray-500">
                                    Uploaded by {{ $attachment->uploader->name }}
                                </p>
                                <a href="{{ Storage::url($attachment->file_path) }}"
                                   target="_blank"
                                   class="text-xs text-blue-600 hover:text-blue-800">
                                    View
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <flux:icon.document class="mx-auto h-12 w-12 text-gray-400 mb-3" />
                    <p class="text-gray-500">No attachments</p>
                    <p class="text-sm text-gray-400 mt-1">Files and documents for this session will appear here</p>
                </div>
            @endif
        </div>
    </flux:card>

    <!-- Start Live Modal -->
    <flux:modal wire:model="showStartModal" class="max-w-md">
        <div class="p-6">
            <div class="text-center mb-6">
                <div class="mx-auto w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mb-4">
                    <flux:icon.play class="w-6 h-6 text-green-600" />
                </div>
                <flux:heading size="lg">Start Live Session?</flux:heading>
                <p class="text-sm text-gray-500 mt-2">
                    This will mark your session as "Live". Make sure you're ready to start streaming.
                </p>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <p class="text-sm font-medium text-gray-700">{{ $session->title }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $session->platformAccount->platform->name }} - {{ $session->platformAccount->account_name }}</p>
            </div>

            <div class="flex gap-3">
                <flux:button variant="primary" wire:click="startLive" class="flex-1">
                    <div class="flex items-center justify-center">
                        <flux:icon.play class="w-4 h-4 mr-2" />
                        Start Live
                    </div>
                </flux:button>
                <flux:button variant="ghost" wire:click="$set('showStartModal', false)">
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- End Live Modal -->
    <flux:modal wire:model="showEndModal" class="max-w-md">
        <div class="p-6">
            <div class="text-center mb-6">
                <div class="mx-auto w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mb-4">
                    <flux:icon.stop class="w-6 h-6 text-red-600" />
                </div>
                <flux:heading size="lg">End Live Session?</flux:heading>
                <p class="text-sm text-gray-500 mt-2">
                    This will mark your session as "Ended". You can upload session details afterwards.
                </p>
            </div>

            @if ($session->actual_start_at)
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Live Duration:</span>
                        <span class="text-sm font-bold text-gray-900">{{ $session->actual_start_at->diffForHumans(null, true) }}</span>
                    </div>
                </div>
            @endif

            <div class="flex gap-3">
                <flux:button variant="danger" wire:click="endLive" class="flex-1">
                    <div class="flex items-center justify-center">
                        <flux:icon.stop class="w-4 h-4 mr-2" />
                        End Live
                    </div>
                </flux:button>
                <flux:button variant="ghost" wire:click="$set('showEndModal', false)">
                    Cancel
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Cancel Session Modal -->
    <flux:modal wire:model="showCancelModal" class="max-w-md">
        <div class="p-6">
            <div class="text-center mb-6">
                <div class="mx-auto w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mb-4">
                    <flux:icon.x-circle class="w-6 h-6 text-red-600" />
                </div>
                <flux:heading size="lg">Cancel Session?</flux:heading>
                <p class="text-sm text-gray-500 mt-2">
                    This action cannot be undone. The session will be marked as cancelled.
                </p>
            </div>

            <div class="bg-red-50 rounded-lg p-4 mb-6">
                <p class="text-sm font-medium text-red-700">{{ $session->title }}</p>
                <p class="text-xs text-red-600 mt-1">Scheduled: {{ $session->scheduled_start_at->format('M d, Y h:i A') }}</p>
            </div>

            <div class="flex gap-3">
                <flux:button variant="danger" wire:click="cancelSession" class="flex-1">
                    <div class="flex items-center justify-center">
                        <flux:icon.x-mark class="w-4 h-4 mr-2" />
                        Cancel Session
                    </div>
                </flux:button>
                <flux:button variant="ghost" wire:click="$set('showCancelModal', false)">
                    Keep Session
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Bottom Navigation -->
    <x-live-host-nav />
</div>
