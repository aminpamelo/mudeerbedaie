<x-layouts.store :seo="$seo">
    @php
        $thumb = $course->thumbnail_url;
        $teacher = $course->teacher?->user;
        $student = auth()->check() ? auth()->user()->student : null;
        $enrolled = $student && $course->enrollments()
            ->where('student_id', $student->id)
            ->whereIn('status', ['enrolled', 'active'])
            ->exists();
    @endphp

    {{-- Breadcrumb --}}
    <section class="border-b border-violet-100/70 bg-gradient-to-b from-violet-50/60 to-white">
        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
            <nav class="flex flex-wrap items-center gap-1.5 text-xs font-medium text-zinc-400">
                <a href="{{ route('storefront.home') }}" class="hover:text-violet-700">{{ __('store.nav_home') }}</a>
                <flux:icon name="chevron-right" class="h-3.5 w-3.5" />
                <a href="{{ route('storefront.courses') }}" class="hover:text-violet-700">{{ __('store.lms_title') }}</a>
                <flux:icon name="chevron-right" class="h-3.5 w-3.5" />
                <span class="truncate text-zinc-600">{{ $course->name }}</span>
            </nav>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2 lg:gap-12">
            {{-- Visual --}}
            <div>
                <div class="relative aspect-[16/10] overflow-hidden rounded-2xl ring-1 ring-zinc-900/5">
                    @if($thumb)
                        <div class="h-full w-full bg-zinc-50 bg-cover bg-center" style="background-image:url('{{ $thumb }}')" role="img" aria-label="{{ $course->name }}"></div>
                    @else
                        <div class="store-grad flex h-full w-full items-center justify-center">
                            <flux:icon name="academic-cap" class="h-20 w-20 text-white/90" />
                        </div>
                    @endif
                    <span class="absolute left-4 top-4 rounded-full bg-white/20 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white backdrop-blur">{{ __('store.lms_badge') }}</span>
                </div>
            </div>

            {{-- Info --}}
            <div class="flex flex-col">
                <span class="text-xs font-semibold uppercase tracking-wide text-fuchsia-600">{{ __('store.lms_badge') }}</span>
                <h1 class="font-display mt-1.5 text-2xl font-extrabold leading-tight text-zinc-900 sm:text-3xl">{{ $course->name }}</h1>

                @if($teacher)
                    <div class="mt-3 flex items-center gap-2 text-sm text-zinc-600">
                        <flux:icon name="user" class="h-4 w-4 text-violet-500" />
                        <span>{{ __('store.lms_taught_by') }} <span class="font-semibold text-zinc-800">{{ $teacher->name }}</span></span>
                    </div>
                @endif

                @if($course->short_description)
                    <p class="mt-4 text-sm leading-relaxed text-zinc-600">{{ $course->short_description }}</p>
                @endif

                <div class="mt-5 flex items-end gap-3">
                    <span class="store-grad-text font-display text-3xl font-extrabold tabular-nums">{{ $course->formatted_fee }}</span>
                </div>

                <div class="mt-6">
                    @if($enrolled)
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <span class="inline-flex items-center gap-1.5 rounded-xl bg-violet-50 px-4 py-3 text-sm font-semibold text-violet-700">
                                <flux:icon name="check-circle" class="h-5 w-5" /> {{ __('store.lms_enrolled') }}
                            </span>
                            <a href="{{ route('student.classes.index') }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-zinc-200 px-4 py-3 text-sm font-semibold text-zinc-700 transition-colors hover:border-violet-300 hover:text-violet-700">
                                {{ __('store.lms_go_to_classes') }} <flux:icon name="arrow-right" class="h-4 w-4" />
                            </a>
                        </div>
                    @else
                        <livewire:store.course-cart :course="$course" :key="'cc-'.$course->id" />
                        @guest
                            <p class="mt-2 text-xs text-zinc-400">{{ __('store.lms_login_hint') }}</p>
                        @endguest
                    @endif
                </div>

                <div class="mt-8 grid grid-cols-1 gap-3 border-t border-zinc-100 pt-6 text-sm text-zinc-600 sm:grid-cols-2">
                    <div class="flex items-center gap-2"><flux:icon name="rectangle-stack" class="h-4 w-4 text-violet-600" /> {{ trans_choice('store.lms_class_count', $course->classes_count, ['count' => $course->classes_count]) }}</div>
                    <div class="flex items-center gap-2"><flux:icon name="users" class="h-4 w-4 text-fuchsia-600" /> {{ trans_choice('store.lms_student_count', $course->active_enrollments_count, ['count' => $course->active_enrollments_count]) }}</div>
                    <div class="flex items-center gap-2"><flux:icon name="video-camera" class="h-4 w-4 text-rose-500" /> {{ __('store.lms_recordings_note') }}</div>
                    <div class="flex items-center gap-2"><flux:icon name="shield-check" class="h-4 w-4 text-violet-600" /> {{ __('store.stat_secure') }}</div>
                </div>
            </div>
        </div>

        {{-- Classes --}}
        @if($course->classes->isNotEmpty())
            <section class="mt-12">
                <h2 class="font-display text-xl font-bold text-zinc-900 sm:text-2xl">{{ __('store.lms_classes_title') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('store.lms_classes_subtitle') }}</p>

                <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach($course->classes as $class)
                        @php
                            $status = $class->computed_status ?? $class->status;
                            $statusMeta = match ($status) {
                                'active' => ['label' => __('store.lms_status_active'), 'class' => 'bg-emerald-50 text-emerald-700'],
                                'upcoming' => ['label' => __('store.lms_status_upcoming'), 'class' => 'bg-violet-50 text-violet-700'],
                                'completed' => ['label' => __('store.lms_status_completed'), 'class' => 'bg-zinc-100 text-zinc-500'],
                                default => ['label' => ucfirst($status), 'class' => 'bg-zinc-100 text-zinc-500'],
                            };
                            $scheduleArr = $class->timetable?->formatted_schedule;
                            $schedule = null;
                            if (is_array($scheduleArr) && $scheduleArr !== []) {
                                $parts = [];
                                foreach ($scheduleArr as $label => $times) {
                                    $flat = [];
                                    array_walk_recursive($times, function ($t) use (&$flat) { $flat[] = $t; });
                                    $parts[] = $label.' ('.implode(', ', $flat).')';
                                }
                                $schedule = implode(' · ', $parts);
                            }
                        @endphp
                        <div class="rounded-2xl border border-zinc-100 bg-white p-5 transition-all hover:border-violet-200 hover:shadow-md" wire:key="class-{{ $class->id }}">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="font-display text-base font-bold leading-snug text-zinc-900">{{ $class->title }}</h3>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span>
                            </div>

                            @if($class->storefront_description)
                                <p class="mt-2 text-sm text-zinc-500 line-clamp-2">{{ $class->storefront_description }}</p>
                            @endif

                            <div class="mt-3 space-y-1.5 text-sm text-zinc-500">
                                @if($class->teacher?->user)
                                    <div class="flex items-center gap-2">
                                        <flux:icon name="user" class="h-4 w-4 text-violet-400" />
                                        {{ $class->teacher->user->name }}
                                    </div>
                                @endif

                                @if($schedule)
                                    <div class="flex items-center gap-2">
                                        <flux:icon name="calendar-days" class="h-4 w-4 text-fuchsia-400" />
                                        {{ $schedule }}
                                    </div>
                                @elseif($class->date_time)
                                    <div class="flex items-center gap-2">
                                        <flux:icon name="calendar-days" class="h-4 w-4 text-fuchsia-400" />
                                        {{ $class->date_time->format('d M Y, g:i A') }}
                                    </div>
                                @endif

                                {{-- Class type --}}
                                <div class="flex items-center gap-2">
                                    <flux:icon name="{{ $class->class_type === 'individual' ? 'user' : 'users' }}" class="h-4 w-4 text-emerald-400" />
                                    {{ $class->class_type === 'individual' ? 'Individu' : 'Kumpulan' }}
                                </div>

                                {{-- Capacity --}}
                                @if($class->max_capacity)
                                    @php $enrolled = $class->active_students_count ?? $class->activeStudents->count(); @endphp
                                    <div class="flex items-center gap-2">
                                        <flux:icon name="users" class="h-4 w-4 text-violet-400" />
                                        {{ $enrolled }}/{{ $class->max_capacity }} pelajar
                                    </div>
                                    <div class="mt-1 h-1.5 w-full rounded-full bg-zinc-100">
                                        <div class="h-1.5 rounded-full bg-violet-500" style="width: {{ min(100, ($enrolled / $class->max_capacity) * 100) }}%"></div>
                                    </div>
                                @endif

                                {{-- Recordings --}}
                                @if(($class->recordings_count ?? 0) > 0)
                                    <div class="flex items-center gap-2 font-medium text-rose-500">
                                        <flux:icon name="video-camera" class="h-4 w-4" />
                                        {{ trans_choice('store.lms_recording_count', $class->recordings_count, ['count' => $class->recordings_count]) }}
                                    </div>
                                @endif
                            </div>

                            {{-- Syllabus preview --}}
                            @if($class->syllabi && $class->syllabi->isNotEmpty())
                                <div class="mt-3 border-t border-zinc-50 pt-3">
                                    <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-zinc-400">Silibus</p>
                                    <ul class="space-y-1">
                                        @foreach($class->syllabi->take(3) as $topic)
                                            <li class="flex items-center gap-1.5 text-sm text-zinc-600">
                                                <flux:icon name="check-circle" class="h-3.5 w-3.5 text-violet-400" />
                                                {{ $topic->title }}
                                            </li>
                                        @endforeach
                                        @if($class->syllabi->count() > 3)
                                            <li class="text-xs text-violet-500">+ {{ $class->syllabi->count() - 3 }} lagi...</li>
                                        @endif
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Description --}}
        @if($course->description)
            <section class="mt-12 max-w-3xl">
                <h2 class="font-display text-xl font-bold text-zinc-900">{{ __('store.lms_about_title') }}</h2>
                <div class="mt-4 text-sm leading-relaxed text-zinc-600">{!! nl2br(e($course->description)) !!}</div>
            </section>
        @endif
    </div>

</x-layouts.store>
