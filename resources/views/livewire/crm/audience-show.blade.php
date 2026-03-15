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
                <flux:heading size="xl">{{ $audience->name }}</flux:heading>
                <flux:badge size="sm" :class="$audience->status === 'active' ? 'badge-green' : 'badge-gray'">
                    {{ ucfirst($audience->status) }}
                </flux:badge>
            </div>
            @if($audience->description)
                <flux:text class="mt-2 ml-[72px]">{{ $audience->description }}</flux:text>
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
        <flux:card class="p-6">
            <div class="text-center">
                <p class="text-2xl font-semibold text-blue-600">{{ $totalMembers }}</p>
                <p class="text-sm text-gray-500 mt-1">Total Members</p>
            </div>
        </flux:card>

        <flux:card class="p-6">
            <div class="text-center">
                <p class="text-2xl font-semibold text-green-600">{{ $activeMembers }}</p>
                <p class="text-sm text-gray-500 mt-1">Active Members</p>
            </div>
        </flux:card>

        <flux:card class="p-6">
            <div class="text-center">
                <p class="text-2xl font-semibold text-purple-600">{{ $broadcastCount }}</p>
                <p class="text-sm text-gray-500 mt-1">Broadcasts Sent</p>
            </div>
        </flux:card>

        <flux:card class="p-6">
            <div class="text-center">
                <p class="text-2xl font-semibold text-gray-600">{{ $audience->created_at->format('M d, Y') }}</p>
                <p class="text-sm text-gray-500 mt-1">Created On</p>
            </div>
        </flux:card>
    </div>

    {{-- Members Table --}}
    <flux:card>
        <div class="p-6 border-b border-gray-200 dark:border-zinc-700">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">Members</flux:heading>
                    <flux:text class="text-gray-600">Students subscribed to this audience</flux:text>
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
            <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                <thead class="bg-gray-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Country</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subscribed</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-zinc-700">
                    @forelse($students as $student)
                        <tr class="hover:bg-gray-50 dark:hover:bg-zinc-700" wire:key="student-{{ $student->id }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="{{ route('students.show', $student) }}" class="text-sm font-medium text-gray-900 dark:text-white hover:text-blue-600 transition-colors">
                                    {{ $student->user->name ?? $student->email }}
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">{{ $student->user->email ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">{{ $student->phone ?: '-' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">{{ $student->country ?: '-' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge size="sm" :class="match($student->status) {
                                    'active' => 'badge-green',
                                    'inactive' => 'badge-gray',
                                    'graduated' => 'badge-blue',
                                    'suspended' => 'badge-red',
                                    default => 'badge-gray'
                                }">
                                    {{ ucfirst($student->status ?? 'unknown') }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    {{ $student->pivot->subscribed_at ? \Carbon\Carbon::parse($student->pivot->subscribed_at)->format('M d, Y') : ($student->pivot->created_at ? \Carbon\Carbon::parse($student->pivot->created_at)->format('M d, Y') : '-') }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <flux:icon name="users" class="mx-auto h-12 w-12 text-gray-400" />
                                <flux:heading size="lg" class="mt-2">No members found</flux:heading>
                                <flux:text class="mt-1 text-gray-500">
                                    @if($search)
                                        No members match your search criteria.
                                    @else
                                        This audience has no members yet.
                                    @endif
                                </flux:text>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($students->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700">
                {{ $students->links() }}
            </div>
        @endif
    </flux:card>
</div>
