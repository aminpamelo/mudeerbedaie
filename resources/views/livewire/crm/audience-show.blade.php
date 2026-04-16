<?php

use App\Models\Audience;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public Audience $audience;

    public string $search = '';

    public function mount(Audience $audience): void
    {
        $this->audience = $audience;
    }

    public function with(): array
    {
        $studentsQuery = $this->audience->students()->with('user');

        if ($this->search) {
            $studentsQuery->where(function ($q) {
                $q->where('students.phone', 'like', '%' . $this->search . '%')
                    ->orWhereHas('user', function ($uq) {
                        $uq->where('name', 'like', '%' . $this->search . '%')
                            ->orWhere('email', 'like', '%' . $this->search . '%');
                    });
            });
        }

        $totalMembers = $this->audience->students()->count();
        $activeMembers = $this->audience->students()->where('students.status', 'active')->count();
        $broadcastCount = $this->audience->broadcasts()->count();

        return [
            'students' => $studentsQuery->orderBy('audience_student.subscribed_at', 'desc')->paginate(20),
            'totalMembers' => $totalMembers,
            'activeMembers' => $activeMembers,
            'broadcastCount' => $broadcastCount,
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }
}; ?>

<div>
    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <flux:button variant="ghost" size="sm" href="{{ route('crm.audiences.index') }}">
                    <div class="flex items-center">
                        <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                        Back
                    </div>
                </flux:button>
                <h1 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ $audience->name }}</h1>
                @if($audience->status === 'active')
                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">{{ ucfirst($audience->status) }}</span>
                @else
                    <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">{{ ucfirst($audience->status) }}</span>
                @endif
            </div>
            @if($audience->description)
                <p class="mt-2 ml-[72px] text-sm text-zinc-500 dark:text-zinc-400">{{ $audience->description }}</p>
            @endif
        </div>
        <flux:button variant="primary" href="{{ route('crm.audiences.edit', $audience) }}">
            <div class="flex items-center">
                <flux:icon name="pencil-square" class="w-4 h-4 mr-1" />
                Edit Audience
            </div>
        </flux:button>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $totalMembers }}</p>
            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mt-1">Total Members</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $activeMembers }}</p>
            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mt-1">Active Members</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $broadcastCount }}</p>
            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mt-1">Broadcasts Sent</p>
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-base font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $audience->created_at->format('M d, Y') }}</p>
            <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mt-1">Created On</p>
        </div>
    </div>

    {{-- Members Table --}}
    <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Members</h3>
                    <p class="mt-0.5 text-[13px] text-zinc-500 dark:text-zinc-400">Students subscribed to this audience</p>
                </div>
                <div class="w-72">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search members..."
                        icon="magnifying-glass" />
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Name</th>
                        <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Email</th>
                        <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Phone</th>
                        <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Country</th>
                        <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Status</th>
                        <th class="bg-zinc-50 px-4 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:bg-zinc-800/50 dark:text-zinc-500">Subscribed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($students as $student)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30" wire:key="student-{{ $student->id }}">
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <a href="{{ route('students.show', $student) }}" class="text-sm font-medium text-zinc-900 dark:text-zinc-100 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors">
                                    {{ $student->user->name ?? $student->email }}
                                </a>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $student->user->email ?? '-' }}</span>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $student->phone ?: '-' }}</span>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $student->country ?: '-' }}</span>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                @php
                                    $statusClasses = match($student->status) {
                                        'active' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
                                        'inactive' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400',
                                        'graduated' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400',
                                        'suspended' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
                                        default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400',
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider {{ $statusClasses }}">
                                    {{ ucfirst($student->status ?? 'unknown') }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span class="text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ $student->pivot->subscribed_at ? \Carbon\Carbon::parse($student->pivot->subscribed_at)->format('M d, Y') : ($student->pivot->created_at ? \Carbon\Carbon::parse($student->pivot->created_at)->format('M d, Y') : '-') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <flux:icon name="users" class="mx-auto h-8 w-8 text-zinc-300 dark:text-zinc-600" />
                                <p class="mt-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">No members found</p>
                                <p class="mt-0.5 text-[13px] text-zinc-500 dark:text-zinc-400">
                                    @if($search)
                                        No members match your search criteria.
                                    @else
                                        This audience has no members yet.
                                    @endif
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($students->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                {{ $students->links() }}
            </div>
        @endif
    </div>
</div>
