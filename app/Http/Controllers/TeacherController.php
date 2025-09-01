<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function index(): View
    {
        return view('livewire.admin.teacher-list');
    }

    public function create(): View
    {
        return view('livewire.admin.teacher-create');
    }

    public function show(Teacher $teacher): View
    {
        return view('livewire.admin.teacher-show', compact('teacher'));
    }

    public function edit(Teacher $teacher): View
    {
        return view('livewire.admin.teacher-edit', compact('teacher'));
    }
}
