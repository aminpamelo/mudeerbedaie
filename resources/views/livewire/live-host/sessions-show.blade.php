<?php

use App\Models\LiveSession;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public LiveSession $session;

    public function mount(LiveSession $session)
    {
        // Verify the session belongs to this live host
        if ($session->platformAccount->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to this session.');
        }

        $this->session = $session->load([
            'platformAccount.platform',
            'liveSchedule',
            'analytics',
            'attachments.uploader'
        ]);
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Session Information -->
        <div class="lg:col-span-2">
            <flux:card>
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">Session Information</flux:heading>

                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Status</p>
                                <flux:badge variant="filled" :color="$session->status_color" class="mt-1">
                                    {{ ucfirst($session->status) }}
                                </flux:badge>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Platform</p>
                                <p class="text-sm text-gray-900 mt-1">{{ $session->platformAccount->platform->name }}</p>
                            </div>
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-500">Account</p>
                            <p class="text-sm text-gray-900 mt-1">{{ $session->platformAccount->account_name }}</p>
                            @if ($session->platformAccount->account_email)
                                <p class="text-xs text-gray-500">{{ $session->platformAccount->account_email }}</p>
                            @endif
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
                                @if ($session->liveSchedule->is_recurring)
                                    <flux:badge variant="outline" color="purple" size="sm" class="mt-1">
                                        Recurring
                                    </flux:badge>
                                @endif
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
    @else
        <flux:card class="mb-6">
            <div class="p-6">
                <div class="text-center py-8">
                    <flux:icon.chart-bar class="mx-auto h-12 w-12 text-gray-400 mb-3" />
                    <p class="text-gray-500">No analytics data available yet</p>
                    <p class="text-sm text-gray-400 mt-1">Analytics will be available after the session ends</p>
                </div>
            </div>
        </flux:card>
    @endif

    <!-- Attachments -->
    <flux:card>
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

    <!-- Bottom Navigation -->
    <x-live-host-nav />
</div>
