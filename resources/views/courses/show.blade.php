<x-layouts.app>
    <x-slot:title>{{ $course->name }} - Course Details</x-slot:title>

    <div class="min-h-screen bg-white">
        <livewire:admin.course-show :course="$course" />
    </div>
</x-layouts.app>