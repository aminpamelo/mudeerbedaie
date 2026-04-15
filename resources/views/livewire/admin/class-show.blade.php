<?php

use App\Models\ClassModel;
use App\Services\StripeService;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ClassModel $class;

    public function mount(ClassModel $class): void
    {
        $this->class = $class->load([
            'course',
            'teacher.user',
            'sessions.attendances.student.user',
            'activeStudents.student.user',
            'timetable',
            'pics',
        ]);

        // Initialize payment year
        $this->paymentYear = now()->year;

        // Read active tab from URL query parameter
        $this->activeTab = request()->query('tab', 'overview');

        // Restore expanded shipment from URL query parameter
        if (request()->query('expandedShipment')) {
            $this->selectedShipmentId = (int) request()->query('expandedShipment');
        }
    }

    public function getEnrolledStudentsCountProperty()
    {
        return $this->class->course->activeEnrollments()->count();
    }

    public function getTotalSessionsCountProperty(): int
    {
        return $this->class->sessions->count();
    }

    public function getCompletedSessionsCountProperty(): int
    {
        return $this->class->sessions->where('status', 'completed')->count();
    }

    public function getUpcomingSessionsCountProperty(): int
    {
        return $this->class->sessions->where('status', 'scheduled')->where('session_date', '>', now()->toDateString())->count();
    }

    public function getTotalAttendanceRecordsProperty(): int
    {
        return $this->class->sessions->sum(function ($session) {
            return $session->attendances->count();
        });
    }

    public function getTotalPresentCountProperty(): int
    {
        return $this->class->sessions->sum(function ($session) {
            return $session->attendances->where('status', 'present')->count();
        });
    }

    public function getTotalAbsentCountProperty(): int
    {
        return $this->class->sessions->sum(function ($session) {
            return $session->attendances->where('status', 'absent')->count();
        });
    }

    public function getTotalLateCountProperty(): int
    {
        return $this->class->sessions->sum(function ($session) {
            return $session->attendances->where('status', 'late')->count();
        });
    }

    public function getTotalExcusedCountProperty(): int
    {
        return $this->class->sessions->sum(function ($session) {
            return $session->attendances->where('status', 'excused')->count();
        });
    }

    public function getOverallAttendanceRateProperty(): float
    {
        if ($this->total_attendance_records === 0) {
            return 0;
        }

        return round(($this->total_present_count / $this->total_attendance_records) * 100, 1);
    }

    public function getSessionsByMonthProperty(): array
    {
        return $this->class->sessions
            ->sortBy('session_date')
            ->groupBy(function ($session) {
                return $session->session_date->format('Y-m');
            })
            ->map(function ($sessions, $key) {
                [$year, $month] = explode('-', $key);

                return [
                    'year' => $year,
                    'month' => $month,
                    'month_name' => \Carbon\Carbon::createFromFormat('m', $month)->format('F'),
                    'sessions' => $sessions,
                    'stats' => [
                        'total' => $sessions->count(),
                        'completed' => $sessions->where('status', 'completed')->count(),
                        'cancelled' => $sessions->where('status', 'cancelled')->count(),
                        'no_show' => $sessions->where('status', 'no_show')->count(),
                        'upcoming' => $sessions->where('status', 'scheduled')->count(),
                        'ongoing' => $sessions->where('status', 'ongoing')->count(),
                    ],
                ];
            })
            ->toArray();
    }

    public $activeTab = 'overview';

    protected $queryString = [
        'activeTab' => ['as' => 'tab'],
    ];

    public $showCreateSessionModal = false;

    public $showEnrollStudentsModal = false;

    // Shipment management properties
    public $selectedShipmentId = null;

    public $shipmentSearch = '';

    public $shipmentStatusFilter = '';

    public $shipmentItemsPerPage = 20;

    // Table view properties
    public array $selectedShipmentIds = [];

    // Upsell tab properties
    public string $upsellDateFrom = '';
    public string $upsellDateTo = '';
    public ?int $upsellFilterPicId = null;
    public ?int $upsellFilterFunnelId = null;
    public ?int $upsellDetailSessionId = null;
    public bool $showUpsellDetailModal = false;

    // Student item selection properties
    public array $selectedShipmentItemIds = [];

    public string $filterMonth = '';

    public string $filterYear = '';

    public string $filterStatus = '';

    public $showImportModal = false;

    public $importFile;

    public $importProcessing = false;

    public $importProgress = [];

    public $matchBy = 'name';

    public $showImportResultModal = false;

    public $importResult = [];

    // Import students to class modal
    public $showImportStudentModal = false;

    public $importStudentFile;

    public $importStudentProcessing = false;

    public $importStudentResult = [];

    public $showImportStudentResultModal = false;

    public $createMissingStudents = false;

    public $autoEnrollImported = true;

    public $importStudentPassword = 'password123';

    public ?int $currentStudentImportProgressId = null;

    public ?array $studentImportProgress = null;

    // Student shipment details modal
    public $showStudentShipmentModal = false;

    public $selectedShipmentItem = null;

    // Edit shipment item modal
    public $showEditShipmentItemModal = false;

    public $editingShipmentItem = null;

    public $editTrackingNumber = '';

    public $editStatus = '';

    public $editAddressLine1 = '';

    public $editAddressLine2 = '';

    public $editCity = '';

    public $editState = '';

    public $editPostcode = '';

    public $editCountry = '';

    // Session creation properties
    public $sessionDate = '';

    public $sessionTime = '';

    public $duration = 60;

    // Student enrollment properties
    public $studentSearch = '';

    public $selectedStudents = [];

    public $enrollOrderIds = [];

    // Enrolled students search
    public $enrolledStudentSearch = '';

    // Enrolled students pagination
    public int $studentsPerPage = 20;

    public function updatingEnrolledStudentSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStudentsPerPage(): void
    {
        $this->resetPage();
    }

    // Individual enrollment
    public $enrollingStudent = null;

    // Student actions modals
    public $showViewStudentModal = false;

    public $showEditStudentModal = false;

    public $showUnenrollConfirmModal = false;

    public $selectedClassStudent = null;

    public $editNotes = '';

    // Eligible students (active enrollment but not in class)
    public $selectedEligibleStudents = [];

    public $eligibleStudentSearch = '';

    // Create student modal properties
    public $showCreateStudentModal = false;

    public $newStudentName = '';

    public $newStudentEmail = '';

    public $newStudentPassword = '';

    public $newStudentPasswordConfirmation = '';

    public $newStudentIcNumber = '';

    public $newStudentCountryCode = '+60';

    public $newStudentPhone = '';

    public $enrollAfterCreate = true;

    public $newStudentOrderId = '';

    public function openCreateStudentModal(): void
    {
        $this->showCreateStudentModal = true;
        $this->resetCreateStudentForm();
    }

    public function closeCreateStudentModal(): void
    {
        $this->showCreateStudentModal = false;
        $this->resetCreateStudentForm();
    }

    public function resetCreateStudentForm(): void
    {
        $this->newStudentName = '';
        $this->newStudentEmail = '';
        $this->newStudentPassword = '';
        $this->newStudentPasswordConfirmation = '';
        $this->newStudentIcNumber = '';
        $this->newStudentCountryCode = '+60';
        $this->newStudentPhone = '';
        $this->enrollAfterCreate = true;
        $this->newStudentOrderId = '';
    }

    public function createStudent(): void
    {
        $this->validate([
            'newStudentName' => 'required|string|min:3|max:255',
            'newStudentEmail' => 'nullable|string|email|max:255|unique:users,email',
            'newStudentPassword' => 'required|string|min:8|same:newStudentPasswordConfirmation',
            'newStudentIcNumber' => 'nullable|string|size:12|regex:/^[0-9]{12}$/|unique:students,ic_number',
            'newStudentCountryCode' => 'required|string|max:5',
            'newStudentPhone' => 'required|string|max:15|regex:/^[0-9]+$/',
        ], [
            'newStudentName.required' => 'Full name is required.',
            'newStudentName.min' => 'Full name must be at least 3 characters.',
            'newStudentEmail.email' => 'Please enter a valid email address.',
            'newStudentEmail.unique' => 'This email is already registered.',
            'newStudentPassword.required' => 'Password is required.',
            'newStudentPassword.min' => 'Password must be at least 8 characters.',
            'newStudentPassword.same' => 'Password confirmation does not match.',
            'newStudentIcNumber.size' => 'IC number must be exactly 12 digits.',
            'newStudentIcNumber.regex' => 'IC number must contain only numbers.',
            'newStudentIcNumber.unique' => 'This IC number is already registered.',
            'newStudentCountryCode.required' => 'Country code is required.',
            'newStudentPhone.required' => 'Phone number is required.',
            'newStudentPhone.regex' => 'Phone number must contain only numbers.',
        ]);

        // Check for duplicate phone number (combining country code + phone)
        $countryCode = ltrim($this->newStudentCountryCode, '+');
        $fullPhone = $countryCode.$this->newStudentPhone;

        $existingStudent = \App\Models\Student::where('phone', $fullPhone)->first();
        if ($existingStudent) {
            $this->addError('newStudentPhone', 'This phone number is already registered to another student.');

            return;
        }

        try {
            // Create user first
            $user = \App\Models\User::create([
                'name' => $this->newStudentName,
                'email' => $this->newStudentEmail ?: null,
                'password' => bcrypt($this->newStudentPassword),
                'role' => 'student',
            ]);

            // Create student profile (using $fullPhone from validation check above)
            $student = \App\Models\Student::create([
                'user_id' => $user->id,
                'ic_number' => $this->newStudentIcNumber ?: null,
                'phone' => $fullPhone,
                'status' => 'active',
            ]);

            $message = "Student {$this->newStudentName} created successfully!";

            // Enroll in class if option is selected
            if ($this->enrollAfterCreate) {
                $orderId = $this->newStudentOrderId ?: null;
                // Check capacity if class has max capacity
                if ($this->class->max_capacity) {
                    $currentCount = $this->class->activeStudents()->count();
                    if ($currentCount < $this->class->max_capacity) {
                        $this->class->addStudent($student, $orderId);
                        $message .= ' and enrolled in the class.';
                    } else {
                        $message .= ' Class is at maximum capacity, student was not enrolled.';
                    }
                } else {
                    $this->class->addStudent($student, $orderId);
                    $message .= ' and enrolled in the class.';
                }
            }

            session()->flash('success', $message);
            $this->closeCreateStudentModal();

            // Refresh the class data
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database errors (like unique constraint violations)
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed') || str_contains($e->getMessage(), 'Duplicate entry')) {
                if (str_contains($e->getMessage(), 'phone')) {
                    $this->addError('newStudentPhone', 'This phone number is already registered.');
                } elseif (str_contains($e->getMessage(), 'email')) {
                    $this->addError('newStudentEmail', 'This email is already registered.');
                } elseif (str_contains($e->getMessage(), 'ic_number')) {
                    $this->addError('newStudentIcNumber', 'This IC number is already registered.');
                } else {
                    $this->addError('newStudentName', 'A student with these details already exists.');
                }
            } else {
                $this->addError('newStudentName', 'Database error: '.$e->getMessage());
            }
        } catch (\Exception $e) {
            $this->addError('newStudentName', 'Failed to create student: '.$e->getMessage());
        }
    }

    public function openCreateSessionModal(): void
    {
        $this->showCreateSessionModal = true;
        // Reset form fields
        $this->sessionDate = '';
        $this->sessionTime = '';
        $this->duration = 60;
    }

    public function closeCreateSessionModal(): void
    {
        $this->showCreateSessionModal = false;
        // Reset form fields
        $this->sessionDate = '';
        $this->sessionTime = '';
        $this->duration = 60;
    }

    public function createSession(): void
    {
        $this->validate([
            'sessionDate' => 'required|date|after_or_equal:today',
            'sessionTime' => 'required',
            'duration' => 'required|integer|min:15|max:480',
        ], [
            'sessionDate.required' => 'Session date is required.',
            'sessionDate.date' => 'Please enter a valid date.',
            'sessionDate.after_or_equal' => 'Session date cannot be in the past.',
            'sessionTime.required' => 'Session time is required.',
            'duration.required' => 'Duration is required.',
            'duration.integer' => 'Duration must be a number.',
            'duration.min' => 'Duration must be at least 15 minutes.',
            'duration.max' => 'Duration cannot exceed 8 hours (480 minutes).',
        ]);

        try {
            \App\Models\ClassSession::create([
                'class_id' => $this->class->id,
                'session_date' => $this->sessionDate,
                'session_time' => $this->sessionTime,
                'duration_minutes' => $this->duration,
                'status' => 'scheduled',
            ]);

            session()->flash('success', 'Session created successfully.');
            $this->closeCreateSessionModal();

            // Refresh the class data to show the new session
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user',
            ]);
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create session. Please try again.');
        }
    }

    public function openEnrollStudentsModal(): void
    {
        $this->showEnrollStudentsModal = true;
        $this->studentSearch = '';
        $this->selectedStudents = [];
        $this->enrollOrderIds = [];
    }

    public function closeEnrollStudentsModal(): void
    {
        $this->showEnrollStudentsModal = false;
        $this->studentSearch = '';
        $this->selectedStudents = [];
        $this->enrollOrderIds = [];
    }

    public function getAvailableStudentsProperty()
    {
        // Get students who are NOT already in this class
        $classStudentIds = $this->class->activeStudents()
            ->pluck('student_id')
            ->toArray();

        $query = \App\Models\Student::whereNotIn('id', $classStudentIds)
            ->with('user');

        // Apply search filter
        if (! empty($this->studentSearch)) {
            $query->where(function ($q) {
                $q->whereHas('user', function ($userQuery) {
                    $userQuery->where('name', 'like', '%'.$this->studentSearch.'%')
                        ->orWhere('email', 'like', '%'.$this->studentSearch.'%');
                })
                    ->orWhere('student_id', 'like', '%'.$this->studentSearch.'%');
            });
        }

        return $query->orderBy('created_at', 'desc')->limit(100)->get();
    }

    public function selectAllStudents(): void
    {
        $this->selectedStudents = $this->available_students->pluck('id')->toArray();
    }

    public function deselectAllStudents(): void
    {
        $this->selectedStudents = [];
    }

    public function enrollStudent($studentId): void
    {
        // Check capacity if class has max capacity
        if ($this->class->max_capacity) {
            $currentCount = $this->class->activeStudents()->count();

            if ($currentCount >= $this->class->max_capacity) {
                session()->flash('error', 'Cannot enroll student. Class has reached maximum capacity.');

                return;
            }
        }

        try {
            $student = \App\Models\Student::find($studentId);
            if ($student) {
                $orderId = $this->enrollOrderIds[$studentId] ?? null;
                $this->class->addStudent($student, $orderId ?: null);
                session()->flash('success', "Successfully enrolled {$student->user->name} in the class.");

                // Refresh the class data
                $this->class->refresh();
                $this->class->load([
                    'course',
                    'teacher.user',
                    'sessions.attendances.student.user',
                    'activeStudents.student.user',
                ]);
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to enroll student. They may already be enrolled.');
        }
    }

    public function enrollSelectedStudents(): void
    {
        if (empty($this->selectedStudents)) {
            session()->flash('error', 'Please select at least one student to enroll.');

            return;
        }

        // Check capacity if class has max capacity
        if ($this->class->max_capacity) {
            $currentCount = $this->class->activeStudents()->count();
            $selectedCount = count($this->selectedStudents);

            if (($currentCount + $selectedCount) > $this->class->max_capacity) {
                session()->flash('error', 'Cannot enroll students. Class capacity would be exceeded.');

                return;
            }
        }

        $enrolled = 0;
        foreach ($this->selectedStudents as $studentId) {
            try {
                $student = \App\Models\Student::find($studentId);
                if ($student) {
                    $orderId = $this->enrollOrderIds[$studentId] ?? null;
                    $this->class->addStudent($student, $orderId ?: null);
                    $enrolled++;
                }
            } catch (\Exception $e) {
                // Skip if student already enrolled or other error
                continue;
            }
        }

        if ($enrolled > 0) {
            session()->flash('success', "Successfully enrolled {$enrolled} student(s) in the class.");
            $this->closeEnrollStudentsModal();

            // Refresh the class data to show updated student list
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user',
            ]);
        } else {
            session()->flash('error', 'No students were enrolled. They may already be in the class.');
        }
    }

    // Eligible Students (active enrollment but not in class)
    public function getEligibleEnrollmentsProperty()
    {
        $enrollments = $this->class->getEligibleEnrollments();

        // Apply search filter
        if (! empty($this->eligibleStudentSearch)) {
            $enrollments = $enrollments->filter(function ($enrollment) {
                $searchTerm = strtolower($this->eligibleStudentSearch);
                $student = $enrollment->student;

                return str_contains(strtolower($student->fullName ?? ''), $searchTerm) ||
                    str_contains(strtolower($student->phone ?? ''), $searchTerm) ||
                    str_contains(strtolower($student->user->email ?? ''), $searchTerm);
            });
        }

        return $enrollments;
    }

    public function toggleEligibleStudent($enrollmentId): void
    {
        if (in_array($enrollmentId, $this->selectedEligibleStudents)) {
            $this->selectedEligibleStudents = array_values(
                array_diff($this->selectedEligibleStudents, [$enrollmentId])
            );
        } else {
            $this->selectedEligibleStudents[] = $enrollmentId;
        }
    }

    public function enrollSelectedEligibleStudents(): void
    {
        if (empty($this->selectedEligibleStudents)) {
            session()->flash('error', 'Please select at least one student to enroll.');

            return;
        }

        // Check capacity if class has max capacity
        if ($this->class->max_capacity) {
            $currentCount = $this->class->activeStudents()->count();
            $selectedCount = count($this->selectedEligibleStudents);

            if (($currentCount + $selectedCount) > $this->class->max_capacity) {
                session()->flash('error', 'Cannot enroll students. Class capacity would be exceeded.');

                return;
            }
        }

        $enrolled = 0;
        $errors = [];

        foreach ($this->selectedEligibleStudents as $enrollmentId) {
            try {
                $enrollment = \App\Models\Enrollment::with('student')->find($enrollmentId);
                if ($enrollment && $enrollment->student) {
                    $this->class->addStudent($enrollment->student);
                    $enrolled++;
                }
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();

                continue;
            }
        }

        if ($enrolled > 0) {
            session()->flash('success', "Successfully enrolled {$enrolled} student(s) in the class.");
            $this->selectedEligibleStudents = [];

            // Refresh the class data to show updated student list
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user',
            ]);
        } else {
            session()->flash('error', 'No students were enrolled. '.implode(', ', $errors));
        }
    }

    // Student Actions Methods
    public function viewStudent($classStudentId): void
    {
        $this->selectedClassStudent = \App\Models\ClassStudent::with([
            'student.user',
            'class.course',
        ])->find($classStudentId);

        if ($this->selectedClassStudent) {
            $this->showViewStudentModal = true;
        }
    }

    public function editStudent($classStudentId): void
    {
        $this->selectedClassStudent = \App\Models\ClassStudent::find($classStudentId);

        if ($this->selectedClassStudent) {
            $this->editNotes = $this->selectedClassStudent->notes ?? '';
            $this->showEditStudentModal = true;
        }
    }

    public function saveStudentEdit(): void
    {
        $this->validate([
            'editNotes' => 'nullable|string|max:1000',
        ]);

        if ($this->selectedClassStudent) {
            $this->selectedClassStudent->update([
                'notes' => $this->editNotes,
            ]);

            session()->flash('success', 'Student enrollment updated successfully.');
            $this->closeEditStudentModal();

            // Refresh the class data
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user',
            ]);
        }
    }

    public function updateStudentOrderId($classStudentId, $orderId): void
    {
        $classStudent = \App\Models\ClassStudent::find($classStudentId);

        if ($classStudent) {
            $classStudent->update([
                'order_id' => $orderId ?: null,
            ]);

            // Refresh the class data
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user',
            ]);
        }
    }

    public function closeViewStudentModal(): void
    {
        $this->showViewStudentModal = false;
        $this->selectedClassStudent = null;
    }

    public function closeEditStudentModal(): void
    {
        $this->showEditStudentModal = false;
        $this->selectedClassStudent = null;
        $this->editNotes = '';
    }

    public function confirmUnenroll($classStudentId): void
    {
        $this->selectedClassStudent = \App\Models\ClassStudent::with(['student.user'])->find($classStudentId);

        if ($this->selectedClassStudent) {
            $this->showUnenrollConfirmModal = true;
        }
    }

    public function unenrollStudent(): void
    {
        if ($this->selectedClassStudent) {
            $studentName = $this->selectedClassStudent->student->user->name;

            $this->selectedClassStudent->update([
                'status' => 'quit',
                'left_at' => now(),
            ]);

            session()->flash('success', "{$studentName} has been unenrolled from the class.");

            $this->closeUnenrollConfirmModal();

            // Refresh the class data
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user',
            ]);
        }
    }

    public function closeUnenrollConfirmModal(): void
    {
        $this->showUnenrollConfirmModal = false;
        $this->selectedClassStudent = null;
    }

    public function markSessionAsOngoing($sessionId): void
    {
        $session = \App\Models\ClassSession::find($sessionId);
        if ($session && $session->isScheduled()) {
            $session->markAsOngoing();
            session()->flash('success', 'Session marked as ongoing.');
        }
    }

    public function openCompletionModal($sessionId): void
    {
        $this->completingSession = \App\Models\ClassSession::find($sessionId);
        if ($this->completingSession && ($this->completingSession->isScheduled() || $this->completingSession->isOngoing())) {
            $this->completionBookmark = $this->completingSession->bookmark ?? '';
            $this->showCompletionModal = true;
        }
    }

    public function closeCompletionModal(): void
    {
        $this->showCompletionModal = false;
        $this->completingSession = null;
        $this->completionBookmark = '';
    }

    public function completeSessionWithBookmark(): void
    {
        $this->validate([
            'completionBookmark' => 'required|string|min:3|max:500',
        ], [
            'completionBookmark.required' => 'Bookmark is required before completing the session.',
            'completionBookmark.min' => 'Bookmark must be at least 3 characters.',
            'completionBookmark.max' => 'Bookmark cannot exceed 500 characters.',
        ]);

        if ($this->completingSession && ($this->completingSession->isScheduled() || $this->completingSession->isOngoing())) {
            $this->completingSession->markCompleted($this->completionBookmark);
            session()->flash('success', 'Session completed with bookmark.');

            $this->closeCompletionModal();

            // Close session modal if it's open
            if ($this->showSessionModal) {
                $this->closeSessionModal();
            }

            // Refresh data
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user',
            ]);
        }
    }

    public function markSessionAsNoShow($sessionId): void
    {
        $session = \App\Models\ClassSession::find($sessionId);
        if ($session && ($session->isScheduled() || $session->isOngoing())) {
            $session->markAsNoShow('Student did not attend');
            session()->flash('success', 'Session marked as no-show.');
        }
    }

    public function markSessionAsCancelled($sessionId): void
    {
        $session = \App\Models\ClassSession::find($sessionId);
        if ($session && ($session->isScheduled() || $session->isOngoing())) {
            $session->cancel();
            session()->flash('success', 'Session cancelled.');
        }
    }

    public $showSessionModal = false;

    public $showCompletionModal = false;

    public $showAttendanceViewModal = false;

    public $currentSession = null;

    public $completingSession = null;

    public $viewingSession = null;

    public $completionBookmark = '';

    public function openSessionModal($sessionId): void
    {
        $this->currentSession = \App\Models\ClassSession::with(['attendances.student.user'])->find($sessionId);
        if ($this->currentSession && $this->currentSession->isOngoing()) {
            $this->bookmarkText = $this->currentSession->bookmark ?? '';
            $this->showSessionModal = true;
        }
    }

    public function closeSessionModal(): void
    {
        $this->showSessionModal = false;
        $this->currentSession = null;
    }

    public function openAttendanceViewModal($sessionId): void
    {
        $this->viewingSession = \App\Models\ClassSession::with(['attendances.student.user'])->find($sessionId);
        if ($this->viewingSession && $this->viewingSession->isCompleted()) {
            $this->showAttendanceViewModal = true;
        }
    }

    public function closeAttendanceViewModal(): void
    {
        $this->showAttendanceViewModal = false;
        $this->viewingSession = null;
    }

    public function updateStudentAttendance($studentId, $status): void
    {
        if (! $this->currentSession || ! $this->currentSession->isOngoing()) {
            return;
        }

        $success = $this->currentSession->updateStudentAttendance($studentId, $status);

        if ($success) {
            // Refresh the current session data
            $this->currentSession = \App\Models\ClassSession::with(['attendances.student.user'])->find($this->currentSession->id);

            // Refresh the class data to update statistics
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user',
            ]);

            session()->flash('success', 'Attendance updated successfully.');
        }
    }

    public $bookmarkText = '';

    public $editingBookmark = false;

    public function updateSessionBookmark(): void
    {
        if (! $this->currentSession || ! $this->currentSession->isOngoing()) {
            return;
        }

        $this->currentSession->updateBookmark($this->bookmarkText);

        // Refresh the current session data
        $this->currentSession = \App\Models\ClassSession::with(['attendances.student.user'])->find($this->currentSession->id);

        // Refresh the class data
        $this->class->refresh();
        $this->class->load([
            'course',
            'teacher.user',
            'sessions.attendances.student.user',
            'activeStudents.student.user',
        ]);

        session()->flash('success', 'Bookmark updated successfully.');
    }

    public function insertBookmarkTemplate($template): void
    {
        $this->bookmarkText = $template;
        $this->updateSessionBookmark();
    }

    public function setActiveTab($tab): void
    {
        $this->activeTab = $tab;

        // Update URL with tab query parameter
        $this->dispatch('update-url', ['tab' => $tab]);
    }

    // Payment Report Properties
    public int $paymentYear = 0;

    public string $paymentFilter = 'all';

    public string $paymentReportSearch = '';

    public int $paymentReportPerPage = 20;

    public string $paymentPicFilter = '';

    public string $paymentMethodTypeFilter = '';

    public array $visiblePaymentPeriods = [];

    private bool $skipPaymentPeriodDispatch = false;

    public bool $showPaymentColumnManager = false;

    // Manual Payment Modal Properties
    public bool $showManualPaymentModal = false;

    public $selectedStudent = null;

    public $selectedPeriod = null;

    public $selectedPeriodLabel = '';

    public $receiptFile = null;

    public $paymentAmount = 0;

    public $paymentNotes = '';

    public $paidAmountForPeriod = 0;

    public $expectedAmount = 0;

    // Edit Enrollment Modal Properties
    public bool $showEditEnrollmentModal = false;

    public $editingEnrollment = null;

    public $editEnrollmentDate = '';

    public $editEnrollmentFee = '';

    public $editPaymentMethodType = '';

    public $editNextPaymentDate = '';

    public $editOriginalPaymentMethodType = '';

    public $editOriginalEnrollmentFee = '';

    // Stripe billing info for automatic subscriptions
    public $editStripeBillingInfo = null;

    // Next billing date for editing automatic subscriptions
    public $editNextBillingDate = '';

    // Create First Enrollment Modal Properties
    public bool $showCreateFirstEnrollmentModal = false;

    public $creatingEnrollmentForStudentId = null;

    public $newEnrollmentDate = '';

    public $newEnrollmentFee = '';

    public $newPaymentMethodType = 'automatic';

    // Cancel Subscription Modal Properties
    public bool $showCancelSubscriptionModal = false;

    public $cancelingEnrollment = null;

    public $cancellationDate = '';

    public function mountPaymentReport()
    {
        $this->paymentYear = now()->year;
        // Don't initialize visible periods here - let Alpine.js handle it from localStorage
        // If no localStorage exists, it will be initialized by the computed property
    }

    public function initializeVisiblePaymentPeriods()
    {
        // Only initialize if visiblePaymentPeriods is empty (no localStorage loaded)
        if (empty($this->visiblePaymentPeriods)) {
            $periodColumns = $this->payment_period_columns;
            $this->visiblePaymentPeriods = $periodColumns->pluck('label')->toArray();
        }
    }

    public function togglePaymentPeriodVisibility($periodLabel)
    {
        if (in_array($periodLabel, $this->visiblePaymentPeriods)) {
            $this->visiblePaymentPeriods = array_values(array_diff($this->visiblePaymentPeriods, [$periodLabel]));
        } else {
            $this->visiblePaymentPeriods[] = $periodLabel;
        }

        // No need to dispatch manually - updatedVisiblePaymentPeriods() will handle it
    }

    public function toggleAllPaymentPeriods()
    {
        $periodColumns = $this->payment_period_columns;
        $allPeriods = $periodColumns->pluck('label')->toArray();

        if (count($this->visiblePaymentPeriods) === count($allPeriods)) {
            $this->visiblePaymentPeriods = [];
        } else {
            $this->visiblePaymentPeriods = $allPeriods;
        }

        // No need to dispatch manually - updatedVisiblePaymentPeriods() will handle it
    }

    public function setVisiblePaymentPeriods($periods)
    {
        // Skip dispatch when loading from localStorage
        $this->skipPaymentPeriodDispatch = true;
        $this->visiblePaymentPeriods = $periods;
        $this->skipPaymentPeriodDispatch = false;
    }

    public function updatedVisiblePaymentPeriods()
    {
        // Only dispatch event if not skipping (i.e., user action, not localStorage load)
        if (! $this->skipPaymentPeriodDispatch) {
            $this->dispatch('save-payment-column-preferences', visiblePeriods: $this->visiblePaymentPeriods);
        }
    }

    public function updatedPaymentYear()
    {
        // Reset pagination when year filter changes
        $this->resetPage();
        $this->initializeVisiblePaymentPeriods();
    }

    public function updatedPaymentFilter()
    {
        // Reset pagination when payment filter changes
        $this->resetPage();
    }

    public function updatedPaymentReportPerPage()
    {
        // Reset pagination when per page changes
        $this->resetPage();
    }

    public function updatedPaymentReportSearch()
    {
        // Reset pagination when search changes
        $this->resetPage();
    }

    public function updatedPaymentPicFilter()
    {
        // Reset pagination when PIC filter changes
        $this->resetPage();
    }

    public function updatedPaymentMethodTypeFilter()
    {
        // Reset pagination when payment method type filter changes
        $this->resetPage();
    }

    public function getClassStudentsForPaymentReportProperty()
    {
        $query = $this->class->activeStudents()
            ->with([
                'student.user.paymentMethods' => function ($q) {
                    $q->where('is_active', true);
                },
                'student.magicLinks' => function ($q) {
                    $q->valid()->latest()->limit(1);
                },
                'student.enrollments' => function ($q) {
                    $q->where('course_id', $this->class->course_id)
                        ->with('enrolledBy');
                },
            ]);

        // Apply payment filter - Filter by subscription status
        if ($this->paymentFilter !== 'all') {
            $query->whereHas('student.enrollments', function ($q) {
                $q->where('course_id', $this->class->course_id)
                    ->where('subscription_status', $this->paymentFilter);
            });
        }

        // Apply search filter
        if (! empty($this->paymentReportSearch)) {
            $query->whereHas('student', function ($q) {
                $q->whereHas('user', function ($userQuery) {
                    $userQuery->where('name', 'like', '%'.$this->paymentReportSearch.'%');
                })
                    ->orWhere('phone', 'like', '%'.$this->paymentReportSearch.'%')
                    ->orWhere('student_id', 'like', '%'.$this->paymentReportSearch.'%');
            });
        }

        // Apply PIC filter
        if (! empty($this->paymentPicFilter)) {
            $query->whereHas('student.enrollments', function ($q) {
                $q->where('course_id', $this->class->course_id)
                    ->where('enrolled_by', $this->paymentPicFilter);
            });
        }

        // Apply payment method type filter
        if (! empty($this->paymentMethodTypeFilter)) {
            $query->whereHas('student.enrollments', function ($q) {
                $q->where('course_id', $this->class->course_id)
                    ->where('payment_method_type', $this->paymentMethodTypeFilter);
            });
        }

        return $query->paginate($this->paymentReportPerPage);
    }

    public function getPaymentPeriodColumnsProperty()
    {
        $course = $this->class->course;

        if (! $course || ! $course->feeSettings) {
            // Default to monthly
            return collect(range(1, 12))->map(function ($month) {
                return [
                    'label' => \Carbon\Carbon::create()->month($month)->format('M'),
                    'period_start' => \Carbon\Carbon::create($this->paymentYear, $month, 1)->startOfMonth(),
                    'period_end' => \Carbon\Carbon::create($this->paymentYear, $month, 1)->endOfMonth(),
                ];
            });
        }

        $billingCycle = $course->feeSettings->billing_cycle;

        switch ($billingCycle) {
            case 'yearly':
                return collect([
                    [
                        'label' => (string) $this->paymentYear,
                        'period_start' => \Carbon\Carbon::create($this->paymentYear, 1, 1)->startOfYear(),
                        'period_end' => \Carbon\Carbon::create($this->paymentYear, 12, 31)->endOfYear(),
                    ],
                ]);

            case 'quarterly':
                return collect([
                    [
                        'label' => 'Q1',
                        'period_start' => \Carbon\Carbon::create($this->paymentYear, 1, 1)->startOfQuarter(),
                        'period_end' => \Carbon\Carbon::create($this->paymentYear, 3, 31)->endOfQuarter(),
                    ],
                    [
                        'label' => 'Q2',
                        'period_start' => \Carbon\Carbon::create($this->paymentYear, 4, 1)->startOfQuarter(),
                        'period_end' => \Carbon\Carbon::create($this->paymentYear, 6, 30)->endOfQuarter(),
                    ],
                    [
                        'label' => 'Q3',
                        'period_start' => \Carbon\Carbon::create($this->paymentYear, 7, 1)->startOfQuarter(),
                        'period_end' => \Carbon\Carbon::create($this->paymentYear, 9, 30)->endOfQuarter(),
                    ],
                    [
                        'label' => 'Q4',
                        'period_start' => \Carbon\Carbon::create($this->paymentYear, 10, 1)->startOfQuarter(),
                        'period_end' => \Carbon\Carbon::create($this->paymentYear, 12, 31)->endOfQuarter(),
                    ],
                ]);

            default: // monthly
                return collect(range(1, 12))->map(function ($month) {
                    return [
                        'label' => \Carbon\Carbon::create()->month($month)->format('M'),
                        'period_start' => \Carbon\Carbon::create($this->paymentYear, $month, 1)->startOfMonth(),
                        'period_end' => \Carbon\Carbon::create($this->paymentYear, $month, 1)->endOfMonth(),
                    ];
                });
        }
    }

    public function getAllPaymentPeriodColumnsProperty()
    {
        return $this->payment_period_columns;
    }

    public function getVisiblePaymentPeriodColumnsProperty()
    {
        $allPeriods = $this->payment_period_columns;

        return $allPeriods->filter(function ($period) {
            return in_array($period['label'], $this->visiblePaymentPeriods);
        });
    }

    public function getClassPaymentDataProperty()
    {
        $classStudents = $this->class_students_for_payment_report;
        $course = $this->class->course;
        $periodColumns = $this->payment_period_columns;

        // Get student IDs
        $studentIds = $classStudents->pluck('student.id')->toArray();

        // Query all orders for these students in this year
        $orders = \App\Models\Order::whereIn('student_id', $studentIds)
            ->where('course_id', $course->id)
            ->whereYear('period_start', $this->paymentYear)
            ->get();

        // Query first paid order date for each student (across all years) to determine billing start
        $firstPaidOrders = \App\Models\Order::whereIn('student_id', $studentIds)
            ->where('course_id', $course->id)
            ->where('status', \App\Models\Order::STATUS_PAID)
            ->selectRaw('student_id, MIN(period_start) as first_payment_date')
            ->groupBy('student_id')
            ->pluck('first_payment_date', 'student_id')
            ->map(fn ($date) => $date ? \Carbon\Carbon::parse($date) : null);

        // Query all document shipments for this class in the selected year
        $shipments = \App\Models\ClassDocumentShipment::where('class_id', $this->class->id)
            ->whereYear('period_start_date', $this->paymentYear)
            ->with(['items' => function ($query) use ($studentIds) {
                $query->whereIn('student_id', $studentIds);
            }])
            ->get();

        $paymentData = [];

        foreach ($classStudents as $classStudent) {
            $student = $classStudent->student;
            $studentOrders = $orders->where('student_id', $student->id);

            $paymentData[$student->id] = [];

            // Get first payment date for this student
            $firstPaymentDate = $firstPaidOrders[$student->id] ?? null;

            foreach ($periodColumns as $period) {
                $periodOrders = $studentOrders->filter(function ($order) use ($period) {
                    // Match orders where the billing period START date falls within the calendar month
                    // This ensures each monthly payment is counted only once in the correct month
                    return $order->period_start >= $period['period_start'] &&
                           $order->period_start <= $period['period_end'];
                });

                $paidOrders = $periodOrders->where('status', \App\Models\Order::STATUS_PAID);
                $pendingOrders = $periodOrders->where('status', \App\Models\Order::STATUS_PENDING);

                $enrollment = $student->enrollments()
                    ->where('course_id', $course->id)
                    ->whereIn('status', ['enrolled', 'active'])
                    ->first();

                $expectedAmount = $this->calculateExpectedAmountForStudent($enrollment, $period, $course, $firstPaymentDate);
                $paidAmount = $paidOrders->sum('amount');
                $pendingAmount = $pendingOrders->sum('amount');
                $unpaidAmount = max(0, $expectedAmount - $paidAmount);

                $status = $this->determinePaymentStatusForStudent($enrollment, $period, $paidAmount, $expectedAmount, $firstPaymentDate);

                // Find document shipment for this period and student
                $shipmentItem = null;
                $shipment = $shipments->first(function ($s) use ($period) {
                    return $s->period_start_date >= $period['period_start']->toDateString() &&
                           $s->period_start_date <= $period['period_end']->toDateString();
                });

                if ($shipment) {
                    $shipmentItem = $shipment->items->first(function ($item) use ($student) {
                        return $item->student_id === $student->id;
                    });
                }

                $paymentData[$student->id][$period['label']] = [
                    'orders' => $periodOrders,
                    'paid_orders' => $paidOrders,
                    'pending_orders' => $pendingOrders,
                    'total_amount' => $periodOrders->sum('amount'),
                    'paid_amount' => $paidAmount,
                    'pending_amount' => $pendingAmount,
                    'expected_amount' => $expectedAmount,
                    'unpaid_amount' => $unpaidAmount,
                    'count' => $periodOrders->count(),
                    'enrollment' => $enrollment,
                    'status' => $status,
                    'shipment' => $shipment,
                    'shipment_item' => $shipmentItem,
                ];
            }
        }

        return $paymentData;
    }

    private function calculateExpectedAmountForStudent($enrollment, $period, $course, $firstPaymentDate = null)
    {
        if (! $enrollment) {
            return 0;
        }

        // Get enrollment date from the enrollment record
        $enrollmentDate = $enrollment->start_date ?: $enrollment->enrollment_date;

        // Use the OLDER date between enrollment date and first payment date
        // This handles cases where payment might exist before enrollment (data migration, pre-payments)
        // or enrollment happens before first payment (normal case)
        $billingStartDate = $enrollmentDate;
        if ($firstPaymentDate && $enrollmentDate) {
            $billingStartDate = $firstPaymentDate < $enrollmentDate ? $firstPaymentDate : $enrollmentDate;
        } elseif ($firstPaymentDate) {
            $billingStartDate = $firstPaymentDate;
        }

        $periodStart = $period['period_start'];
        $periodEnd = $period['period_end'];

        if ($billingStartDate && $billingStartDate > $periodEnd) {
            return 0;
        }

        if ($enrollment->subscription_cancel_at && $enrollment->subscription_cancel_at <= $periodStart) {
            return 0;
        }

        if (in_array($enrollment->academic_status?->value, ['withdrawn', 'suspended'])) {
            return 0;
        }

        // Use enrollment-specific fee instead of course fee
        // This allows for different pricing per student (discounts, promotions, etc.)
        if ($enrollment->enrollment_fee) {
            return $enrollment->enrollment_fee;
        }

        // Fallback to course fee if enrollment fee not set
        if ($course->feeSettings) {
            return $course->feeSettings->fee_amount;
        }

        return 0;
    }

    private function determinePaymentStatusForStudent($enrollment, $period, $paidAmount, $expectedAmount, $firstPaymentDate = null)
    {
        if (! $enrollment) {
            return 'no_enrollment';
        }

        // Get enrollment date from the enrollment record
        $enrollmentDate = $enrollment->start_date ?: $enrollment->enrollment_date;

        // Use the OLDER date between enrollment date and first payment date
        // This handles cases where payment might exist before enrollment (data migration, pre-payments)
        // or enrollment happens before first payment (normal case)
        $billingStartDate = $enrollmentDate;
        if ($firstPaymentDate && $enrollmentDate) {
            $billingStartDate = $firstPaymentDate < $enrollmentDate ? $firstPaymentDate : $enrollmentDate;
        } elseif ($firstPaymentDate) {
            $billingStartDate = $firstPaymentDate;
        }

        $periodStart = $period['period_start'];
        $periodEnd = $period['period_end'];

        if ($billingStartDate && $billingStartDate > $periodEnd) {
            return 'not_started';
        }

        // PRIORITY 1: Check actual payment status FIRST (actual money received takes priority)
        if ($expectedAmount > 0) {
            if ($paidAmount >= $expectedAmount) {
                return 'paid';
            }

            if ($paidAmount > 0) {
                return 'partial_payment';
            }
        }

        // PRIORITY 2: Check if subscription was cancelled (only if no payment made)
        if ($enrollment->subscription_cancel_at) {
            if ($enrollment->subscription_cancel_at >= $periodStart && $enrollment->subscription_cancel_at <= $periodEnd) {
                return 'cancelled_this_period';
            } elseif ($enrollment->subscription_cancel_at < $periodStart) {
                return 'cancelled_before';
            }
        }

        // PRIORITY 3: Check academic status
        if ($enrollment->academic_status) {
            switch ($enrollment->academic_status->value) {
                case 'withdrawn':
                    return 'withdrawn';
                case 'suspended':
                    return 'suspended';
                case 'completed':
                    return 'completed';
            }
        }

        // PRIORITY 4: Check if payment is expected
        if ($expectedAmount <= 0) {
            return 'no_payment_due';
        }

        // PRIORITY 5: Check if period hasn't started yet (future months)
        if ($periodStart > now()) {
            // If enrollment is active, show as pending payment (will be collected)
            if ($enrollment->subscription_status === 'active') {
                return 'pending_payment';
            }

            // Otherwise, show as not started (won't be collected)
            return 'not_started';
        }

        // PRIORITY 6: Default to unpaid if period has started
        return 'unpaid';
    }

    private function hasConsecutiveUnpaidMonths($studentId, $consecutiveCount = 2)
    {
        $paymentData = $this->class_payment_data[$studentId] ?? [];
        $periodColumns = $this->payment_period_columns;

        $consecutiveUnpaid = 0;

        foreach ($periodColumns as $period) {
            $payment = $paymentData[$period['label']] ?? ['status' => 'no_data'];
            $status = $payment['status'];

            // Check if the status is unpaid or partial_payment for active students
            if (in_array($status, ['unpaid', 'partial_payment'])) {
                $consecutiveUnpaid++;

                // If we reached the threshold, return true
                if ($consecutiveUnpaid >= $consecutiveCount) {
                    return true;
                }
            } else {
                // Reset counter if the status is not unpaid/partial
                // But only if the period has started (not future periods)
                if (! in_array($status, ['not_started', 'no_enrollment'])) {
                    $consecutiveUnpaid = 0;
                }
            }
        }

        return false;
    }

    public function openManualPaymentModal($studentId, $periodLabel, $periodStart, $periodEnd, $unpaidAmount, $paidAmount = 0, $expectedAmount = 0)
    {
        $this->selectedStudent = $studentId;
        $this->selectedPeriod = [
            'label' => $periodLabel,
            'start' => $periodStart,
            'end' => $periodEnd,
        ];
        $this->selectedPeriodLabel = $periodLabel;
        $this->paidAmountForPeriod = $paidAmount;
        $this->expectedAmount = $expectedAmount;

        // If unpaid amount is 0, use enrollment fee as default
        if ($unpaidAmount <= 0) {
            $student = \App\Models\Student::find($studentId);
            $enrollment = $student?->enrollments->first();

            // Use enrollment fee if available, otherwise use course fee
            if ($enrollment && $enrollment->enrollment_fee > 0) {
                $this->paymentAmount = $enrollment->enrollment_fee;
                $this->expectedAmount = $enrollment->enrollment_fee;
            } elseif ($enrollment && $this->class->course->feeSettings) {
                $this->paymentAmount = $this->class->course->feeSettings->fee_amount;
                $this->expectedAmount = $this->class->course->feeSettings->fee_amount;
            } else {
                $this->paymentAmount = 0;
            }
        } else {
            $this->paymentAmount = $unpaidAmount;
        }
        $this->paymentNotes = '';
        $this->receiptFile = null;
        $this->showManualPaymentModal = true;
    }

    public function closeManualPaymentModal()
    {
        $this->showManualPaymentModal = false;
        $this->selectedStudent = null;
        $this->selectedPeriod = null;
        $this->selectedPeriodLabel = '';
        $this->paymentAmount = 0;
        $this->paymentNotes = '';
        $this->receiptFile = null;
        $this->paidAmountForPeriod = 0;
        $this->expectedAmount = 0;
    }

    public function updatedReceiptFile()
    {
        $this->validate([
            'receiptFile' => 'file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240', // 10MB Max - Images and PDF
        ]);
    }

    public function createManualPayment()
    {
        $this->validate([
            'paymentAmount' => 'required|numeric|min:0.01',
            'receiptFile' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240',
        ]);

        try {
            // Get student and enrollment
            $student = \App\Models\Student::find($this->selectedStudent);
            $enrollment = $student->enrollments()
                ->where('course_id', $this->class->course_id)
                ->first();

            if (! $enrollment) {
                $this->addError('general', 'Enrollment not found for this student.');

                return;
            }

            // Handle receipt file upload
            $receiptUrl = null;
            if ($this->receiptFile) {
                $receiptPath = $this->receiptFile->store('receipts', 'public');
                $receiptUrl = \Storage::url($receiptPath);
            }

            // Check if order already exists for this period
            $existingOrder = \App\Models\Order::where('enrollment_id', $enrollment->id)
                ->where('student_id', $student->id)
                ->where('course_id', $this->class->course_id)
                ->where('period_start', $this->selectedPeriod['start'])
                ->where('period_end', $this->selectedPeriod['end'])
                ->first();

            if ($existingOrder) {
                // Update existing order
                $existingOrder->update([
                    'amount' => $this->paymentAmount,
                    'status' => \App\Models\Order::STATUS_PAID,
                    'payment_method' => \App\Models\Order::PAYMENT_METHOD_MANUAL,
                    'paid_at' => now(),
                    'receipt_url' => $receiptUrl ?? $existingOrder->receipt_url,
                    'metadata' => array_merge($existingOrder->metadata ?? [], [
                        'notes' => $this->paymentNotes,
                        'manual_payment' => true,
                        'processed_by' => auth()->id(),
                        'processed_at' => now()->toDateTimeString(),
                    ]),
                ]);

                session()->flash('message', 'Payment updated successfully!');
            } else {
                // Create new order
                \App\Models\Order::create([
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $student->id,
                    'course_id' => $this->class->course_id,
                    'amount' => $this->paymentAmount,
                    'currency' => 'MYR',
                    'status' => \App\Models\Order::STATUS_PAID,
                    'period_start' => $this->selectedPeriod['start'],
                    'period_end' => $this->selectedPeriod['end'],
                    'billing_reason' => \App\Models\Order::REASON_MANUAL,
                    'payment_method' => \App\Models\Order::PAYMENT_METHOD_MANUAL,
                    'paid_at' => now(),
                    'receipt_url' => $receiptUrl,
                    'metadata' => [
                        'notes' => $this->paymentNotes,
                        'manual_payment' => true,
                        'processed_by' => auth()->id(),
                        'processed_at' => now()->toDateTimeString(),
                    ],
                ]);

                session()->flash('message', 'Payment recorded successfully!');
            }

            $this->closeManualPaymentModal();
        } catch (\Exception $e) {
            $this->addError('general', 'Failed to create payment: '.$e->getMessage());
        }
    }

    // Edit Enrollment Methods
    public function openEditEnrollmentModal($enrollmentId)
    {
        $this->editingEnrollment = \App\Models\Enrollment::find($enrollmentId);

        if ($this->editingEnrollment) {
            $this->editEnrollmentDate = $this->editingEnrollment->enrollment_date->format('Y-m-d');
            $this->editEnrollmentFee = $this->editingEnrollment->enrollment_fee ?? '';
            $this->editOriginalEnrollmentFee = $this->editEnrollmentFee;
            $this->editPaymentMethodType = $this->editingEnrollment->payment_method_type ?? 'automatic';
            $this->editOriginalPaymentMethodType = $this->editPaymentMethodType;
            // Set default next payment date to tomorrow
            $this->editNextPaymentDate = now()->addDay()->format('Y-m-d');

            // Fetch Stripe billing info for automatic subscriptions
            $this->editStripeBillingInfo = null;
            $this->editNextBillingDate = '';
            if ($this->editingEnrollment->stripe_subscription_id && $this->editingEnrollment->payment_method_type === 'automatic') {
                $this->fetchStripeBillingInfo();

                // Initialize next billing date from Stripe data
                if ($this->editStripeBillingInfo) {
                    // Use trial_end if in trial, otherwise use current_period_end
                    $nextBillingDate = $this->editStripeBillingInfo['trial_end'] ?? $this->editStripeBillingInfo['current_period_end'];
                    $this->editNextBillingDate = $nextBillingDate?->format('Y-m-d') ?? '';
                }
            }

            $this->showEditEnrollmentModal = true;
        }
    }

    protected function fetchStripeBillingInfo()
    {
        try {
            $stripeService = app(\App\Services\StripeService::class);
            $subscription = $stripeService->getStripe()->subscriptions->retrieve(
                $this->editingEnrollment->stripe_subscription_id,
                ['expand' => ['latest_invoice']]
            );

            $trialEnd = $subscription->trial_end ? \Carbon\Carbon::createFromTimestamp($subscription->trial_end) : null;
            $currentPeriodEnd = $subscription->current_period_end ? \Carbon\Carbon::createFromTimestamp($subscription->current_period_end) : null;

            $this->editStripeBillingInfo = [
                'status' => $subscription->status,
                'current_period_start' => $subscription->current_period_start ? \Carbon\Carbon::createFromTimestamp($subscription->current_period_start) : null,
                'current_period_end' => $currentPeriodEnd,
                'trial_end' => $trialEnd,
                'cancel_at' => $subscription->cancel_at ? \Carbon\Carbon::createFromTimestamp($subscription->cancel_at) : null,
                'canceled_at' => $subscription->canceled_at ? \Carbon\Carbon::createFromTimestamp($subscription->canceled_at) : null,
            ];

            // Sync the next payment date to the enrollment for display in tables
            $nextPaymentDate = $trialEnd ?? $currentPeriodEnd;
            if ($nextPaymentDate && $this->editingEnrollment->next_payment_date?->format('Y-m-d') !== $nextPaymentDate->format('Y-m-d')) {
                $this->editingEnrollment->update([
                    'next_payment_date' => $nextPaymentDate,
                    'trial_end_at' => $trialEnd,
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to fetch Stripe billing info', [
                'enrollment_id' => $this->editingEnrollment->id,
                'subscription_id' => $this->editingEnrollment->stripe_subscription_id,
                'error' => $e->getMessage(),
            ]);
            $this->editStripeBillingInfo = null;
        }
    }

    public function closeEditEnrollmentModal()
    {
        $this->showEditEnrollmentModal = false;
        $this->editingEnrollment = null;
        $this->editEnrollmentDate = '';
        $this->editEnrollmentFee = '';
        $this->editOriginalEnrollmentFee = '';
        $this->editPaymentMethodType = '';
        $this->editNextPaymentDate = '';
        $this->editOriginalPaymentMethodType = '';
        $this->editStripeBillingInfo = null;
        $this->editNextBillingDate = '';
    }

    public function updateEnrollment()
    {
        $rules = [
            'editEnrollmentDate' => 'required|date',
            'editEnrollmentFee' => 'required|numeric|min:0',
            'editPaymentMethodType' => 'required|in:automatic,manual',
        ];

        // If switching from manual to automatic, require next payment date
        $isSwitchingToAutomatic = $this->editOriginalPaymentMethodType === 'manual'
            && $this->editPaymentMethodType === 'automatic';

        if ($isSwitchingToAutomatic) {
            $rules['editNextPaymentDate'] = 'required|date|after:today';
        }

        // If editing next billing date for automatic subscription
        $isEditingBillingDate = $this->editPaymentMethodType === 'automatic'
            && !empty($this->editNextBillingDate)
            && $this->editStripeBillingInfo
            && !$isSwitchingToAutomatic;

        if ($isEditingBillingDate) {
            $rules['editNextBillingDate'] = 'required|date|after:today';
        }

        $this->validate($rules);

        try {
            if ($this->editingEnrollment) {
                // Check if enrollment fee changed for Stripe update
                $feeChanged = abs((float) $this->editEnrollmentFee - (float) $this->editOriginalEnrollmentFee) >= 0.01;
                $hasStripeSubscription = !empty($this->editingEnrollment->stripe_subscription_id);

                // Update basic enrollment info
                $this->editingEnrollment->update([
                    'enrollment_date' => $this->editEnrollmentDate,
                    'enrollment_fee' => $this->editEnrollmentFee,
                    'payment_method_type' => $this->editPaymentMethodType,
                ]);

                // If fee changed and has Stripe subscription, update the subscription price
                $stripeService = app(\App\Services\StripeService::class);

                if ($feeChanged && $hasStripeSubscription) {
                    $stripeService->updateSubscriptionFee($this->editingEnrollment, (float) $this->editEnrollmentFee);

                    \Log::info('Updated Stripe subscription price due to enrollment fee change', [
                        'enrollment_id' => $this->editingEnrollment->id,
                        'old_fee' => $this->editOriginalEnrollmentFee,
                        'new_fee' => $this->editEnrollmentFee,
                    ]);
                }

                // If switching to automatic, create subscription with next payment date
                if ($isSwitchingToAutomatic) {
                    $this->createAutomaticSubscription();
                }

                // If editing next billing date, update Stripe subscription schedule
                $billingDateChanged = false;
                if ($isEditingBillingDate && $hasStripeSubscription) {
                    // Get original next billing date from Stripe info
                    $originalNextBillingDate = $this->editStripeBillingInfo['trial_end'] ?? $this->editStripeBillingInfo['current_period_end'];
                    $originalDateString = $originalNextBillingDate?->format('Y-m-d') ?? '';

                    // Check if the billing date actually changed
                    if ($this->editNextBillingDate !== $originalDateString) {
                        $billingDateChanged = true;

                        // Parse the new billing date with time (use 7:23 AM Malaysia time for consistency)
                        $timezone = 'Asia/Kuala_Lumpur';
                        $newBillingDateTime = \Carbon\Carbon::parse($this->editNextBillingDate . ' 07:23:00', $timezone);

                        // Update subscription schedule in Stripe
                        $stripeService->updateSubscriptionSchedule(
                            $this->editingEnrollment->stripe_subscription_id,
                            ['next_payment_date' => $newBillingDateTime->timestamp]
                        );

                        // Update the next payment date in enrollment
                        $this->editingEnrollment->update([
                            'next_payment_date' => $newBillingDateTime,
                        ]);

                        \Log::info('Updated next billing date via admin edit', [
                            'enrollment_id' => $this->editingEnrollment->id,
                            'subscription_id' => $this->editingEnrollment->stripe_subscription_id,
                            'old_billing_date' => $originalDateString,
                            'new_billing_date' => $this->editNextBillingDate,
                        ]);
                    }
                }

                // Build success message
                $message = 'Enrollment updated successfully!';
                if ($feeChanged && $hasStripeSubscription && $billingDateChanged) {
                    $message = 'Enrollment, subscription price, and next billing date updated successfully!';
                } elseif ($feeChanged && $hasStripeSubscription) {
                    $message = 'Enrollment and Stripe subscription price updated successfully!';
                } elseif ($billingDateChanged) {
                    $message = 'Enrollment and next billing date updated successfully!';
                }

                session()->flash('message', $message);
                $this->closeEditEnrollmentModal();
            }
        } catch (\Exception $e) {
            $this->addError('general', 'Failed to update enrollment: '.$e->getMessage());
        }
    }

    protected function createAutomaticSubscription()
    {
        $enrollment = $this->editingEnrollment;
        $student = $enrollment->student;
        $user = $student->user;

        // Check if student has a payment method
        $paymentMethod = $user->paymentMethods()->active()->default()->first()
            ?? $user->paymentMethods()->active()->first();

        if (! $paymentMethod) {
            throw new \Exception('Student must have an active payment method before switching to automatic payments.');
        }

        // Check if course has Stripe price ID
        if (! $enrollment->course->feeSettings?->stripe_price_id) {
            throw new \Exception('Course must be synced with Stripe first. Go to Course Edit page and click "Sync to Stripe".');
        }

        $stripeService = app(\App\Services\StripeService::class);

        // Create subscription with the specified next payment date
        $result = $stripeService->createSubscriptionWithOptions($enrollment, $paymentMethod, [
            'start_date' => $this->editNextPaymentDate,
            'start_time' => '07:23',
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

        // Update enrollment with subscription details
        $enrollment->update([
            'stripe_subscription_id' => $result['subscription']->id,
            'subscription_status' => 'active',
            'manual_payment_required' => false,
        ]);

        \Log::info('Switched enrollment to automatic payment with scheduled start date', [
            'enrollment_id' => $enrollment->id,
            'subscription_id' => $result['subscription']->id,
            'next_payment_date' => $this->editNextPaymentDate,
        ]);
    }

    // Activate Enrollment Method
    public function activateEnrollment($enrollmentId)
    {
        try {
            $enrollment = \App\Models\Enrollment::find($enrollmentId);

            if ($enrollment) {
                $enrollment->update([
                    'subscription_status' => 'active',
                    'subscription_cancel_at' => null, // Clear cancellation date when reactivating
                ]);

                session()->flash('message', 'Enrollment activated successfully!');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to activate enrollment: '.$e->getMessage());
        }
    }

    // Generate Magic Link for Student
    public function generateMagicLinkForStudent($studentId, $forceRegenerate = false)
    {
        try {
            $student = \App\Models\Student::find($studentId);

            if (! $student) {
                session()->flash('error', 'Student not found.');

                return;
            }

            // Check if a valid magic link already exists
            $existingToken = $student->magicLinks()->valid()->first();

            if ($existingToken && !$forceRegenerate) {
                // Don't regenerate, just refresh the page to show the existing link
                session()->flash('message', 'A valid magic link already exists for this student. Click the copy button to copy it.');
                return;
            }

            $token = \App\Models\PaymentMethodToken::generateForStudent($student);

            \Log::info('Admin generated magic link for student from payment reports', [
                'admin_id' => auth()->user()->id,
                'admin_name' => auth()->user()->name,
                'student_id' => $student->id,
                'student_name' => $student->user->name,
                'token_id' => $token->id,
                'expires_at' => $token->expires_at->toIso8601String(),
            ]);

            session()->flash('message', 'Magic link generated! Click the copy button to copy the link.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to generate magic link: '.$e->getMessage());
        }
    }

    // Force regenerate Magic Link for Student (invalidates existing links)
    public function regenerateMagicLinkForStudent($studentId)
    {
        $this->generateMagicLinkForStudent($studentId, true);
    }

    // Get Magic Link URL for Student
    public function getMagicLinkUrl($studentId): ?string
    {
        $student = \App\Models\Student::find($studentId);

        if (! $student) {
            return null;
        }

        $token = $student->magicLinks()->valid()->latest()->first();

        return $token?->getMagicLinkUrl();
    }

    // Create First Enrollment Methods
    public function openCreateFirstEnrollmentModal($studentId)
    {
        $this->creatingEnrollmentForStudentId = $studentId;
        $this->newEnrollmentDate = now()->format('Y-m-d');
        $this->newEnrollmentFee = $this->class->course->feeSettings->monthly_fee ?? '';
        $this->newPaymentMethodType = 'automatic';
        $this->showCreateFirstEnrollmentModal = true;
    }

    public function closeCreateFirstEnrollmentModal()
    {
        $this->showCreateFirstEnrollmentModal = false;
        $this->creatingEnrollmentForStudentId = null;
        $this->newEnrollmentDate = '';
        $this->newEnrollmentFee = '';
        $this->newPaymentMethodType = 'automatic';
    }

    public function createFirstEnrollment()
    {
        $this->validate([
            'newEnrollmentDate' => 'required|date',
            'newEnrollmentFee' => 'required|numeric|min:0',
            'newPaymentMethodType' => 'required|in:automatic,manual',
        ]);

        try {
            $student = \App\Models\Student::find($this->creatingEnrollmentForStudentId);

            if (! $student) {
                throw new \Exception('Student not found');
            }

            // Create the enrollment
            $enrollment = \App\Models\Enrollment::create([
                'student_id' => $student->id,
                'course_id' => $this->class->course_id,
                'enrolled_by' => auth()->id(),
                'status' => 'enrolled',
                'academic_status' => \App\AcademicStatus::ACTIVE,
                'enrollment_date' => $this->newEnrollmentDate,
                'start_date' => $this->newEnrollmentDate,
                'enrollment_fee' => $this->newEnrollmentFee,
                'payment_method_type' => $this->newPaymentMethodType,
                'subscription_status' => 'active',
                'stripe_subscription_id' => 'INTERNAL-'.\Illuminate\Support\Str::uuid(),
            ]);

            session()->flash('message', 'First enrollment created successfully for '.$student->user->name.'!');
            $this->closeCreateFirstEnrollmentModal();

            // Refresh the class to show updated data
            $this->class->refresh();
        } catch (\Exception $e) {
            $this->addError('general', 'Failed to create enrollment: '.$e->getMessage());
        }
    }

    // Cancel Subscription Methods
    public function openCancelSubscriptionModal($enrollmentId)
    {
        $this->cancelingEnrollment = \App\Models\Enrollment::find($enrollmentId);

        if ($this->cancelingEnrollment) {
            // Default to today's date
            $this->cancellationDate = now()->format('Y-m-d');
            $this->showCancelSubscriptionModal = true;
        }
    }

    public function closeCancelSubscriptionModal()
    {
        $this->showCancelSubscriptionModal = false;
        $this->cancelingEnrollment = null;
        $this->cancellationDate = '';
    }

    public function cancelSubscription()
    {
        $this->validate([
            'cancellationDate' => 'required|date',
        ]);

        try {
            if ($this->cancelingEnrollment) {
                $cancellationDate = \Carbon\Carbon::parse($this->cancellationDate);

                // Check if enrollment has a subscription
                if (empty($this->cancelingEnrollment->stripe_subscription_id)) {
                    // For active enrollments without subscriptions (generating orders but no subscription yet)
                    $this->cancelingEnrollment->update([
                        'subscription_cancel_at' => $cancellationDate,
                        'subscription_status' => 'canceled',
                    ]);

                    session()->flash('message', 'Enrollment cancelled successfully!');
                } elseif ($this->cancelingEnrollment->isStripeSubscription()) {
                    // For Stripe subscriptions, use StripeService to properly cancel
                    $stripeService = app(StripeService::class);

                    // Determine if we should cancel immediately or at period end
                    $cancelImmediately = $cancellationDate->isToday() || $cancellationDate->isPast();

                    $result = $stripeService->cancelSubscription(
                        $this->cancelingEnrollment->stripe_subscription_id,
                        $cancelImmediately
                    );

                    // Update enrollment based on Stripe response
                    if ($result['immediately']) {
                        $this->cancelingEnrollment->update([
                            'subscription_status' => 'canceled',
                            'subscription_cancel_at' => now(),
                        ]);
                    } else {
                        $this->cancelingEnrollment->update([
                            'subscription_cancel_at' => $cancellationDate,
                        ]);
                    }

                    session()->flash('message', 'Stripe subscription cancelled successfully! '.$result['message']);
                } else {
                    // For Internal/Manual subscriptions, just update the database
                    $this->cancelingEnrollment->update([
                        'subscription_cancel_at' => $cancellationDate,
                        'subscription_status' => 'canceled',
                    ]);

                    session()->flash('message', 'Manual subscription cancelled successfully!');
                }

                $this->closeCancelSubscriptionModal();

                // Refresh the class data
                $this->class->refresh();
            }
        } catch (\Exception $e) {
            $this->addError('general', 'Failed to cancel subscription: '.$e->getMessage());
        }
    }

    public function getAvailablePicsProperty()
    {
        // Get all unique PICs from enrollments for this class
        return $this->class->activeStudents()
            ->with([
                'student.enrollments' => function ($q) {
                    $q->where('course_id', $this->class->course_id)
                        ->with('enrolledBy');
                },
            ])
            ->get()
            ->pluck('student.enrollments')
            ->flatten()
            ->pluck('enrolledBy')
            ->filter(fn ($user) => $user !== null)
            ->filter(fn ($user) => ! is_null($user->name))
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    public function updatedEnrolledStudentSearch()
    {
        // Reset pagination when search term changes
        $this->resetPage();
    }

    public function getFilteredEnrolledStudentsProperty()
    {
        $query = $this->class->activeStudents()
            ->with(['student.user']);

        // Apply search filter
        if (! empty($this->enrolledStudentSearch)) {
            $query->whereHas('student', function ($q) {
                $q->whereHas('user', function ($userQuery) {
                    $userQuery->where('name', 'like', '%'.$this->enrolledStudentSearch.'%')
                        ->orWhere('email', 'like', '%'.$this->enrolledStudentSearch.'%');
                })
                    ->orWhere('phone', 'like', '%'.$this->enrolledStudentSearch.'%');
            });
        }

        return $query->paginate($this->studentsPerPage);
    }

    public function getRemainingCapacityProperty(): ?int
    {
        if (! $this->class->max_capacity) {
            return null;
        }

        return $this->class->max_capacity - $this->class->activeStudents()->count();
    }

    public function getCapacityWarningProperty(): ?string
    {
        $remaining = $this->remaining_capacity;

        if ($remaining === null) {
            return null;
        }

        if ($remaining <= 0) {
            return 'Class is at full capacity';
        }

        if ($remaining <= 3) {
            return "Only {$remaining} spot(s) remaining";
        }

        return null;
    }

    // Monthly Calendar Properties and Methods
    public $currentMonth;

    public $currentYear;

    public function initializeCalendar(): void
    {
        if (! $this->currentMonth) {
            $this->currentMonth = now()->month;
        }
        if (! $this->currentYear) {
            $this->currentYear = now()->year;
        }
    }

    public function previousMonth(): void
    {
        $this->initializeCalendar();
        $this->currentMonth--;
        if ($this->currentMonth < 1) {
            $this->currentMonth = 12;
            $this->currentYear--;
        }
    }

    public function nextMonth(): void
    {
        $this->initializeCalendar();
        $this->currentMonth++;
        if ($this->currentMonth > 12) {
            $this->currentMonth = 1;
            $this->currentYear++;
        }
    }

    public function getCurrentMonthNameProperty(): string
    {
        $this->initializeCalendar();

        return \Carbon\Carbon::create($this->currentYear, $this->currentMonth, 1)->format('F Y');
    }

    public function getMonthlyCalendarDataProperty(): array
    {
        $this->initializeCalendar();

        $firstDayOfMonth = \Carbon\Carbon::create($this->currentYear, $this->currentMonth, 1);
        $lastDayOfMonth = $firstDayOfMonth->copy()->endOfMonth();

        // Start from Monday of the week containing the first day
        $startDate = $firstDayOfMonth->copy()->startOfWeek(\Carbon\Carbon::MONDAY);

        // End on Sunday of the week containing the last day
        $endDate = $lastDayOfMonth->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);

        $calendar = [];
        $currentDate = $startDate->copy();

        // Get all sessions for the month range
        $sessions = $this->class->sessions()
            ->whereBetween('session_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->groupBy('session_date');

        while ($currentDate <= $endDate) {
            $dateString = $currentDate->toDateString();
            $calendar[] = [
                'date' => $currentDate->copy(),
                'isCurrentMonth' => $currentDate->month === $this->currentMonth,
                'isToday' => $currentDate->isToday(),
                'sessions' => $sessions->get($dateString, collect()),
            ];
            $currentDate->addDay();
        }

        return $calendar;
    }

    // Shipment Management Methods
    public bool $showShipmentModal = false;

    public int $shipmentYear = 0;

    public int $shipmentMonth = 0;

    public function mountShipmentModal()
    {
        $this->shipmentYear = now()->year;
        $this->shipmentMonth = now()->month;
    }

    public function openShipmentModal(): void
    {
        $this->mountShipmentModal();
        $this->showShipmentModal = true;
    }

    public function closeShipmentModal(): void
    {
        $this->showShipmentModal = false;
    }

    public function generateShipment(): void
    {
        $periodStart = \Carbon\Carbon::create($this->shipmentYear, $this->shipmentMonth, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $periodLabel = $periodStart->format('F Y');

        // Check if shipment already exists for this period
        $existingShipment = $this->class->documentShipments()
            ->where('period_start_date', $periodStart->toDateString())
            ->where('period_end_date', $periodEnd->toDateString())
            ->first();

        if ($existingShipment) {
            // Update existing shipment with new subscribed students
            $result = \App\Models\ClassDocumentShipment::updateShipmentStudents($existingShipment, $this->class);

            if ($result['success']) {
                $message = "Shipment for {$periodLabel} updated successfully! ";
                if ($result['added'] > 0) {
                    $message .= "{$result['added']} student(s) added. ";
                }
                if ($result['removed'] > 0) {
                    $message .= "{$result['removed']} student(s) removed (subscription inactive). ";
                }
                if ($result['added'] === 0 && $result['removed'] === 0) {
                    $message = "Shipment for {$periodLabel} is already up to date.";
                }

                session()->flash('success', $message);
                $this->class->refresh();
                $this->closeShipmentModal();
            } else {
                session()->flash('error', $result['message'] ?? 'Failed to update shipment.');
            }
        } else {
            // Create new shipment
            $shipment = $this->class->generateShipmentForPeriod($periodStart, $periodEnd);

            if ($shipment) {
                session()->flash('success', "Shipment generated successfully for {$periodLabel}! {$shipment->total_recipients} student(s) included.");
                $this->class->refresh();
                $this->closeShipmentModal();
            } else {
                session()->flash('error', 'Failed to generate shipment. No students with active subscriptions found or configuration is incomplete.');
            }
        }
    }

    public function generateShipmentForCurrentMonth(): void
    {
        $this->openShipmentModal();
    }

    public function markShipmentAsProcessing($shipmentId): void
    {
        $shipment = \App\Models\ClassDocumentShipment::findOrFail($shipmentId);
        $shipment->markAsProcessing();
        session()->flash('success', 'Shipment marked as processing.');
        $this->class->refresh();
    }

    public function markShipmentAsShipped($shipmentId): void
    {
        $shipment = \App\Models\ClassDocumentShipment::findOrFail($shipmentId);
        $shipment->markAsShipped();
        session()->flash('success', 'Shipment marked as shipped.');
        $this->class->refresh();
    }

    public function markShipmentAsDelivered($shipmentId): void
    {
        $shipment = \App\Models\ClassDocumentShipment::findOrFail($shipmentId);
        $shipment->markAsDelivered();
        session()->flash('success', 'Shipment marked as delivered.');
        $this->class->refresh();
    }

    public function toggleSelectAll(): void
    {
        $filteredShipments = $this->getFilteredShipmentsProperty();

        if (count($this->selectedShipmentIds) === $filteredShipments->count()) {
            $this->selectedShipmentIds = [];
        } else {
            $this->selectedShipmentIds = $filteredShipments->pluck('id')->toArray();
        }
    }

    public function bulkMarkAsProcessing(): void
    {
        if (empty($this->selectedShipmentIds)) {
            session()->flash('error', 'Please select at least one shipment.');

            return;
        }

        $count = 0;
        foreach ($this->selectedShipmentIds as $shipmentId) {
            $shipment = \App\Models\ClassDocumentShipment::find($shipmentId);
            if ($shipment && $shipment->status === 'pending') {
                $shipment->markAsProcessing();
                $count++;
            }
        }

        session()->flash('success', "Successfully marked {$count} shipment(s) as processing.");
        $this->selectedShipmentIds = [];
        $this->class->refresh();
    }

    public function bulkMarkAsShipped(): void
    {
        if (empty($this->selectedShipmentIds)) {
            session()->flash('error', 'Please select at least one shipment.');

            return;
        }

        $count = 0;
        foreach ($this->selectedShipmentIds as $shipmentId) {
            $shipment = \App\Models\ClassDocumentShipment::find($shipmentId);
            if ($shipment && $shipment->status === 'processing') {
                $shipment->markAsShipped();
                $count++;
            }
        }

        session()->flash('success', "Successfully marked {$count} shipment(s) as shipped.");
        $this->selectedShipmentIds = [];
        $this->class->refresh();
    }

    public function bulkMarkAsDelivered(): void
    {
        if (empty($this->selectedShipmentIds)) {
            session()->flash('error', 'Please select at least one shipment.');

            return;
        }

        $count = 0;
        foreach ($this->selectedShipmentIds as $shipmentId) {
            $shipment = \App\Models\ClassDocumentShipment::find($shipmentId);
            if ($shipment && $shipment->status === 'shipped') {
                $shipment->markAsDelivered();
                $count++;
            }
        }

        session()->flash('success', "Successfully marked {$count} shipment(s) as delivered.");
        $this->selectedShipmentIds = [];
        $this->class->refresh();
    }

    public function resetTableFilters(): void
    {
        $this->filterMonth = '';
        $this->filterYear = '';
        $this->filterStatus = '';
        $this->selectedShipmentIds = [];
    }

    // Student item selection methods
    public function toggleSelectAllItems($shipmentId): void
    {
        $filteredItems = $this->getFilteredShipmentItems($shipmentId);
        $itemIds = $filteredItems->pluck('id')->toArray();

        if (count(array_intersect($this->selectedShipmentItemIds, $itemIds)) === count($itemIds)) {
            // Deselect all items from this shipment
            $this->selectedShipmentItemIds = array_diff($this->selectedShipmentItemIds, $itemIds);
        } else {
            // Select all items from this shipment
            $this->selectedShipmentItemIds = array_unique(array_merge($this->selectedShipmentItemIds, $itemIds));
        }
    }

    public function bulkMarkItemsAsShipped(): void
    {
        if (empty($this->selectedShipmentItemIds)) {
            session()->flash('error', 'Please select at least one student.');

            return;
        }

        $count = 0;
        $failed = 0;
        foreach ($this->selectedShipmentItemIds as $itemId) {
            $item = \App\Models\ClassDocumentShipmentItem::find($itemId);
            if ($item && $item->status === 'pending') {
                // Use markAsShipped() to ensure stock is deducted
                $item->markAsShipped();
                $count++;
            } else {
                $failed++;
            }
        }

        if ($count > 0) {
            session()->flash('success', "Successfully marked {$count} student(s) as shipped.".($failed > 0 ? " ({$failed} skipped)" : ''));
        } else {
            session()->flash('error', 'No eligible items to ship. Items must have "pending" status.');
        }

        $this->selectedShipmentItemIds = [];
        $this->class->refresh();
    }

    public function bulkMarkItemsAsDelivered(): void
    {
        if (empty($this->selectedShipmentItemIds)) {
            session()->flash('error', 'Please select at least one student.');

            return;
        }

        $count = 0;
        $failed = 0;
        foreach ($this->selectedShipmentItemIds as $itemId) {
            $item = \App\Models\ClassDocumentShipmentItem::find($itemId);
            if ($item && $item->status === 'shipped') {
                // Use markAsDelivered() to maintain consistency
                $item->markAsDelivered();
                $count++;
            } else {
                $failed++;
            }
        }

        if ($count > 0) {
            session()->flash('success', "Successfully marked {$count} student(s) as delivered.".($failed > 0 ? " ({$failed} skipped)" : ''));
        } else {
            session()->flash('error', 'No eligible items to deliver. Items must have "shipped" status.');
        }

        $this->selectedShipmentItemIds = [];
        $this->class->refresh();
    }

    public function getFilteredShipmentsProperty()
    {
        $query = $this->class->documentShipments()
            ->with(['product', 'warehouse', 'items.student'])
            ->orderBy('period_start_date', 'desc');

        // Apply status filter
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        // Apply month/year filter
        if ($this->filterMonth && $this->filterYear) {
            $query->forPeriod($this->filterYear, $this->filterMonth);
        } elseif ($this->filterYear) {
            $query->forPeriod($this->filterYear);
        }

        return $query->get();
    }

    public function getFilteredPendingCountProperty(): int
    {
        $query = $this->class->documentShipments()->pending();

        if ($this->filterMonth && $this->filterYear) {
            $query->forPeriod($this->filterYear, $this->filterMonth);
        } elseif ($this->filterYear) {
            $query->forPeriod($this->filterYear);
        }

        return $query->count();
    }

    public function getFilteredProcessingCountProperty(): int
    {
        $query = $this->class->documentShipments()->processing();

        if ($this->filterMonth && $this->filterYear) {
            $query->forPeriod($this->filterYear, $this->filterMonth);
        } elseif ($this->filterYear) {
            $query->forPeriod($this->filterYear);
        }

        return $query->count();
    }

    public function getFilteredShippedCountProperty(): int
    {
        $query = $this->class->documentShipments()->shipped();

        if ($this->filterMonth && $this->filterYear) {
            $query->forPeriod($this->filterYear, $this->filterMonth);
        } elseif ($this->filterYear) {
            $query->forPeriod($this->filterYear);
        }

        return $query->count();
    }

    public function getFilteredDeliveredCountProperty(): int
    {
        $query = $this->class->documentShipments()->delivered();

        if ($this->filterMonth && $this->filterYear) {
            $query->forPeriod($this->filterYear, $this->filterMonth);
        } elseif ($this->filterYear) {
            $query->forPeriod($this->filterYear);
        }

        return $query->count();
    }

    public function viewShipmentDetails($shipmentId): void
    {
        if ($this->selectedShipmentId === $shipmentId) {
            $this->selectedShipmentId = null;
            $this->resetShipmentFilters();
            $this->dispatch('update-shipment-url', expandedShipment: null);
        } else {
            $this->selectedShipmentId = $shipmentId;
            $this->resetShipmentFilters();
            $this->dispatch('update-shipment-url', expandedShipment: $shipmentId);
        }
    }

    public function resetShipmentFilters(): void
    {
        $this->shipmentSearch = '';
        $this->shipmentStatusFilter = '';
    }

    public function getFilteredShipmentItems($shipmentId)
    {
        $shipment = \App\Models\ClassDocumentShipment::findOrFail($shipmentId);

        $query = $shipment->items()->with('student.user');

        // Apply search filter
        if ($this->shipmentSearch) {
            $query->whereHas('student.user', function ($q) {
                $q->where('name', 'like', '%'.$this->shipmentSearch.'%');
            });
        }

        // Apply status filter
        if ($this->shipmentStatusFilter) {
            $query->where('status', $this->shipmentStatusFilter);
        }

        return $query->paginate($this->shipmentItemsPerPage);
    }

    public function exportShipmentItems($shipmentId)
    {
        $shipment = \App\Models\ClassDocumentShipment::findOrFail($shipmentId);

        $query = $shipment->items()->with('student.user');

        // Apply search filter
        if ($this->shipmentSearch) {
            $query->whereHas('student.user', function ($q) {
                $q->where('name', 'like', '%'.$this->shipmentSearch.'%');
            });
        }

        // Apply status filter
        if ($this->shipmentStatusFilter) {
            $query->where('status', $this->shipmentStatusFilter);
        }

        $items = $query->get();

        // Generate CSV content
        $csvContent = "Student Name,Phone,Address Line 1,Address Line 2,City,State,Postcode,Country,Quantity,Status,Tracking Number,Shipped At,Delivered At\n";

        foreach ($items as $item) {
            $csvContent .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s",%d,%s,"%s","%s","%s"'."\n",
                $item->student?->user?->name ?? '-',
                $item->student?->phone ?? '-',
                $item->student?->address_line_1 ?? '-',
                $item->student?->address_line_2 ?? '-',
                $item->student?->city ?? '-',
                $item->student?->state ?? '-',
                $item->student?->postcode ?? '-',
                $item->student?->country ?? '-',
                $item->quantity,
                $item->status_label,
                $item->tracking_number ?? '-',
                $item->shipped_at ? $item->shipped_at->format('Y-m-d H:i:s') : '-',
                $item->delivered_at ? $item->delivered_at->format('Y-m-d H:i:s') : '-'
            );
        }

        // Return download response
        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, 'shipment-'.$shipment->shipment_number.'-items-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function markItemAsShipped($itemId): void
    {
        $item = \App\Models\ClassDocumentShipmentItem::findOrFail($itemId);
        $item->markAsShipped();
        session()->flash('success', 'Item marked as shipped.');
        $this->class->refresh();

        // Close modal if open and refresh the selected item
        if ($this->showStudentShipmentModal && $this->selectedShipmentItem && $this->selectedShipmentItem->id === $itemId) {
            $this->selectedShipmentItem->refresh();
        }
    }

    public function markItemAsDelivered($itemId): void
    {
        $item = \App\Models\ClassDocumentShipmentItem::findOrFail($itemId);
        $item->markAsDelivered();
        session()->flash('success', 'Item marked as delivered.');
        $this->class->refresh();

        // Close modal if open and refresh the selected item
        if ($this->showStudentShipmentModal && $this->selectedShipmentItem && $this->selectedShipmentItem->id === $itemId) {
            $this->selectedShipmentItem->refresh();
        }
    }

    public function openImportModal($shipmentId): void
    {
        $this->selectedShipmentId = $shipmentId;
        $this->showImportModal = true;
        $this->importFile = null;
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->selectedShipmentId = null;
        $this->importFile = null;
        $this->matchBy = 'name';
    }

    public function closeImportResultModal(): void
    {
        $this->showImportResultModal = false;
        $this->importResult = [];
    }

    // Import Students to Class Methods
    public function openImportStudentModal(): void
    {
        $this->showImportStudentModal = true;
        $this->importStudentFile = null;
        $this->createMissingStudents = false;
        $this->autoEnrollImported = true;
        $this->importStudentPassword = 'password123';
        $this->importStudentResult = [];
    }

    public function closeImportStudentModal(): void
    {
        $this->showImportStudentModal = false;
        $this->importStudentFile = null;
        $this->createMissingStudents = false;
        $this->autoEnrollImported = true;
        $this->importStudentPassword = 'password123';
    }

    public function closeImportStudentResultModal(): void
    {
        $this->showImportStudentResultModal = false;
        $this->importStudentResult = [];
    }

    public function downloadImportStudentSample(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $csvContent = "phone,name,email,order_id\n";
        $csvContent .= "60123456789,Ahmad Ali,ahmad@example.com,ORD-2024-001\n";
        $csvContent .= "60198765432,Siti Aminah,siti@example.com,ORD-2024-002\n";
        $csvContent .= "60112233445,Muhammad Hafiz,hafiz@example.com,\n";

        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, 'import-students-sample.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function importStudentsToClass(): void
    {
        $this->validate([
            'importStudentFile' => 'required|file|mimes:csv,txt|max:10240',
            'importStudentPassword' => $this->createMissingStudents ? 'required|string|min:8' : 'nullable',
        ], [
            'importStudentFile.required' => 'Please select a CSV file to import.',
            'importStudentFile.mimes' => 'Only CSV files are accepted.',
            'importStudentFile.max' => 'File size cannot exceed 10MB.',
            'importStudentPassword.required' => 'Password is required when creating new students.',
            'importStudentPassword.min' => 'Password must be at least 8 characters.',
        ]);

        $this->importStudentProcessing = true;

        try {
            $fileName = 'student_import_' . time() . '_' . uniqid() . '.csv';
            $relativePath = 'imports/students/' . $fileName;

            // CRITICAL: Read file contents IMMEDIATELY before temp file can be cleaned up
            // On shared hosting, Livewire temp files may be deleted very quickly
            $tempPath = $this->importStudentFile->getRealPath();
            \Illuminate\Support\Facades\Log::info("Student import - Temp file path: {$tempPath}");

            if (! $tempPath || ! file_exists($tempPath)) {
                throw new \Exception('Temporary upload file not found. Please try uploading again.');
            }

            $fileContents = file_get_contents($tempPath);
            \Illuminate\Support\Facades\Log::info("Student import - Read " . strlen($fileContents) . " bytes from temp file");

            if ($fileContents === false || empty($fileContents)) {
                throw new \Exception('Failed to read uploaded file contents.');
            }

            // Store using Storage facade with the contents we already read
            $stored = \Illuminate\Support\Facades\Storage::disk('local')->put($relativePath, $fileContents);
            \Illuminate\Support\Facades\Log::info("Student import - Storage put result: " . ($stored ? 'SUCCESS' : 'FAILED'));

            if (! $stored) {
                throw new \Exception('Failed to store the uploaded CSV file.');
            }

            // Verify file was actually stored
            $fileExists = \Illuminate\Support\Facades\Storage::disk('local')->exists($relativePath);
            \Illuminate\Support\Facades\Log::info("Student import - File exists after storage: " . ($fileExists ? 'YES' : 'NO'));

            if (! $fileExists) {
                throw new \Exception('File storage verification failed - file does not exist after upload.');
            }

            $storedSize = \Illuminate\Support\Facades\Storage::disk('local')->size($relativePath);
            \Illuminate\Support\Facades\Log::info("Student import - Stored file size: {$storedSize} bytes");

            // Create import progress record with relative path
            $importProgress = \App\Models\StudentImportProgress::create([
                'class_id' => $this->class->id,
                'user_id' => auth()->id(),
                'file_path' => $relativePath,
                'status' => 'pending',
                'auto_enroll' => $this->autoEnrollImported,
                'create_missing' => $this->createMissingStudents,
                'default_password' => $this->createMissingStudents ? $this->importStudentPassword : null,
            ]);

            \Illuminate\Support\Facades\Log::info("Student import - Created progress record ID: {$importProgress->id} with file_path: {$importProgress->file_path}");

            $this->currentStudentImportProgressId = $importProgress->id;

            // Dispatch the job
            \App\Jobs\ProcessStudentImportToClass::dispatch($importProgress->id);

            // Close the import modal and show progress
            $this->closeImportStudentModal();

            // Start polling for progress
            $this->dispatch('start-student-import-polling');

        } catch (\Exception $e) {
            session()->flash('error', 'Import failed: ' . $e->getMessage());
            $this->importStudentProcessing = false;
        }
    }

    #[On('checkStudentImportProgress')]
    public function checkStudentImportProgress(): void
    {
        if (! $this->currentStudentImportProgressId) {
            $this->importStudentProcessing = false;
            return;
        }

        $progress = \App\Models\StudentImportProgress::find($this->currentStudentImportProgressId);

        if (! $progress) {
            $this->importStudentProcessing = false;
            $this->currentStudentImportProgressId = null;
            $this->dispatch('stop-student-import-polling');
            return;
        }

        $this->studentImportProgress = [
            'status' => $progress->status,
            'total_rows' => $progress->total_rows,
            'processed_rows' => $progress->processed_rows,
            'matched_count' => $progress->matched_count,
            'created_count' => $progress->created_count,
            'enrolled_count' => $progress->enrolled_count,
            'skipped_count' => $progress->skipped_count,
            'error_count' => $progress->error_count,
            'progress_percentage' => $progress->progress_percentage,
        ];

        if ($progress->isCompleted()) {
            $this->importStudentProcessing = false;
            $this->importStudentResult = $progress->result ?? [];
            $this->showImportStudentResultModal = true;
            $this->currentStudentImportProgressId = null;
            $this->studentImportProgress = null;
            $this->dispatch('stop-student-import-polling');

            // Refresh the class data
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user',
            ]);
        } elseif ($progress->isFailed()) {
            $this->importStudentProcessing = false;
            $this->currentStudentImportProgressId = null;
            $this->studentImportProgress = null;
            $this->dispatch('stop-student-import-polling');
            session()->flash('error', 'Import failed: ' . ($progress->error_message ?? 'Unknown error'));
        } elseif ($progress->isCancelled()) {
            $this->importStudentProcessing = false;
            $this->currentStudentImportProgressId = null;
            $this->studentImportProgress = null;
            $this->dispatch('stop-student-import-polling');
            session()->flash('info', 'Import was cancelled.');
        }
    }

    public function cancelStudentImport(): void
    {
        if ($this->currentStudentImportProgressId) {
            $progress = \App\Models\StudentImportProgress::find($this->currentStudentImportProgressId);
            if ($progress && !$progress->isCompleted() && !$progress->isFailed()) {
                $progress->update(['status' => 'cancelled', 'error_message' => 'Cancelled by user']);
                if ($progress->file_path && file_exists($progress->file_path)) {
                    unlink($progress->file_path);
                }
            }
        }

        $this->importStudentProcessing = false;
        $this->currentStudentImportProgressId = null;
        $this->studentImportProgress = null;
        $this->dispatch('stop-student-import-polling');
    }

    public function viewStudentShipmentDetails($itemId): void
    {
        $this->selectedShipmentItem = \App\Models\ClassDocumentShipmentItem::with([
            'student.user',
            'student.enrollments.course',
            'shipment.product',
            'shipment.warehouse',
        ])->findOrFail($itemId);
        $this->showStudentShipmentModal = true;
    }

    public function closeStudentShipmentModal(): void
    {
        $this->showStudentShipmentModal = false;
        $this->selectedShipmentItem = null;
    }

    public function editShipmentItem($itemId): void
    {
        $this->editingShipmentItem = \App\Models\ClassDocumentShipmentItem::with([
            'student',
            'shipment',
        ])->findOrFail($itemId);

        $this->editTrackingNumber = $this->editingShipmentItem->tracking_number ?? '';
        $this->editStatus = $this->editingShipmentItem->status;

        // Populate address fields
        $student = $this->editingShipmentItem->student;
        $this->editAddressLine1 = $student->address_line_1 ?? '';
        $this->editAddressLine2 = $student->address_line_2 ?? '';
        $this->editCity = $student->city ?? '';
        $this->editState = $student->state ?? '';
        $this->editPostcode = $student->postcode ?? '';
        $this->editCountry = $student->country ?? '';

        $this->showEditShipmentItemModal = true;
    }

    public function closeEditShipmentItemModal(): void
    {
        $this->showEditShipmentItemModal = false;
        $this->editingShipmentItem = null;
        $this->editTrackingNumber = '';
        $this->editStatus = '';
        $this->editAddressLine1 = '';
        $this->editAddressLine2 = '';
        $this->editCity = '';
        $this->editState = '';
        $this->editPostcode = '';
        $this->editCountry = '';
        $this->resetValidation();
    }

    public function updateShipmentItem(): void
    {
        $this->validate([
            'editTrackingNumber' => 'nullable|string|max:255',
            'editStatus' => 'required|in:pending,shipped,delivered',
            'editAddressLine1' => 'nullable|string|max:255',
            'editAddressLine2' => 'nullable|string|max:255',
            'editCity' => 'nullable|string|max:100',
            'editState' => 'nullable|string|max:100',
            'editPostcode' => 'nullable|string|max:20',
            'editCountry' => 'nullable|string|max:100',
        ]);

        try {
            // Update tracking number
            $this->editingShipmentItem->tracking_number = $this->editTrackingNumber ?: null;

            // Update student address
            $student = $this->editingShipmentItem->student;
            $student->address_line_1 = $this->editAddressLine1 ?: null;
            $student->address_line_2 = $this->editAddressLine2 ?: null;
            $student->city = $this->editCity ?: null;
            $student->state = $this->editState ?: null;
            $student->postcode = $this->editPostcode ?: null;
            $student->country = $this->editCountry ?: null;
            $student->save();

            // Update status and related timestamps
            if ($this->editStatus === 'shipped' && $this->editingShipmentItem->status !== 'shipped') {
                $this->editingShipmentItem->markAsShipped();
            } elseif ($this->editStatus === 'delivered' && $this->editingShipmentItem->status !== 'delivered') {
                $this->editingShipmentItem->markAsDelivered();
            } else {
                $this->editingShipmentItem->status = $this->editStatus;
                $this->editingShipmentItem->save();
            }

            session()->flash('success', 'Shipment item and address updated successfully.');
            $this->class->refresh();
            $this->closeEditShipmentItemModal();
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update shipment item: '.$e->getMessage());
        }
    }

    public function importShipmentTracking(): void
    {
        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt|max:10240',
            'matchBy' => 'required|in:name,phone',
        ]);

        try {
            // Create unique filename
            $fileName = 'import_'.time().'_'.uniqid().'.csv';
            $absolutePath = storage_path('app/imports/'.$fileName);

            // Ensure imports directory exists
            $importsDir = storage_path('app/imports');
            if (! file_exists($importsDir)) {
                mkdir($importsDir, 0755, true);
            }

            // Read file contents from Livewire temporary storage and write synchronously
            // This ensures the file is available when the queue job runs
            $fileContents = file_get_contents($this->importFile->getRealPath());

            if ($fileContents === false) {
                throw new \Exception('Failed to read uploaded file');
            }

            // Write the file synchronously to permanent storage
            if (file_put_contents($absolutePath, $fileContents) === false) {
                throw new \Exception('Failed to write file to permanent storage');
            }

            // Verify the file was written successfully
            if (! file_exists($absolutePath)) {
                throw new \Exception('File verification failed after writing');
            }

            // Dispatch the job with matchBy parameter
            \App\Jobs\ProcessShipmentImport::dispatch(
                $this->selectedShipmentId,
                $absolutePath,
                (int) auth()->id(),
                $this->matchBy
            );

            // Mark as processing
            $this->importProcessing = true;

            $this->closeImportModal();

            // Start polling for progress
            $this->dispatch('start-import-polling');

            // Show success message
            session()->flash('message', 'CSV import started successfully. Processing in background...');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so they display properly
            throw $e;
        } catch (\Exception $e) {
            \Log::error('CSV Import Error: '.$e->getMessage(), [
                'shipment_id' => $this->selectedShipmentId,
                'user_id' => auth()->id(),
                'exception' => $e,
            ]);

            session()->flash('error', 'Import failed: '.$e->getMessage());
        }
    }

    #[On('checkImportProgress')]
    public function checkImportProgress(): void
    {
        $userId = auth()->id();
        $shipmentId = $this->selectedShipmentId ?? request()->query('shipmentId');

        // Check for final result first
        $result = \Illuminate\Support\Facades\Cache::get("shipment_import_{$shipmentId}_{$userId}_result");

        if ($result) {
            $this->importProcessing = false;

            if ($result['status'] === 'completed') {
                $updated = $result['updated'] ?? 0;
                $imported = $result['imported'] ?? 0;
                $errors = $result['errors'] ?? [];

                // Store result for modal display
                $this->importResult = [
                    'status' => 'completed',
                    'imported' => $imported,
                    'updated' => $updated,
                    'errors' => $errors,
                ];

                // Clear the cache
                \Illuminate\Support\Facades\Cache::forget("shipment_import_{$shipmentId}_{$userId}_result");
                \Illuminate\Support\Facades\Cache::forget("shipment_import_{$shipmentId}_{$userId}_progress");

                $this->class->refresh();
                $this->dispatch('stop-import-polling');

                // Show result modal
                $this->showImportResultModal = true;
            } elseif ($result['status'] === 'failed') {
                // Store error result for modal display
                $this->importResult = [
                    'status' => 'failed',
                    'error' => $result['error'] ?? 'Unknown error occurred',
                ];

                \Illuminate\Support\Facades\Cache::forget("shipment_import_{$shipmentId}_{$userId}_result");
                $this->dispatch('stop-import-polling');

                // Show result modal
                $this->showImportResultModal = true;
            }
        } else {
            // Check progress
            $progress = \Illuminate\Support\Facades\Cache::get("shipment_import_{$shipmentId}_{$userId}_progress");
            if ($progress) {
                $this->importProgress = $progress;
            }
        }
    }

    // PIC Performance Properties
    public function getPicPerformanceDataProperty()
    {
        $course = $this->class->course;

        if (! $course) {
            return collect();
        }

        // Get student IDs in this class
        $studentIds = \App\Models\ClassStudent::where('class_id', $this->class->id)
            ->pluck('student_id')
            ->toArray();

        if (empty($studentIds)) {
            return collect();
        }

        // Get all enrollments for students in this class
        $enrollments = \App\Models\Enrollment::with(['student.user', 'enrolledBy', 'orders'])
            ->where('course_id', $course->id)
            ->whereIn('student_id', $studentIds)
            ->get();

        // Group by PIC (filter out null enrolled_by first)
        $picData = $enrollments
            ->filter(fn ($enrollment) => $enrollment->enrolled_by !== null && $enrollment->enrolledBy !== null)
            ->groupBy('enrolled_by')->map(function ($picEnrollments, $picId) {
                $pic = $picEnrollments->first()->enrolledBy;
                $studentCount = $picEnrollments->count();

                // Calculate status distribution
                $statusCounts = $picEnrollments->countBy('status');

                // Calculate payment collection
                $totalExpected = 0;
                $totalCollected = 0;
                $totalPending = 0;
                $totalOverdue = 0;

                foreach ($picEnrollments as $enrollment) {
                    // Get orders for current year
                    $orders = $enrollment->orders()
                        ->whereYear('period_start', now()->year)
                        ->get();

                    foreach ($orders as $order) {
                        $totalExpected += $order->amount;

                        if ($order->status === 'paid') {
                            $totalCollected += $order->amount;
                        } elseif ($order->status === 'pending' && $order->period_end < now()) {
                            $totalOverdue += $order->amount;
                        } elseif ($order->status === 'pending') {
                            $totalPending += $order->amount;
                        }
                    }
                }

                $collectionRate = $totalExpected > 0 ? round(($totalCollected / $totalExpected) * 100, 1) : 0;

                return [
                    'pic' => $pic,
                    'student_count' => $studentCount,
                    'status_distribution' => [
                        'enrolled' => $statusCounts->get('enrolled', 0),
                        'active' => $statusCounts->get('active', 0),
                        'completed' => $statusCounts->get('completed', 0),
                        'dropped' => $statusCounts->get('dropped', 0),
                        'suspended' => $statusCounts->get('suspended', 0),
                        'pending' => $statusCounts->get('pending', 0),
                    ],
                    'payment_stats' => [
                        'total_expected' => $totalExpected,
                        'total_collected' => $totalCollected,
                        'total_pending' => $totalPending,
                        'total_overdue' => $totalOverdue,
                        'collection_rate' => $collectionRate,
                    ],
                    'students' => $picEnrollments->map(function ($enrollment) {
                        $student = $enrollment->student;

                        // Get payment status for this student
                        $orders = $enrollment->orders()
                            ->whereYear('period_start', now()->year)
                            ->get();

                        $expectedAmount = $orders->sum('amount');
                        $paidAmount = $orders->where('status', 'paid')->sum('amount');
                        $pendingAmount = $orders->where('status', 'pending')->sum('amount');
                        $overdueAmount = $orders->where('status', 'pending')
                            ->filter(fn ($o) => $o->period_end < now())
                            ->sum('amount');

                        return [
                            'id' => $student->id,
                            'enrollment_id' => $enrollment->id,
                            'name' => $student->user?->name ?? 'N/A',
                            'email' => $student->user?->email ?? 'N/A',
                            'enrollment_status' => $enrollment->status,
                            'enrollment_date' => $enrollment->enrollment_date,
                            'payment_summary' => [
                                'expected' => $expectedAmount,
                                'paid' => $paidAmount,
                                'pending' => $pendingAmount,
                                'overdue' => $overdueAmount,
                            ],
                        ];
                    }),
                ];
            })
            ->sortByDesc('student_count')
            ->values();

        return $picData;
    }

    public function getPicPerformanceSummaryProperty()
    {
        $picData = $this->pic_performance_data;

        return [
            'total_pics' => $picData->count(),
            'total_students' => $picData->sum('student_count'),
            'total_expected' => $picData->sum('payment_stats.total_expected'),
            'total_collected' => $picData->sum('payment_stats.total_collected'),
            'total_pending' => $picData->sum('payment_stats.total_pending'),
            'total_overdue' => $picData->sum('payment_stats.total_overdue'),
            'overall_collection_rate' => $picData->sum('payment_stats.total_expected') > 0
                ? round(($picData->sum('payment_stats.total_collected') / $picData->sum('payment_stats.total_expected')) * 100, 1)
                : 0,
        ];
    }

    // PIC Performance Filtering & Pagination Properties
    public array $picSearchQueries = [];

    public array $picStatusFilters = [];

    public array $picPaymentFilters = [];

    public array $picCurrentPages = [];

    public int $picPerPage = 10;

    public function getPicStudents($picId, $students)
    {
        $search = $this->picSearchQueries[$picId] ?? '';
        $statusFilter = $this->picStatusFilters[$picId] ?? 'all';
        $paymentFilter = $this->picPaymentFilters[$picId] ?? 'all';
        $currentPage = $this->picCurrentPages[$picId] ?? 1;

        // Filter students
        $filtered = $students->filter(function ($student) use ($search, $statusFilter, $paymentFilter) {
            // Search filter
            if ($search) {
                $searchLower = strtolower($search);
                $nameMatch = str_contains(strtolower($student['name']), $searchLower);
                $emailMatch = str_contains(strtolower($student['email']), $searchLower);

                if (! $nameMatch && ! $emailMatch) {
                    return false;
                }
            }

            // Status filter
            if ($statusFilter !== 'all' && $student['enrollment_status'] !== $statusFilter) {
                return false;
            }

            // Payment filter
            if ($paymentFilter !== 'all') {
                $hasOverdue = $student['payment_summary']['overdue'] > 0;
                $hasPending = $student['payment_summary']['pending'] > 0;
                $isPaid = $student['payment_summary']['paid'] > 0 && $student['payment_summary']['expected'] > 0 && $student['payment_summary']['paid'] >= $student['payment_summary']['expected'];

                if ($paymentFilter === 'overdue' && ! $hasOverdue) {
                    return false;
                }
                if ($paymentFilter === 'pending' && ! $hasPending) {
                    return false;
                }
                if ($paymentFilter === 'paid' && ! $isPaid) {
                    return false;
                }
            }

            return true;
        });

        // Paginate
        $total = $filtered->count();
        $totalPages = (int) ceil($total / $this->picPerPage);
        $offset = ($currentPage - 1) * $this->picPerPage;

        return [
            'students' => $filtered->slice($offset, $this->picPerPage)->values(),
            'total' => $total,
            'per_page' => $this->picPerPage,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
        ];
    }

    public function setPicPage($picId, $page)
    {
        $this->picCurrentPages[$picId] = max(1, (int) $page);
    }

    public function nextPicPage($picId, $totalPages)
    {
        $currentPage = $this->picCurrentPages[$picId] ?? 1;
        $this->picCurrentPages[$picId] = min($currentPage + 1, $totalPages);
    }

    public function previousPicPage($picId)
    {
        $currentPage = $this->picCurrentPages[$picId] ?? 1;
        $this->picCurrentPages[$picId] = max(1, $currentPage - 1);
    }

    public function updatedPicSearchQueries($value, $key)
    {
        // Reset to first page when search changes
        $this->picCurrentPages[$key] = 1;
    }

    public function updatedPicStatusFilters($value, $key)
    {
        // Reset to first page when filter changes
        $this->picCurrentPages[$key] = 1;
    }

    public function updatedPicPaymentFilters($value, $key)
    {
        // Reset to first page when filter changes
        $this->picCurrentPages[$key] = 1;
    }

    // Class Assignment Approvals
    public string $studentSubTab = 'enrolled';
    public string $approvalSearch = '';
    public array $approvalSubscriptionToggles = [];
    public array $selectedApprovalIds = [];

    public function updatedApprovalSearch(): void
    {
        $this->selectedApprovalIds = [];
    }

    public function getPendingApprovalsProperty()
    {
        return \App\Models\ClassAssignmentApproval::where('class_id', $this->class->id)
            ->pending()
            ->with(['student.user', 'productOrder', 'assignedByUser'])
            ->when($this->approvalSearch, function ($query) {
                $search = '%' . $this->approvalSearch . '%';
                $query->where(function ($q) use ($search) {
                    $q->whereHas('student.user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', $search)
                            ->orWhere('phone', 'like', $search);
                    })
                    ->orWhereHas('student', function ($studentQuery) use ($search) {
                        $studentQuery->where('phone', 'like', $search);
                    })
                    ->orWhereHas('productOrder', function ($orderQuery) use ($search) {
                        $orderQuery->where('order_number', 'like', $search);
                    });
                });
            })
            ->latest()
            ->get();
    }

    public function approveAssignment(int $approvalId): void
    {
        $approval = \App\Models\ClassAssignmentApproval::findOrFail($approvalId);
        $enrollWithSubscription = $this->approvalSubscriptionToggles[$approvalId] ?? false;
        $approval->approve(auth()->user(), $enrollWithSubscription);
        session()->flash('message', 'Student enrolled successfully.');
    }

    public function rejectAssignment(int $approvalId, ?string $notes = null): void
    {
        $approval = \App\Models\ClassAssignmentApproval::findOrFail($approvalId);
        $approval->reject(auth()->user(), $notes);
        session()->flash('message', 'Assignment rejected.');
    }

    public function bulkApproveAssignments(): void
    {
        foreach ($this->selectedApprovalIds as $id) {
            $approval = \App\Models\ClassAssignmentApproval::find($id);
            if ($approval && $approval->status === 'pending') {
                $enrollWithSubscription = $this->approvalSubscriptionToggles[$id] ?? false;
                $approval->approve(auth()->user(), $enrollWithSubscription);
            }
        }
        $this->selectedApprovalIds = [];
        session()->flash('message', 'Selected students enrolled successfully.');
    }

    public function bulkRejectAssignments(): void
    {
        foreach ($this->selectedApprovalIds as $id) {
            $approval = \App\Models\ClassAssignmentApproval::find($id);
            if ($approval && $approval->status === 'pending') {
                $approval->reject(auth()->user());
            }
        }
        $this->selectedApprovalIds = [];
        session()->flash('message', 'Selected assignments rejected.');
    }

    public function toggleApprovalSelection(int $approvalId): void
    {
        if (in_array($approvalId, $this->selectedApprovalIds)) {
            $this->selectedApprovalIds = array_values(array_diff($this->selectedApprovalIds, [$approvalId]));
        } else {
            $this->selectedApprovalIds[] = $approvalId;
        }
    }

    public function toggleApprovalSubscription(int $approvalId): void
    {
        $this->approvalSubscriptionToggles[$approvalId] = ! ($this->approvalSubscriptionToggles[$approvalId] ?? false);
    }

    // ===== Upsell Tab Methods =====

    public function getAvailableFunnelsProperty()
    {
        return \App\Models\Funnel::where('status', 'published')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    public function getUpsellAvailablePicsProperty()
    {
        return \App\Models\User::whereIn('role', ['admin', 'class_admin', 'sales', 'employee'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function getUpsellSessionsProperty()
    {
        return $this->class->sessions()
            ->with(['assignedTeacher.user'])
            ->withCount(['funnelOrders as upsell_orders_count' => function ($q) {
                $q->whereNotNull('class_session_id');
            }])
            ->withSum(['funnelOrders as upsell_revenue' => function ($q) {
                $q->whereNotNull('class_session_id');
            }], 'funnel_revenue')
            ->withCount(['funnelSessions as upsell_visitors_count'])
            ->when($this->upsellDateFrom, fn ($q) => $q->where('session_date', '>=', $this->upsellDateFrom))
            ->when($this->upsellDateTo, fn ($q) => $q->where('session_date', '<=', $this->upsellDateTo))
            ->when($this->upsellFilterPicId, fn ($q) => $q->whereJsonContains('upsell_pic_user_ids', (int) $this->upsellFilterPicId))
            ->when($this->upsellFilterFunnelId, fn ($q) => $q->whereJsonContains('upsell_funnel_ids', (int) $this->upsellFilterFunnelId))
            ->orderBy('session_date')
            ->orderBy('session_time')
            ->get();
    }

    public function addSessionFunnel(int $sessionId, int $funnelId): void
    {
        $session = $this->class->sessions()->findOrFail($sessionId);
        $ids = $session->upsell_funnel_ids ?? [];

        if (! in_array($funnelId, $ids)) {
            $ids[] = $funnelId;
            $session->update(['upsell_funnel_ids' => $ids]);
        }
    }

    public function removeSessionFunnel(int $sessionId, int $funnelId): void
    {
        $session = $this->class->sessions()->findOrFail($sessionId);
        $ids = array_values(array_diff($session->upsell_funnel_ids ?? [], [$funnelId]));
        $session->update(['upsell_funnel_ids' => empty($ids) ? null : $ids]);
    }

    public function addSessionPic(int $sessionId, int $userId): void
    {
        $session = $this->class->sessions()->findOrFail($sessionId);
        $ids = $session->upsell_pic_user_ids ?? [];

        if (! in_array($userId, $ids)) {
            $ids[] = $userId;
            $session->update(['upsell_pic_user_ids' => $ids]);
        }
    }

    public function removeSessionPic(int $sessionId, int $userId): void
    {
        $session = $this->class->sessions()->findOrFail($sessionId);
        $ids = array_values(array_diff($session->upsell_pic_user_ids ?? [], [$userId]));
        $session->update(['upsell_pic_user_ids' => empty($ids) ? null : $ids]);
    }

    public function getUpsellAvailableTeachersProperty()
    {
        return \App\Models\User::where('status', 'active')
            ->where(function ($q) {
                $q->whereHas('teacher')
                    ->orWhereIn('role', ['admin', 'class_admin']);
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function addSessionTeacher(int $sessionId, int $userId): void
    {
        $session = $this->class->sessions()->findOrFail($sessionId);
        $ids = $session->upsell_teacher_ids ?? [];

        if (! in_array($userId, $ids)) {
            $ids[] = $userId;
            $session->update(['upsell_teacher_ids' => $ids]);
        }
    }

    public function removeSessionTeacher(int $sessionId, int $userId): void
    {
        $session = $this->class->sessions()->findOrFail($sessionId);
        $ids = array_values(array_diff($session->upsell_teacher_ids ?? [], [$userId]));
        $session->update(['upsell_teacher_ids' => empty($ids) ? null : $ids]);
    }

    public function updateSessionCommissionRate(int $sessionId, $rate): void
    {
        $session = $this->class->sessions()->findOrFail($sessionId);
        $rate = $rate === '' || $rate === null ? null : (float) $rate;
        $session->update(['upsell_teacher_commission_rate' => $rate]);
    }

    public function showUpsellDetail(int $sessionId): void
    {
        $this->upsellDetailSessionId = $sessionId;
        $this->showUpsellDetailModal = true;
    }

    public function closeUpsellDetail(): void
    {
        $this->showUpsellDetailModal = false;
        $this->upsellDetailSessionId = null;
    }

    public function getUpsellDetailSessionProperty()
    {
        if (! $this->upsellDetailSessionId) {
            return null;
        }

        return $this->class->sessions()
            ->with(['funnelOrders.funnel', 'funnelOrders.productOrder'])
            ->withCount(['funnelOrders as upsell_orders_count' => fn ($q) => $q->whereNotNull('class_session_id')])
            ->withSum(['funnelOrders as upsell_revenue' => fn ($q) => $q->whereNotNull('class_session_id')], 'funnel_revenue')
            ->find($this->upsellDetailSessionId);
    }

    public function getUpsellStatsProperty(): array
    {
        $sessions = $this->class->sessions()
            ->whereNotNull('upsell_funnel_ids')
            ->when($this->upsellDateFrom, fn ($q) => $q->where('session_date', '>=', $this->upsellDateFrom))
            ->when($this->upsellDateTo, fn ($q) => $q->where('session_date', '<=', $this->upsellDateTo));

        $totalSessions = $sessions->count();

        $sessionIds = $sessions->clone()->pluck('id');

        $totalVisitors = \App\Models\FunnelSession::whereIn('class_session_id', $sessionIds)->count();

        $orders = \App\Models\FunnelOrder::whereNotNull('class_session_id')
            ->whereIn('class_session_id', $sessionIds)
            ->get();

        $totalConversions = $orders->count();
        $totalRevenue = $orders->sum('funnel_revenue');
        $conversionRate = $totalVisitors > 0 ? round(($totalConversions / $totalVisitors) * 100, 1) : 0;

        return [
            'total_sessions' => $totalSessions,
            'total_visitors' => $totalVisitors,
            'total_conversions' => $totalConversions,
            'total_revenue' => $totalRevenue,
            'conversion_rate' => $conversionRate,
        ];
    }
};

?>

<div class="space-y-5">
    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 5000)" class="rounded-lg bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 px-4 py-3">
            <div class="flex items-center gap-2">
                <flux:icon.check-circle class="h-4 w-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                <p class="text-sm font-medium text-emerald-800 dark:text-emerald-300 flex-1">{{ session('success') }}</p>
                <button @click="show = false" class="text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-300">
                    <flux:icon.x-mark class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 5000)" class="rounded-lg bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 px-4 py-3">
            <div class="flex items-center gap-2">
                <flux:icon.exclamation-triangle class="h-4 w-4 text-red-600 dark:text-red-400 shrink-0" />
                <p class="text-sm font-medium text-red-800 dark:text-red-300 flex-1">{{ session('error') }}</p>
                <button @click="show = false" class="text-red-500 hover:text-red-700 dark:hover:text-red-300">
                    <flux:icon.x-mark class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
    @endif

    <!-- Header -->
    <div class="mb-5 flex items-start justify-between">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('classes.index') }}" class="text-xs text-zinc-400 dark:text-zinc-500 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors">Classes</a>
                <flux:icon name="chevron-right" class="w-3 h-3 text-zinc-300 dark:text-zinc-600" />
                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $class->course->name }}</span>
            </div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl">{{ $class->title }}</flux:heading>
                <flux:badge size="sm" :class="$class->status_badge_class">{{ ucfirst($class->status) }}</flux:badge>
                @if($class->isIndividual())
                    <span class="inline-flex items-center gap-1 rounded-md bg-blue-50 dark:bg-blue-500/10 px-2 py-0.5 text-[11px] font-medium text-blue-700 dark:text-blue-400">
                        <flux:icon name="user" class="w-3 h-3" /> Individual
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 dark:bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-400">
                        <flux:icon name="users" class="w-3 h-3" /> Group
                        @if($class->max_capacity)
                            <span class="text-emerald-500">({{ $class->max_capacity }})</span>
                        @endif
                    </span>
                @endif
            </div>
            <div class="mt-1 flex items-center gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                <span class="flex items-center gap-1">
                    <flux:icon name="academic-cap" class="w-3.5 h-3.5" />
                    {{ $class->teacher->fullName }}
                </span>
                <span class="text-zinc-300 dark:text-zinc-600">|</span>
                <span class="flex items-center gap-1">
                    <flux:icon name="calendar" class="w-3.5 h-3.5" />
                    {{ $class->date_time->format('M d, Y') }} · {{ $class->date_time->format('g:i A') }}
                </span>
                <span class="text-zinc-300 dark:text-zinc-600">|</span>
                <span>{{ $class->formatted_duration }}</span>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <flux:button variant="ghost" href="{{ route('classes.edit', $class) }}" icon="pencil" size="sm">
                Edit
            </flux:button>
            <flux:button variant="ghost" href="{{ route('classes.index') }}" size="sm">
                Back
            </flux:button>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="mb-5 border-b border-zinc-200 dark:border-zinc-700 overflow-x-auto">
        <nav class="flex gap-0.5 -mb-px min-w-max">
            @php
                $tabs = [
                    ['key' => 'overview', 'label' => 'Overview', 'icon' => 'document-text', 'count' => null],
                    ['key' => 'students', 'label' => 'Students', 'icon' => 'users', 'count' => $class->activeStudents->count() ?: null],
                    ['key' => 'timetable', 'label' => 'Timetable', 'icon' => 'calendar', 'count' => null],
                    ['key' => 'certificates', 'label' => 'Certificates', 'icon' => 'document-check', 'count' => null],
                    ['key' => 'payment-reports', 'label' => 'Payments', 'icon' => 'chart-bar', 'count' => null],
                    ['key' => 'pic-performance', 'label' => 'PIC', 'icon' => 'user-group', 'count' => $this->pic_performance_summary['total_pics'] ?: null],
                    ['key' => 'upsell', 'label' => 'Upsell', 'icon' => 'gift', 'count' => $class->timetable?->upsells()->active()->count() ?: null],
                ];
            @endphp

            @foreach($tabs as $tab)
                <button
                    wire:click="setActiveTab('{{ $tab['key'] }}')"
                    class="relative whitespace-nowrap pb-2.5 px-3 text-sm font-medium transition-colors {{ $activeTab === $tab['key'] ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                >
                    <div class="flex items-center gap-1.5">
                        <flux:icon :name="$tab['icon']" class="h-3.5 w-3.5" />
                        {{ $tab['label'] }}
                        @if($tab['count'])
                            <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-semibold rounded-full {{ $activeTab === $tab['key'] ? 'bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300' }}">
                                {{ $tab['count'] }}
                            </span>
                        @endif
                    </div>
                    @if($activeTab === $tab['key'])
                        <span class="absolute bottom-0 inset-x-0 h-0.5 bg-zinc-900 dark:bg-zinc-100 rounded-t"></span>
                    @endif
                </button>
            @endforeach

            @if($class->enable_document_shipment)
                <button
                    wire:click="setActiveTab('shipments')"
                    class="relative whitespace-nowrap pb-2.5 px-3 text-sm font-medium transition-colors {{ $activeTab === 'shipments' ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                >
                    <div class="flex items-center gap-1.5">
                        <flux:icon name="truck" class="h-3.5 w-3.5" />
                        Shipments
                        @if($class->documentShipments()->pending()->count() > 0)
                            <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-semibold rounded-full bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400">
                                {{ $class->documentShipments()->pending()->count() }}
                            </span>
                        @endif
                    </div>
                    @if($activeTab === 'shipments')
                        <span class="absolute bottom-0 inset-x-0 h-0.5 bg-zinc-900 dark:bg-zinc-100 rounded-t"></span>
                    @endif
                </button>
            @endif

            <button
                wire:click="setActiveTab('notifications')"
                class="relative whitespace-nowrap pb-2.5 px-3 text-sm font-medium transition-colors {{ $activeTab === 'notifications' ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
            >
                <div class="flex items-center gap-1.5">
                    <flux:icon name="bell" class="h-3.5 w-3.5" />
                    Notifikasi
                    @if($class->scheduledNotifications()->pending()->count() > 0)
                        <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-semibold rounded-full bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-400">
                            {{ $class->scheduledNotifications()->pending()->count() }}
                        </span>
                    @endif
                </div>
                @if($activeTab === 'notifications')
                    <span class="absolute bottom-0 inset-x-0 h-0.5 bg-zinc-900 dark:bg-zinc-100 rounded-t"></span>
                @endif
            </button>
        </nav>
    </div>

    <!-- Tab Content -->
    <div>
        <!-- Overview Tab -->
        <div class="{{ $activeTab === 'overview' ? 'block' : 'hidden' }}">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Class Information -->
        <div class="lg:col-span-2 space-y-5">
            <!-- Basic Info -->
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                <div class="px-5 py-3.5 border-b border-zinc-100 dark:border-zinc-700">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Class Information</h3>
                </div>
                <div class="px-5 py-4">
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Course</dt>
                            <dd class="mt-0.5 text-sm text-zinc-900 dark:text-zinc-100">{{ $class->course->name }}</dd>
                        </div>

                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Teacher</dt>
                            <dd class="mt-0.5 flex items-center gap-1.5">
                                <flux:avatar size="xs" :name="$class->teacher->fullName" />
                                <span class="text-sm text-zinc-900 dark:text-zinc-100">{{ $class->teacher->fullName }}</span>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Person In Charge</dt>
                            <dd class="mt-0.5">
                                @if($class->pics->count() > 0)
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($class->pics as $pic)
                                            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-zinc-100 dark:bg-zinc-700 rounded-md">
                                                <flux:avatar size="xs" :name="$pic->name" />
                                                <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $pic->name }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-sm text-zinc-400 dark:text-zinc-500 italic">No PIC assigned</span>
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Date & Time</dt>
                            <dd class="mt-0.5 text-sm text-zinc-900 dark:text-zinc-100">{{ $class->formatted_date_time }}</dd>
                        </div>

                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Duration</dt>
                            <dd class="mt-0.5 text-sm text-zinc-900 dark:text-zinc-100">{{ $class->formatted_duration }}</dd>
                        </div>

                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Type</dt>
                            <dd class="mt-0.5">
                                <div class="flex items-center gap-1.5">
                                    @if($class->isIndividual())
                                        <flux:icon.user class="h-3.5 w-3.5 text-blue-500" />
                                        <span class="text-sm text-zinc-900 dark:text-zinc-100">Individual</span>
                                    @else
                                        <flux:icon.users class="h-3.5 w-3.5 text-emerald-500" />
                                        <span class="text-sm text-zinc-900 dark:text-zinc-100">Group</span>
                                        @if($class->max_capacity)
                                            <span class="text-xs text-zinc-500">(Max: {{ $class->max_capacity }})</span>
                                        @endif
                                    @endif
                                </div>
                            </dd>
                        </div>

                        @if($class->location)
                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Location</dt>
                                <dd class="mt-0.5 text-sm text-zinc-900 dark:text-zinc-100">{{ $class->location }}</dd>
                            </div>
                        @endif

                        @if($class->meeting_url)
                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Meeting URL</dt>
                                <dd class="mt-0.5">
                                    <a href="{{ $class->meeting_url }}" target="_blank" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                                        <flux:icon name="link" class="w-3.5 h-3.5" />
                                        Join Meeting
                                    </a>
                                </dd>
                            </div>
                        @endif

                        @if($class->whatsapp_group_link)
                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">WhatsApp Group</dt>
                                <dd class="mt-0.5">
                                    <a href="{{ $class->whatsapp_group_link }}" target="_blank" class="inline-flex items-center gap-1 text-sm text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/></svg>
                                        Join WhatsApp
                                    </a>
                                </dd>
                            </div>
                        @endif

                        @if($class->description)
                            <div class="sm:col-span-2">
                                <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Description</dt>
                                <dd class="mt-0.5 text-sm text-zinc-700 dark:text-zinc-300">{{ $class->description }}</dd>
                            </div>
                        @endif

                        @if($class->notes)
                            <div class="sm:col-span-2">
                                <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Notes</dt>
                                <dd class="mt-0.5 text-sm text-zinc-700 dark:text-zinc-300">{{ $class->notes }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Teacher Allowance Info -->
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                <div class="px-5 py-3.5 border-b border-zinc-100 dark:border-zinc-700">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Teacher Allowance</h3>
                </div>
                <div class="px-5 py-4">
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Rate Type</dt>
                            <dd class="mt-0.5 text-sm text-zinc-900 dark:text-zinc-100">
                                {{ match($class->rate_type) {
                                    'per_class' => 'Per Class (Fixed)',
                                    'per_student' => 'Per Student',
                                    'per_session' => 'Per Session (Commission)',
                                    default => ucfirst($class->rate_type)
                                } }}
                            </dd>
                        </div>

                        @if($class->rate_type !== 'per_session')
                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Rate Amount</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-zinc-100">RM {{ number_format($class->teacher_rate, 2) }}</dd>
                            </div>
                        @endif

                        @if($class->rate_type === 'per_session')
                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Commission Type</dt>
                                <dd class="mt-0.5 text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ $class->commission_type === 'percentage' ? 'Percentage' : 'Fixed Amount' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Commission Value</dt>
                                <dd class="mt-0.5 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    @if($class->commission_type === 'percentage')
                                        {{ number_format($class->commission_value, 1) }}%
                                    @else
                                        RM {{ number_format($class->commission_value, 2) }}
                                    @endif
                                </dd>
                            </div>
                        @endif

                        @if($class->completed_sessions > 0)
                            <div class="sm:col-span-2 pt-2 border-t border-zinc-100 dark:border-zinc-700">
                                <dt class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Total Allowance Earned</dt>
                                <dd class="mt-1 flex items-baseline gap-2">
                                    <span class="text-lg font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">
                                        RM {{ number_format($class->calculateTotalTeacherAllowance(), 2) }}
                                    </span>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                        from {{ $class->completed_sessions }} session(s)
                                    </span>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-5">
            <!-- Attendance Summary -->
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                <div class="px-4 py-3 border-b border-zinc-100 dark:border-zinc-700">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Sessions & Attendance</h3>
                </div>
                <div class="px-4 py-3">
                    <!-- Session Stats as compact grid -->
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <div class="text-center p-2 rounded-md bg-zinc-50 dark:bg-zinc-700/50">
                            <div class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $this->total_sessions_count }}</div>
                            <div class="text-[10px] font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Total</div>
                        </div>
                        <div class="text-center p-2 rounded-md bg-emerald-50 dark:bg-emerald-500/10">
                            <div class="text-lg font-semibold tabular-nums text-emerald-700 dark:text-emerald-400">{{ $this->completed_sessions_count }}</div>
                            <div class="text-[10px] font-medium uppercase tracking-wider text-emerald-600 dark:text-emerald-500">Done</div>
                        </div>
                        <div class="text-center p-2 rounded-md bg-blue-50 dark:bg-blue-500/10">
                            <div class="text-lg font-semibold tabular-nums text-blue-700 dark:text-blue-400">{{ $this->upcoming_sessions_count }}</div>
                            <div class="text-[10px] font-medium uppercase tracking-wider text-blue-600 dark:text-blue-500">Upcoming</div>
                        </div>
                    </div>

                    @if($this->total_sessions_count > 0 && $this->total_attendance_records > 0)
                        <div class="border-t border-zinc-100 dark:border-zinc-700 pt-3">
                            <div class="flex items-center justify-between mb-2.5">
                                <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Attendance</span>
                                <span class="text-sm font-semibold tabular-nums {{ $this->overall_attendance_rate >= 80 ? 'text-emerald-600 dark:text-emerald-400' : ($this->overall_attendance_rate >= 60 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">{{ $this->overall_attendance_rate }}%</span>
                            </div>

                            <!-- Progress bar -->
                            <div class="h-1.5 w-full bg-zinc-100 dark:bg-zinc-700 rounded-full overflow-hidden mb-3">
                                <div class="h-full rounded-full transition-all {{ $this->overall_attendance_rate >= 80 ? 'bg-emerald-500' : ($this->overall_attendance_rate >= 60 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ $this->overall_attendance_rate }}%"></div>
                            </div>

                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        <span class="text-zinc-600 dark:text-zinc-400">Present</span>
                                    </span>
                                    <span class="font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ $this->total_present_count }}</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                        <span class="text-zinc-600 dark:text-zinc-400">Absent</span>
                                    </span>
                                    <span class="font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ $this->total_absent_count }}</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                        <span class="text-zinc-600 dark:text-zinc-400">Late</span>
                                    </span>
                                    <span class="font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ $this->total_late_count }}</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                        <span class="text-zinc-600 dark:text-zinc-400">Excused</span>
                                    </span>
                                    <span class="font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ $this->total_excused_count }}</span>
                                </div>
                            </div>
                        </div>
                    @elseif($this->total_sessions_count > 0)
                        <div class="border-t border-zinc-100 dark:border-zinc-700 pt-3">
                            <p class="text-xs text-zinc-400 dark:text-zinc-500 text-center">No attendance records yet</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                <div class="px-4 py-3 border-b border-zinc-100 dark:border-zinc-700">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Quick Actions</h3>
                </div>
                <div class="p-3 space-y-2">
                    <flux:button
                        variant="filled"
                        size="sm"
                        class="w-full"
                        icon="plus"
                        wire:click="openCreateSessionModal"
                    >
                        Add New Session
                    </flux:button>

                    <flux:button
                        href="{{ route('classes.edit', $class) }}"
                        variant="ghost"
                        size="sm"
                        class="w-full"
                        icon="pencil"
                    >
                        Edit Class Details
                    </flux:button>

                    @if($this->upcoming_sessions_count > 0)
                        <div class="pt-2 border-t border-zinc-100 dark:border-zinc-700">
                            <div class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-1.5">Next Session</div>
                            @php
                                $nextSession = $class->sessions->where('status', 'scheduled')->where('session_date', '>', now()->toDateString())->sortBy('session_date')->first();
                            @endphp
                            @if($nextSession)
                                <div class="text-sm text-zinc-700 dark:text-zinc-300 mb-2">
                                    {{ $nextSession->formatted_date_time }}
                                </div>
                                <flux:button
                                    wire:click="markSessionAsOngoing({{ $nextSession->id }})"
                                    variant="ghost"
                                    size="sm"
                                    class="w-full"
                                    icon="play"
                                >
                                    Start Session
                                </flux:button>
                            @endif
                        </div>
                    @endif

                    @php
                        $ongoingSession = $class->sessions->where('status', 'ongoing')->first();
                    @endphp
                    @if($ongoingSession)
                        <div class="pt-2 border-t border-zinc-100 dark:border-zinc-700">
                            <div class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-1.5">Current Session</div>
                            <div class="text-sm text-zinc-700 dark:text-zinc-300 mb-2">
                                {{ $ongoingSession->formatted_date_time }}
                            </div>

                            <div
                                x-data="sessionTimer('{{ $ongoingSession->started_at ? $ongoingSession->started_at->toISOString() : now()->toISOString() }}')"
                                x-init="startTimer()"
                                class="flex items-center gap-2 mb-2 p-2 bg-amber-50 dark:bg-amber-500/10 rounded-md border border-amber-200 dark:border-amber-500/20"
                            >
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 bg-amber-500 rounded-full animate-pulse"></div>
                                    <span class="text-xs font-medium text-amber-800 dark:text-amber-400">Running:</span>
                                    <span class="text-sm font-mono font-semibold text-amber-900 dark:text-amber-300" x-text="formattedTime"></span>
                                </div>
                            </div>

                            @if($ongoingSession->hasBookmark())
                                <div class="mb-2 p-2 bg-zinc-50 dark:bg-zinc-700/50 rounded-md">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <flux:icon.bookmark class="h-3 w-3 text-zinc-500" />
                                        <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Progress</span>
                                    </div>
                                    <div class="text-sm text-zinc-700 dark:text-zinc-300 font-medium">
                                        {{ $ongoingSession->bookmark }}
                                    </div>
                                </div>
                            @endif

                            <flux:button
                                wire:click="openCompletionModal({{ $ongoingSession->id }})"
                                variant="filled"
                                size="sm"
                                class="w-full"
                                icon="check"
                            >
                                Complete
                            </flux:button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Sessions Management - Full Width -->
    @if($this->total_sessions_count > 0)
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
            <div class="overflow-hidden">
                <div class="px-5 py-3.5 border-b border-zinc-100 dark:border-zinc-700 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Sessions</h3>
                    <flux:button variant="primary" size="sm" icon="plus" wire:click="openCreateSessionModal">
                        Add Session
                    </flux:button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Date & Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Duration</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">KPI</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Bookmark</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Attendance</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500  uppercase tracking-wider">Allowance</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500  uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-zinc-800">
                            @php $hasAnySessions = count($this->sessions_by_month) > 0; @endphp
                            @if($hasAnySessions)
                                @foreach($this->sessions_by_month as $monthData)
                                    <!-- Month Header Row -->
                                    <tr class="bg-gray-50  border-t-2 border-gray-300">
                                        <td colspan="8" class="px-6 py-3">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-3">
                                                    <flux:icon.calendar class="h-5 w-5 text-gray-500" />
                                                    <span class="font-semibold text-gray-700">{{ $monthData['month_name'] }} {{ $monthData['year'] }}</span>
                                                    <flux:badge size="sm" variant="outline">{{ $monthData['stats']['total'] }} sessions</flux:badge>
                                                </div>
                                                <div class="flex gap-3 text-sm text-gray-500">
                                                    @if($monthData['stats']['completed'] > 0)
                                                        <span class="text-green-600">✓ {{ $monthData['stats']['completed'] }} completed</span>
                                                    @endif
                                                    @if($monthData['stats']['upcoming'] > 0)
                                                        <span class="text-blue-600">📅 {{ $monthData['stats']['upcoming'] }} upcoming</span>
                                                    @endif
                                                    @if($monthData['stats']['ongoing'] > 0)
                                                        <span class="text-yellow-600">▶️ {{ $monthData['stats']['ongoing'] }} running</span>
                                                    @endif
                                                    @if($monthData['stats']['cancelled'] > 0)
                                                        <span class="text-red-600">✗ {{ $monthData['stats']['cancelled'] }} cancelled</span>
                                                    @endif
                                                    @if($monthData['stats']['no_show'] > 0)
                                                        <span class="text-orange-600">👤 {{ $monthData['stats']['no_show'] }} no show</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Sessions for this month -->
                                    @foreach($monthData['sessions'] as $session)
                                        <tr class="divide-y divide-gray-200  hover:bg-gray-50 :bg-gray-800/50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $session->formatted_date_time }}
                                                </div>
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <div class="space-y-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-gray-600">Target:</span>
                                                        <span class="font-medium">{{ $session->formatted_duration }}</span>
                                                    </div>
                                                    @if($session->formatted_actual_duration)
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-gray-600">Actual:</span>
                                                            <span class="font-medium {{ $session->meetsKpi() === true ? 'text-green-700' : ($session->meetsKpi() === false ? 'text-red-700' : 'text-gray-700') }}">
                                                                {{ $session->formatted_actual_duration }}
                                                            </span>
                                                        </div>
                                                        <div class="text-xs {{ $session->meetsKpi() === true ? 'text-green-600' : ($session->meetsKpi() === false ? 'text-red-600' : 'text-gray-500') }}">
                                                            {{ $session->duration_comparison }}
                                                        </div>
                                                    @elseif($session->isOngoing())
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-gray-600">Current:</span>
                                                            <span
                                                                x-data="sessionTimer('{{ $session->started_at ? $session->started_at->toISOString() : now()->toISOString() }}')"
                                                                x-init="startTimer()"
                                                                class="font-mono text-yellow-700"
                                                                x-text="formattedTime"
                                                            ></span>
                                                        </div>
                                                    @else
                                                        <div class="text-xs text-gray-400">Not started yet</div>
                                                    @endif
                                                </div>
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                                @if($session->isCompleted() && $session->meetsKpi() !== null)
                                                    <div class="flex flex-col items-center gap-1">
                                                        <flux:badge size="sm" :class="$session->kpi_badge_class">
                                                            {{ $session->meetsKpi() ? 'Met' : 'Missed' }}
                                                        </flux:badge>
                                                        @if(!$session->meetsKpi())
                                                            <div class="text-xs text-gray-500">
                                                                {{ $session->duration_comparison }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                @elseif($session->isOngoing())
                                                    <flux:badge size="sm" variant="outline" class="animate-pulse">
                                                        In Progress
                                                    </flux:badge>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>

                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($session->isOngoing())
                                                    <div 
                                                        x-data="sessionTimer('{{ $session->started_at ? $session->started_at->toISOString() : now()->toISOString() }}')" 
                                                        x-init="startTimer()"
                                                        class="flex items-center gap-2"
                                                    >
                                                        <div class="flex items-center gap-1">
                                                            <div class="w-2 h-2 bg-yellow-500 rounded-full animate-pulse"></div>
                                                            <span class="text-sm font-medium text-yellow-800">Running</span>
                                                        </div>
                                                        <span class="text-sm font-mono font-semibold text-yellow-900" x-text="formattedTime"></span>
                                                    </div>
                                                @else
                                                    <flux:badge size="sm" :class="match($session->status) {
                                                        'completed' => 'badge-green',
                                                        'scheduled' => 'badge-blue',
                                                        'ongoing' => 'badge-yellow',
                                                        'cancelled' => 'badge-red',
                                                        'no_show' => 'badge-orange',
                                                        'rescheduled' => 'badge-purple',
                                                        default => 'badge-gray'
                                                    }">
                                                        {{ match($session->status) {
                                                            'no_show' => 'No Show',
                                                            'rescheduled' => 'Rescheduled',
                                                            default => ucfirst($session->status)
                                                        } }}
                                                    </flux:badge>
                                                @endif
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                @if($session->hasBookmark())
                                                    <div class="flex items-center gap-2 group" title="{{ $session->bookmark }}">
                                                        <flux:icon.bookmark class="h-4 w-4 text-amber-500" />
                                                        <span class="text-gray-900">{{ $session->formatted_bookmark }}</span>
                                                        @if($session->isOngoing())
                                                            <flux:button 
                                                                wire:click="openSessionModal({{ $session->id }})"
                                                                variant="ghost" 
                                                                size="sm" 
                                                                icon="pencil"
                                                                class="opacity-0 group-hover:opacity-100 transition-opacity text-amber-600 hover:text-amber-800"
                                                            />
                                                        @endif
                                                    </div>
                                                @else
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-gray-400">—</span>
                                                        @if($session->isOngoing())
                                                            <flux:button 
                                                                wire:click="openSessionModal({{ $session->id }})"
                                                                variant="ghost" 
                                                                size="sm" 
                                                                icon="plus"
                                                                class="text-amber-600 hover:text-amber-800"
                                                            >
                                                                Add
                                                            </flux:button>
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                @php
                                                    $presentCount = $session->attendances->where('status', 'present')->count();
                                                    $totalCount = $session->attendances->count();
                                                    $attendanceRate = $totalCount > 0 ? round(($presentCount / $totalCount) * 100) : 0;
                                                @endphp
                                                
                                                @if($totalCount == 0)
                                                    <span class="text-gray-400">—</span>
                                                @elseif($totalCount == 1)
                                                    @if($presentCount == 1)
                                                        <span class="text-green-600 text-lg">✓</span>
                                                    @else
                                                        <span class="text-red-600 text-lg">✗</span>
                                                    @endif
                                                @elseif($totalCount <= 5)
                                                    <div class="flex items-center gap-1">
                                                        @foreach($session->attendances as $att)
                                                            <div class="w-2 h-2 rounded-full {{ $att->status == 'present' ? 'bg-green-500' : ($att->status == 'late' ? 'bg-yellow-500' : 'bg-gray-300') }}" 
                                                                 title="{{ $att->student->fullName }}: {{ ucfirst($att->status) }}"></div>
                                                        @endforeach
                                                        <span class="text-xs text-gray-600 ml-1">{{ $presentCount }}</span>
                                                    </div>
                                                @else
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-sm font-semibold {{ $attendanceRate >= 80 ? 'text-green-600' : ($attendanceRate >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                                                            {{ $presentCount }}
                                                        </span>
                                                        <div class="w-12 h-1.5 bg-gray-200 rounded-full">
                                                            <div class="h-full rounded-full {{ $attendanceRate >= 80 ? 'bg-green-500' : ($attendanceRate >= 60 ? 'bg-amber-500' : 'bg-red-500') }}" 
                                                                 style="width: {{ $attendanceRate }}%"></div>
                                                        </div>
                                                        <span class="text-xs text-gray-500">/ {{ $totalCount }}</span>
                                                    </div>
                                                @endif
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                @if($session->isCompleted())
                                                    <span class="font-medium text-green-600">
                                                        RM {{ number_format($session->getTeacherAllowanceAmount(), 2) }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                <div class="flex items-center justify-end gap-2">
                                                    @if($session->isScheduled())
                                                        <flux:button 
                                                            wire:click="markSessionAsOngoing({{ $session->id }})"
                                                            variant="ghost" 
                                                            size="sm" 
                                                            icon="play"
                                                            class="text-yellow-600 hover:text-yellow-800"
                                                        >
                                                            Start
                                                        </flux:button>
                                                        
                                                        <flux:button 
                                                            wire:click="openCompletionModal({{ $session->id }})"
                                                            variant="ghost" 
                                                            size="sm" 
                                                            icon="check"
                                                            class="text-green-600 hover:text-green-800"
                                                        >
                                                            Complete
                                                        </flux:button>
                                                        
                                                        <flux:button 
                                                            wire:click="markSessionAsNoShow({{ $session->id }})"
                                                            variant="ghost" 
                                                            size="sm" 
                                                            icon="user-minus"
                                                            class="text-orange-600 hover:text-orange-800"
                                                        >
                                                            No Show
                                                        </flux:button>
                                                        
                                                        <flux:button 
                                                            wire:click="markSessionAsCancelled({{ $session->id }})"
                                                            variant="ghost" 
                                                            size="sm" 
                                                            icon="x-mark"
                                                            class="text-red-600 hover:text-red-800"
                                                        >
                                                            Cancel
                                                        </flux:button>
                                                        
                                                    @elseif($session->isOngoing())
                                                        <flux:button 
                                                            wire:click="openSessionModal({{ $session->id }})"
                                                            variant="ghost" 
                                                            size="sm" 
                                                            icon="users"
                                                            class="text-blue-600 hover:text-blue-800"
                                                        >
                                                            Manage
                                                        </flux:button>
                                                        
                                                        <flux:button 
                                                            wire:click="openCompletionModal({{ $session->id }})"
                                                            variant="ghost" 
                                                            size="sm" 
                                                            icon="check"
                                                            class="text-green-600 hover:text-green-800"
                                                        >
                                                            Complete
                                                        </flux:button>
                                                        
                                                        <flux:button 
                                                            wire:click="markSessionAsNoShow({{ $session->id }})"
                                                            variant="ghost" 
                                                            size="sm" 
                                                            icon="user-minus"
                                                            class="text-orange-600 hover:text-orange-800"
                                                        >
                                                            No Show
                                                        </flux:button>
                                                        
                                                    @elseif($session->isCompleted())
                                                        <flux:button 
                                                            wire:click="openAttendanceViewModal({{ $session->id }})"
                                                            variant="ghost" 
                                                            size="sm" 
                                                            icon="eye"
                                                            class="text-blue-600 hover:text-blue-800"
                                                        >
                                                            View Attendance
                                                        </flux:button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center">
                                        <div class="text-gray-500">
                                            <flux:icon.calendar class="mx-auto h-8 w-8 text-gray-400 mb-4" />
                                            <p>No sessions scheduled yet</p>
                                            <flux:button variant="primary" size="sm" class="mt-3" icon="plus" wire:click="openCreateSessionModal">
                                                Schedule First Session
                                            </flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <!-- No Sessions - Create First Session -->
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-8 text-center">
            <flux:icon.calendar class="mx-auto h-10 w-10 text-zinc-300 dark:text-zinc-600 mb-3" />
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-1">No Sessions Scheduled</h3>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-4">Create the first session to get started.</p>
            <flux:button variant="primary" size="sm" icon="plus" wire:click="openCreateSessionModal">
                Create First Session
            </flux:button>
        </div>
    @endif
        </div>
        <!-- End Overview Tab -->

        <!-- Students Tab -->
        <div class="{{ $activeTab === 'students' ? 'block' : 'hidden' }}">
            <!-- Student Sub-Tabs -->
            <div class="inline-flex items-center rounded-md bg-zinc-100 dark:bg-zinc-700/50 p-0.5 mb-4">
                <button
                    wire:click="$set('studentSubTab', 'enrolled')"
                    class="inline-flex items-center gap-1.5 rounded px-3 py-1.5 text-sm font-medium transition-all {{ $studentSubTab === 'enrolled' ? 'bg-white dark:bg-zinc-600 text-zinc-900 dark:text-zinc-100 shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200' }}"
                >
                    Enrolled
                    <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-semibold rounded-full {{ $studentSubTab === 'enrolled' ? 'bg-zinc-900 dark:bg-zinc-100 text-white dark:text-zinc-900' : 'bg-zinc-200 dark:bg-zinc-600 text-zinc-600 dark:text-zinc-300' }}">
                        {{ $class->activeStudents->count() }}
                    </span>
                </button>
                <button
                    wire:click="$set('studentSubTab', 'approvals')"
                    class="inline-flex items-center gap-1.5 rounded px-3 py-1.5 text-sm font-medium transition-all {{ $studentSubTab === 'approvals' ? 'bg-white dark:bg-zinc-600 text-zinc-900 dark:text-zinc-100 shadow-sm' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200' }}"
                >
                    Approvals
                    @if($this->pendingApprovals->count() > 0)
                        <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-semibold rounded-full bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400">
                            {{ $this->pendingApprovals->count() }}
                        </span>
                    @endif
                </button>
            </div>

            <!-- Enrolled Students Sub-Tab -->
            <div class="{{ $studentSubTab === 'enrolled' ? 'block' : 'hidden' }}">
            @if($class->activeStudents->count() > 0)
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 overflow-hidden">
                    <!-- Header -->
                    <div class="px-4 py-3 border-b border-zinc-100 dark:border-zinc-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Enrolled Students</h3>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $class->activeStudents->count() }} enrolled
                                    @if($class->max_capacity)
                                        / {{ $class->max_capacity }} max
                                    @endif
                                </span>
                                @if($class->max_capacity)
                                    @php $capacityPercent = round(($class->activeStudents->count() / $class->max_capacity) * 100); @endphp
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-16 bg-zinc-200 dark:bg-zinc-600 rounded-full h-1">
                                            <div class="h-1 rounded-full transition-all {{ $capacityPercent >= 90 ? 'bg-red-500' : ($capacityPercent >= 70 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ min($capacityPercent, 100) }}%"></div>
                                        </div>
                                        <span class="text-[11px] font-medium tabular-nums {{ $capacityPercent >= 90 ? 'text-red-600 dark:text-red-400' : ($capacityPercent >= 70 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400') }}">{{ $capacityPercent }}%</span>
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-center gap-1.5">
                                <flux:button variant="ghost" size="sm" wire:click="openImportStudentModal" icon="arrow-up-tray">Import</flux:button>
                                <flux:button variant="ghost" size="sm" wire:click="openCreateStudentModal" icon="plus">Create</flux:button>
                                <flux:button variant="primary" size="sm" wire:click="openEnrollStudentsModal" icon="user-plus">Enroll</flux:button>
                            </div>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="px-4 py-2.5 border-b border-zinc-100 dark:border-zinc-700">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 min-w-0">
                                <flux:input wire:model.live.debounce.300ms="enrolledStudentSearch" placeholder="Search students..." icon="magnifying-glass" size="sm" />
                            </div>
                            <div class="w-32 shrink-0">
                                <flux:select wire:model.live="studentsPerPage" size="sm">
                                    <flux:select.option value="20">20 / page</flux:select.option>
                                    <flux:select.option value="50">50 / page</flux:select.option>
                                    <flux:select.option value="100">100 / page</flux:select.option>
                                    <flux:select.option value="200">200 / page</flux:select.option>
                                </flux:select>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50/80 dark:bg-zinc-800/80">
                                    <th class="px-4 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Student</th>
                                    <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Enrolled</th>
                                    <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Order ID</th>
                                    <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Attendance</th>
                                    <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Status</th>
                                    <th class="px-3 py-2 w-12"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                                @forelse($this->filtered_enrolled_students as $classStudent)
                                    @php
                                        $student = $classStudent->student;
                                        $completedSessions = $this->completed_sessions_count;
                                        $studentAttendances = collect();
                                        foreach($class->sessions as $session) {
                                            $attendance = $session->attendances->where('student_id', $student->id)->first();
                                            if($attendance) { $studentAttendances->push($attendance); }
                                        }
                                        $presentCount = $studentAttendances->where('status', 'present')->count();
                                        $totalRecords = $studentAttendances->count();
                                        $attendanceRate = $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 1) : 0;
                                    @endphp
                                    <tr wire:key="enrolled-{{ $classStudent->id }}" class="group hover:bg-zinc-50/50 dark:hover:bg-zinc-700/30 transition-colors">
                                        <td class="px-4 py-2.5 whitespace-nowrap">
                                            <div class="flex items-center gap-2.5">
                                                <flux:avatar size="xs" :name="$student->fullName" />
                                                <div>
                                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $student->fullName }}</div>
                                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $student->phone ?? '-' }}</div>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="px-3 py-2.5 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ $classStudent->enrolled_at->format('M d, Y') }}
                                        </td>

                                        <td class="px-3 py-2.5 whitespace-nowrap">
                                            <input
                                                type="text"
                                                value="{{ $classStudent->order_id }}"
                                                placeholder="--"
                                                class="w-20 px-1.5 py-0.5 text-xs font-mono border border-transparent rounded bg-zinc-50 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 hover:border-zinc-300 dark:hover:border-zinc-500 focus:border-blue-500 focus:bg-white dark:focus:bg-zinc-600 focus:ring-1 focus:ring-blue-500 focus:outline-none transition-all"
                                                wire:blur="updateStudentOrderId({{ $classStudent->id }}, $event.target.value)"
                                                wire:keydown.enter="updateStudentOrderId({{ $classStudent->id }}, $event.target.value)"
                                            />
                                        </td>

                                        <td class="px-3 py-2.5 whitespace-nowrap">
                                            @if($totalRecords > 0)
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-medium tabular-nums {{ $attendanceRate >= 80 ? 'text-emerald-600 dark:text-emerald-400' : ($attendanceRate >= 60 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">{{ $attendanceRate }}%</span>
                                                    <div class="w-14 bg-zinc-200 dark:bg-zinc-600 rounded-full h-1">
                                                        <div class="h-1 rounded-full transition-all {{ $attendanceRate >= 80 ? 'bg-emerald-500' : ($attendanceRate >= 60 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ $attendanceRate }}%"></div>
                                                    </div>
                                                    <span class="text-[11px] tabular-nums text-zinc-400 dark:text-zinc-500">{{ $presentCount }}/{{ $totalRecords }}</span>
                                                </div>
                                            @else
                                                <span class="text-[11px] text-zinc-400 dark:text-zinc-500">--</span>
                                            @endif
                                        </td>

                                        <td class="px-3 py-2.5 whitespace-nowrap">
                                            <flux:badge size="sm" color="green">Active</flux:badge>
                                        </td>

                                        <td class="px-3 py-2.5 whitespace-nowrap text-right">
                                            <flux:dropdown position="bottom" align="end">
                                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" class="opacity-0 group-hover:opacity-100 transition-opacity" />
                                                <flux:menu class="min-w-40">
                                                    <flux:menu.item icon="eye" wire:click="viewStudent({{ $classStudent->id }})">View</flux:menu.item>
                                                    <flux:menu.item icon="pencil" wire:click="editStudent({{ $classStudent->id }})">Edit</flux:menu.item>
                                                    <flux:separator />
                                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmUnenroll({{ $classStudent->id }})">Unenroll</flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-10 text-center">
                                            <flux:icon.magnifying-glass class="mx-auto h-6 w-6 text-zinc-300 dark:text-zinc-600 mb-2" />
                                            <p class="text-sm text-zinc-500 dark:text-zinc-400">No students found matching "{{ $enrolledStudentSearch }}"</p>
                                            <flux:button variant="ghost" size="sm" class="mt-2" wire:click="$set('enrolledStudentSearch', '')">Clear Search</flux:button>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($this->filtered_enrolled_students->hasPages())
                        <div class="px-4 py-2.5 border-t border-zinc-200 dark:border-zinc-700">
                            {{ $this->filtered_enrolled_students->links() }}
                        </div>
                    @endif
                </div>
            @elseif($class->isDraft() || $class->isActive())
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-8 text-center">
                    <div class="mx-auto w-10 h-10 rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center mb-3">
                        <flux:icon.users class="h-5 w-5 text-zinc-400 dark:text-zinc-500" />
                    </div>
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-1">No Students Enrolled</h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-4">This class doesn't have any students yet.</p>
                    <flux:button variant="primary" size="sm" icon="user-plus" wire:click="openEnrollStudentsModal">Enroll Students</flux:button>
                </div>
            @endif

            <!-- Eligible Students -->
            @if($this->eligible_enrollments->count() > 0)
                <div class="mt-4 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 overflow-hidden">
                    <div class="px-4 py-3 border-b border-zinc-100 dark:border-zinc-700">
                        <div class="flex items-center justify-between mb-2.5">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Active Enrollments</h3>
                                <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-semibold rounded-full bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400">{{ $this->eligible_enrollments->count() }}</span>
                            </div>
                            @if(count($selectedEligibleStudents) > 0)
                                <flux:button variant="primary" size="sm" wire:click="enrollSelectedEligibleStudents" icon="user-plus">
                                    Enroll Selected ({{ count($selectedEligibleStudents) }})
                                </flux:button>
                            @endif
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-2.5">Students with active course enrollment not yet in this class</p>
                        <flux:input wire:model.live.debounce.300ms="eligibleStudentSearch" placeholder="Search students..." icon="magnifying-glass" size="sm" />
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50/80 dark:bg-zinc-800/80">
                                    <th class="w-10 px-4 py-2 text-left">
                                        <flux:checkbox
                                            wire:click="$set('selectedEligibleStudents', {{ $this->eligible_enrollments->count() > 0 && count($selectedEligibleStudents) === $this->eligible_enrollments->count() ? '[]' : json_encode($this->eligible_enrollments->pluck('id')->toArray()) }})"
                                            :checked="$this->eligible_enrollments->count() > 0 && count($selectedEligibleStudents) === $this->eligible_enrollments->count()"
                                        />
                                    </th>
                                    <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Student</th>
                                    <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Enrollment Date</th>
                                    <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Status</th>
                                    <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Payment</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                                @forelse($this->eligible_enrollments as $enrollment)
                                    @php $student = $enrollment->student; @endphp
                                    <tr wire:key="eligible-{{ $enrollment->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-700/30 transition-colors">
                                        <td class="w-10 px-4 py-2.5 whitespace-nowrap">
                                            <flux:checkbox wire:click="toggleEligibleStudent({{ $enrollment->id }})" :checked="in_array($enrollment->id, $selectedEligibleStudents)" />
                                        </td>
                                        <td class="px-3 py-2.5 whitespace-nowrap">
                                            <div class="flex items-center gap-2.5">
                                                <flux:avatar size="xs" :name="$student->fullName" />
                                                <div>
                                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $student->fullName }}</div>
                                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $student->phone ?? '-' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ $enrollment->enrollment_date->format('M d, Y') }}
                                        </td>
                                        <td class="px-3 py-2.5 whitespace-nowrap">
                                            @if($enrollment->status === 'active')
                                                <flux:badge size="sm" color="green">Active</flux:badge>
                                            @elseif($enrollment->status === 'enrolled')
                                                <flux:badge size="sm" color="blue">Enrolled</flux:badge>
                                            @else
                                                <flux:badge size="sm">{{ ucfirst($enrollment->status) }}</flux:badge>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                            @if($enrollment->payment_method_type === 'card')
                                                <span class="flex items-center gap-1"><flux:icon name="credit-card" class="w-3.5 h-3.5" /> Card</span>
                                            @elseif($enrollment->payment_method_type === 'manual')
                                                <span class="flex items-center gap-1"><flux:icon name="banknotes" class="w-3.5 h-3.5" /> Manual</span>
                                            @else
                                                <span class="text-zinc-400 dark:text-zinc-500">Not set</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-10 text-center">
                                            <flux:icon.magnifying-glass class="mx-auto h-6 w-6 text-zinc-300 dark:text-zinc-600 mb-2" />
                                            <p class="text-sm text-zinc-500 dark:text-zinc-400">No students found matching "{{ $eligibleStudentSearch }}"</p>
                                            <flux:button variant="ghost" size="sm" class="mt-2" wire:click="$set('eligibleStudentSearch', '')">Clear Search</flux:button>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
            </div>
            <!-- End Enrolled Students Sub-Tab -->

            <!-- Approval List Sub-Tab -->
            <div class="{{ $studentSubTab === 'approvals' ? 'block' : 'hidden' }}">
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 overflow-hidden">
                    <div class="px-4 py-3 border-b border-zinc-100 dark:border-zinc-700">
                        <div class="flex items-center justify-between mb-2.5">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Pending Approvals</h3>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Students from orders awaiting enrollment approval</p>
                            </div>
                            @if($this->pendingApprovals->count() > 0 && count($selectedApprovalIds) > 0)
                                <div class="flex gap-1.5">
                                    <flux:button variant="primary" size="sm" wire:click="bulkApproveAssignments">Approve ({{ count($selectedApprovalIds) }})</flux:button>
                                    <flux:button variant="danger" size="sm" wire:click="bulkRejectAssignments">Reject ({{ count($selectedApprovalIds) }})</flux:button>
                                </div>
                            @endif
                        </div>
                        <flux:input wire:model.live.debounce.300ms="approvalSearch" placeholder="Search by name, phone, or order..." icon="magnifying-glass" size="sm" />
                    </div>

                    @if($this->pendingApprovals->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50/80 dark:bg-zinc-800/80">
                                        <th class="w-10 px-4 py-2">
                                            <flux:checkbox
                                                wire:click="$set('selectedApprovalIds', count($selectedApprovalIds) === $this->pendingApprovals->count() ? [] : $this->pendingApprovals->pluck('id')->toArray())"
                                                :checked="count($selectedApprovalIds) > 0 && count($selectedApprovalIds) === $this->pendingApprovals->count()"
                                            />
                                        </th>
                                        <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Student</th>
                                        <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Order</th>
                                        <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Assigned By</th>
                                        <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Date</th>
                                        <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Sub</th>
                                        <th class="px-3 py-2 text-left text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                                    @foreach($this->pendingApprovals as $approval)
                                        <tr wire:key="approval-{{ $approval->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-700/30 transition-colors">
                                            <td class="w-10 px-4 py-2.5">
                                                <flux:checkbox wire:click="toggleApprovalSelection({{ $approval->id }})" :checked="in_array($approval->id, $selectedApprovalIds)" />
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <div class="flex items-center gap-2.5">
                                                    <flux:avatar size="xs" name="{{ $approval->student->user?->name ?? $approval->student->name ?? 'Unknown' }}" />
                                                    <div>
                                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $approval->student->user?->name ?? $approval->student->name ?? 'Unknown' }}</p>
                                                        <p class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $approval->student->phone ?? $approval->student->user?->phone ?? '' }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <a href="{{ route('admin.orders.show', $approval->productOrder) }}" class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 font-medium">
                                                    {{ $approval->productOrder->order_number }}
                                                </a>
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ $approval->assignedByUser->name }}</p>
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $approval->created_at->format('M d, Y') }}</p>
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <flux:switch wire:click="toggleApprovalSubscription({{ $approval->id }})" :checked="$approvalSubscriptionToggles[$approval->id] ?? false" />
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <div class="flex gap-1">
                                                    <flux:button variant="primary" size="xs" wire:click="approveAssignment({{ $approval->id }})">Approve</flux:button>
                                                    <flux:button variant="danger" size="xs" wire:click="rejectAssignment({{ $approval->id }})">Reject</flux:button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-10 px-4">
                            <flux:icon.clipboard-document-check class="h-8 w-8 text-zinc-300 dark:text-zinc-600 mx-auto mb-2" />
                            @if(empty($approvalSearch))
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">No pending approvals</p>
                                <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-0.5">Assignments from orders will appear here</p>
                            @else
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">No approvals matching "{{ $approvalSearch }}"</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
            <!-- End Approval List Sub-Tab -->
        </div>
        <!-- End Students Tab -->

        <!-- Timetable Tab -->
        <div class="{{ $activeTab === 'timetable' ? 'block' : 'hidden' }}">
            @if($class->timetable)
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <div class="border-b border-zinc-200 dark:border-zinc-700 px-5 py-3">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Class Timetable</h3>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            @if($class->timetable->recurrence_pattern === 'monthly')
                                Monthly recurring schedule with different days per week
                            @elseif($class->timetable->recurrence_pattern === 'bi_weekly')
                                Bi-weekly recurring schedule for this class
                            @else
                                Weekly recurring schedule for this class
                            @endif
                        </p>
                    </div>

                    <div class="p-5">
                        <!-- Timetable Info -->
                        <div class="mb-5 grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                                <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Recurrence Pattern</span>
                                <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ ucfirst(str_replace('_', ' ', $class->timetable->recurrence_pattern)) }}
                                </p>
                            </div>

                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                                <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Total Sessions</span>
                                <p class="mt-1 text-sm font-medium tabular-nums text-zinc-900 dark:text-zinc-100">
                                    {{ $class->timetable->total_sessions ?? 'Unlimited' }}
                                </p>
                            </div>

                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                                <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Duration</span>
                                <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $class->formatted_duration }}
                                </p>
                            </div>
                        </div>

                        <!-- Date Range -->
                        <div class="mb-5 flex items-center gap-4 rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <flux:icon.calendar-days class="h-4 w-4 text-zinc-400 dark:text-zinc-500" />
                                <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Start</span>
                                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $class->timetable->start_date->format('M d, Y') }}</span>
                            </div>

                            @if($class->timetable->end_date)
                                <div class="h-4 w-px bg-zinc-200 dark:bg-zinc-700"></div>
                                <div class="flex items-center gap-2">
                                    <flux:icon.calendar class="h-4 w-4 text-zinc-400 dark:text-zinc-500" />
                                    <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">End</span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $class->timetable->end_date->format('M d, Y') }}</span>
                                </div>
                            @endif
                        </div>

                        <!-- Monthly Calendar View -->
                        <div class="overflow-x-auto">
                            <div class="inline-block min-w-full">
                                <!-- Calendar Header -->
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Monthly Schedule</h4>
                                    <div class="flex items-center gap-1">
                                        <flux:button size="sm" variant="ghost" wire:click="previousMonth">
                                            <flux:icon.chevron-left class="h-4 w-4" />
                                        </flux:button>
                                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100 px-3 tabular-nums">
                                            {{ $this->current_month_name }}
                                        </span>
                                        <flux:button size="sm" variant="ghost" wire:click="nextMonth">
                                            <flux:icon.chevron-right class="h-4 w-4" />
                                        </flux:button>
                                    </div>
                                </div>

                                <!-- Days of Week Header -->
                                <div class="grid grid-cols-7 gap-px mb-px">
                                    @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
                                        <div class="text-center py-1.5 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50 rounded-sm">
                                            {{ $day }}
                                        </div>
                                    @endforeach
                                </div>

                                <!-- Calendar Grid -->
                                <div class="grid grid-cols-7 gap-px">
                                    @foreach(collect($this->monthly_calendar_data)->chunk(7) as $week)
                                        @foreach($week as $day)
                                            <div class="min-h-22 border border-zinc-100 dark:border-zinc-800 rounded-sm p-1 {{ $day['isCurrentMonth'] ? 'bg-white dark:bg-zinc-900' : 'bg-zinc-50 dark:bg-zinc-800/30' }} {{ $day['isToday'] ? 'ring-1 ring-zinc-900 dark:ring-zinc-300' : '' }}">
                                                <!-- Date Number -->
                                                <div class="text-xs mb-1 {{ $day['isCurrentMonth'] ? 'text-zinc-700 dark:text-zinc-300' : 'text-zinc-300 dark:text-zinc-600' }} {{ $day['isToday'] ? 'font-bold text-zinc-900 dark:text-zinc-100' : 'font-medium' }}">
                                                    {{ $day['date']->day }}
                                                </div>

                                                <!-- Sessions for this date -->
                                                @if($day['sessions']->count() > 0)
                                                    @foreach($day['sessions'] as $session)
                                                        <div class="mb-0.5 px-1 py-0.5 text-[10px] font-medium rounded tabular-nums {{
                                                            $session->status === 'completed' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' :
                                                            ($session->status === 'cancelled' ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' :
                                                            'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300')
                                                        }}">
                                                            {{ \Carbon\Carbon::parse($session->start_time)->format('g:iA') }}
                                                        </div>
                                                    @endforeach
                                                @else
                                                    @if($day['isCurrentMonth'])
                                                        <!-- Check if this day has scheduled classes from timetable -->
                                                        @php
                                                            $dayName = strtolower($day['date']->format('l'));
                                                            $scheduledTimes = [];

                                                            if ($class->timetable && $class->timetable->is_active) {
                                                                // Check if date is within timetable range
                                                                $isWithinRange = $class->timetable->isDateWithinRange($day['date']);

                                                                if ($isWithinRange) {
                                                                    if ($class->timetable->recurrence_pattern === 'monthly') {
                                                                        // For monthly pattern, get week of month and check that week's schedule
                                                                        $dayOfMonth = $day['date']->day;
                                                                        if ($dayOfMonth <= 7) {
                                                                            $weekKey = 'week_1';
                                                                        } elseif ($dayOfMonth <= 14) {
                                                                            $weekKey = 'week_2';
                                                                        } elseif ($dayOfMonth <= 21) {
                                                                            $weekKey = 'week_3';
                                                                        } else {
                                                                            $weekKey = 'week_4';
                                                                        }

                                                                        if (isset($class->timetable->weekly_schedule[$weekKey][$dayName]) && !empty($class->timetable->weekly_schedule[$weekKey][$dayName])) {
                                                                            $scheduledTimes = $class->timetable->weekly_schedule[$weekKey][$dayName];
                                                                        }
                                                                    } else {
                                                                        // For weekly/bi-weekly pattern
                                                                        if (isset($class->timetable->weekly_schedule[$dayName]) && !empty($class->timetable->weekly_schedule[$dayName])) {
                                                                            $scheduledTimes = $class->timetable->weekly_schedule[$dayName];
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        @endphp
                                                        @if(!empty($scheduledTimes))
                                                            @foreach($scheduledTimes as $time)
                                                                <div class="mb-0.5 px-1 py-0.5 text-[10px] rounded tabular-nums bg-zinc-50 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">
                                                                    {{ date('g:iA', strtotime($time)) }}
                                                                </div>
                                                            @endforeach
                                                        @endif
                                                    @endif
                                                @endif
                                            </div>
                                        @endforeach
                                    @endforeach
                                </div>

                                <!-- Legend -->
                                <div class="mt-3 flex flex-wrap gap-4 text-[11px]">
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-2 h-2 rounded-full bg-zinc-300 dark:bg-zinc-500"></div>
                                        <span class="text-zinc-500 dark:text-zinc-400">Scheduled</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-2 h-2 rounded-full bg-emerald-500 dark:bg-emerald-400"></div>
                                        <span class="text-zinc-500 dark:text-zinc-400">Completed</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-2 h-2 rounded-full bg-red-500 dark:bg-red-400"></div>
                                        <span class="text-zinc-500 dark:text-zinc-400">Cancelled</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-2 h-2 rounded-full bg-zinc-200 dark:bg-zinc-700"></div>
                                        <span class="text-zinc-500 dark:text-zinc-400">Recurring Schedule</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sessions Generated -->
                    </div>
                </div>
            @else
                <!-- No Timetable -->
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <div class="px-5 py-10 text-center">
                        <flux:icon.calendar class="mx-auto h-8 w-8 text-zinc-300 dark:text-zinc-600 mb-3" />
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-1">No Timetable Configured</h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-4">This class doesn't have a recurring timetable. Sessions are managed individually.</p>
                        <flux:button variant="primary" href="{{ route('classes.edit', $class) }}" icon="plus">
                            Add Timetable
                        </flux:button>
                    </div>
                </div>
            @endif
        </div>
        <!-- End Timetable Tab -->

        <!-- Certificates Tab -->
        <div class="{{ $activeTab === 'certificates' ? 'block' : 'hidden' }}">
            <livewire:admin.certificates.class-certificate-management :class="$class" />
        </div>
        <!-- End Certificates Tab -->

        <!-- Payment Reports Tab -->
        <div class="{{ $activeTab === 'payment-reports' ? 'block' : 'hidden' }}"
             x-data="{
                 storageKey: 'paymentColumnPreferences_{{ $class->id }}_{{ $paymentYear }}',
                 init() {
                     console.log('[Payment Columns] Init called');
                     console.log('[Payment Columns] Storage key:', this.storageKey);

                     // Load saved column preferences from localStorage
                     const savedPreferences = localStorage.getItem(this.storageKey);
                     console.log('[Payment Columns] Loaded from localStorage:', savedPreferences);

                     if (savedPreferences) {
                         try {
                             const preferences = JSON.parse(savedPreferences);
                             console.log('[Payment Columns] Parsed preferences:', preferences);
                             // Set the periods from localStorage
                             $wire.setVisiblePaymentPeriods(preferences);
                             console.log('[Payment Columns] Set visible periods from localStorage');
                         } catch (e) {
                             console.error('[Payment Columns] Error loading payment column preferences:', e);
                             // If error, initialize with all periods
                             $wire.initializeVisiblePaymentPeriods();
                         }
                     } else {
                         console.log('[Payment Columns] No saved preferences, initializing with all periods');
                         // No saved preferences, initialize with all periods
                         $wire.initializeVisiblePaymentPeriods();
                     }
                 },
                 savePreferences(data) {
                     console.log('[Payment Columns] savePreferences called with:', data);
                     console.log('[Payment Columns] Visible periods:', data.visiblePeriods);
                     console.log('[Payment Columns] Storage key:', this.storageKey);

                     try {
                         localStorage.setItem(this.storageKey, JSON.stringify(data.visiblePeriods));
                         console.log('[Payment Columns] Saved to localStorage successfully');

                         // Verify save
                         const verified = localStorage.getItem(this.storageKey);
                         console.log('[Payment Columns] Verified localStorage value:', verified);
                     } catch (e) {
                         console.error('[Payment Columns] Error saving payment column preferences:', e);
                     }
                 }
             }"
             @save-payment-column-preferences.window="savePreferences($event.detail)">
            @if($activeTab === 'payment-reports')
            <!-- Year and Payment Filter -->
            <div class="mb-6 rounded-lg border border-zinc-200 dark:border-zinc-700">
                <div class="border-b border-zinc-200 dark:border-zinc-700 px-5 py-3 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Payment Reports</h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Track student payment history for this class</p>
                    </div>
                    <!-- Column Visibility Manager -->
                    <div class="relative" x-data="{ open: @entangle('showPaymentColumnManager') }">
                        <flux:button variant="outline" size="sm" @click="open = !open">
                            <div class="flex items-center justify-center">
                                <flux:icon icon="view-columns" class="w-4 h-4 mr-1" />
                                Columns
                                <span class="ml-1 text-[11px] text-zinc-400">({{ count($visiblePaymentPeriods) }}/{{ $this->all_payment_period_columns->count() }})</span>
                            </div>
                        </flux:button>

                        <div x-show="open" @click.away="open = false" x-cloak
                             class="absolute right-0 mt-2 w-72 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 z-50"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95">

                            <div class="p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">Column Visibility</h4>
                                    <flux:button variant="ghost" size="sm" wire:click="toggleAllPaymentPeriods">
                                        <div class="text-xs">
                                            {{ count($visiblePaymentPeriods) === $this->all_payment_period_columns->count() ? 'Hide All' : 'Show All' }}
                                        </div>
                                    </flux:button>
                                </div>

                                <div class="space-y-1 max-h-96 overflow-y-auto">
                                    @foreach($this->all_payment_period_columns as $period)
                                        <label class="flex items-center space-x-3 px-2 py-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-700 rounded cursor-pointer">
                                            <flux:checkbox
                                                wire:model.live="visiblePaymentPeriods"
                                                value="{{ $period['label'] }}"
                                            />
                                            <div class="flex-1">
                                                <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ $period['label'] }}</div>
                                                @if($class->course->feeSettings && $class->course->feeSettings->billing_cycle !== 'yearly')
                                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                        {{ $period['period_start']->format('M j') }} - {{ $period['period_end']->format('M j') }}
                                                    </div>
                                                @endif
                                            </div>
                                        </label>
                                    @endforeach
                                </div>

                                @if(count($visiblePaymentPeriods) === 0)
                                    <div class="mt-2 p-2 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded text-xs text-yellow-700 dark:text-yellow-400">
                                        At least one column must be visible
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-5 py-3 space-y-3">
                    <!-- Filters Row -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <div class="w-40 shrink-0">
                            <flux:select wire:model.live="paymentFilter" class="w-full">
                                <flux:select.option value="all">All Students</flux:select.option>
                                <flux:select.option value="active">Active</flux:select.option>
                                <flux:select.option value="canceled">Canceled</flux:select.option>
                                <flux:select.option value="trialing">Trialing</flux:select.option>
                                <flux:select.option value="past_due">Past Due</flux:select.option>
                            </flux:select>
                        </div>
                        <div class="w-40 shrink-0">
                            <flux:select wire:model.live="paymentPicFilter" class="w-full">
                                <flux:select.option value="">All PICs</flux:select.option>
                                @foreach($this->available_pics as $pic)
                                    @if($pic)
                                        <flux:select.option value="{{ $pic->id }}">{{ $pic->name ?? 'N/A' }}</flux:select.option>
                                    @endif
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="w-36 shrink-0">
                            <flux:select wire:model.live="paymentMethodTypeFilter" class="w-full">
                                <flux:select.option value="">All Types</flux:select.option>
                                <flux:select.option value="automatic">Automatic</flux:select.option>
                                <flux:select.option value="manual">Manual</flux:select.option>
                            </flux:select>
                        </div>
                        <div class="w-28 shrink-0">
                            <flux:select wire:model.live="paymentYear" class="w-full">
                                @for($y = now()->year + 1; $y >= now()->year - 5; $y--)
                                    <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                                @endfor
                            </flux:select>
                        </div>
                        <div class="w-36 shrink-0">
                            <flux:select wire:model.live="paymentReportPerPage" class="w-full">
                                <flux:select.option value="20">20 per page</flux:select.option>
                                <flux:select.option value="30">30 per page</flux:select.option>
                                <flux:select.option value="50">50 per page</flux:select.option>
                                <flux:select.option value="100">100 per page</flux:select.option>
                                <flux:select.option value="200">200 per page</flux:select.option>
                                <flux:select.option value="300">300 per page</flux:select.option>
                            </flux:select>
                        </div>
                        <div class="flex-1 min-w-[200px]">
                            <flux:input
                                wire:model.live.debounce.300ms="paymentReportSearch"
                                placeholder="Search student name, phone, or ID..."
                                icon="magnifying-glass"
                                class="w-full"
                            />
                        </div>
                    </div>

                    @if($class->course && $class->course->feeSettings)
                        <div class="p-2.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                            <div class="flex items-center gap-2">
                                <flux:icon.information-circle class="w-4 h-4 text-zinc-400 dark:text-zinc-500 shrink-0" />
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $class->course->feeSettings->billing_cycle_label }} Billing</span>
                                    &middot; {{ $class->course->feeSettings->formatted_fee }} per {{ strtolower($class->course->feeSettings->billing_cycle_label) }}
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Payment Report Table -->
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                @if($this->class_students_for_payment_report->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                    <th class="text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 py-2.5 px-4 sticky left-0 bg-zinc-50 dark:bg-zinc-800 z-10 min-w-[250px]">
                                        Student Name
                                    </th>
                                    <th class="text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 py-2.5 px-4 bg-zinc-50 dark:bg-zinc-800 min-w-[150px] border-l border-zinc-200 dark:border-zinc-700">
                                        PIC
                                    </th>
                                    @foreach($this->visible_payment_period_columns as $period)
                                        <th class="text-center text-[11px] font-medium uppercase tracking-wider text-zinc-400 py-2.5 px-3 min-w-[100px] border-l border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                                            <div>{{ $period['label'] }}</div>
                                            @if($class->course->feeSettings && $class->course->feeSettings->billing_cycle !== 'yearly')
                                                <div class="text-[10px] normal-case tracking-normal text-zinc-400 dark:text-zinc-500 mt-0.5">
                                                    {{ $period['period_start']->format('M j') }} - {{ $period['period_end']->format('M j') }}
                                                </div>
                                            @endif
                                        </th>
                                    @endforeach
                                    <th class="text-center text-[11px] font-medium uppercase tracking-wider text-zinc-400 py-2.5 px-4 min-w-[150px] border-l-2 border-zinc-300 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-800">
                                        <div>Summary</div>
                                        <div class="text-[10px] normal-case tracking-normal text-zinc-400 dark:text-zinc-500 mt-0.5">Paid / Expected</div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->class_students_for_payment_report as $classStudent)
                                    @php
                                        $student = $classStudent->student;
                                        $enrollment = $student->enrollments->first();
                                        $totalPaid = 0;
                                        $totalExpected = 0;
                                        $totalUnpaid = 0;
                                        $hasConsecutiveUnpaid = $this->hasConsecutiveUnpaidMonths($student->id);
                                    @endphp
                                    <tr class="border-b border-gray-100 dark:border-zinc-700 hover:bg-gray-50 dark:hover:bg-zinc-700/50 {{ $hasConsecutiveUnpaid ? 'bg-red-50 dark:bg-red-900/20' : '' }}">
                                        <td class="py-3 px-4 sticky left-0 {{ $hasConsecutiveUnpaid ? 'bg-red-50 dark:bg-red-900/20' : 'bg-white dark:bg-zinc-800' }} z-10 border-r border-gray-100 dark:border-zinc-700">
                                            @if($hasConsecutiveUnpaid)
                                                <div class="flex items-start gap-2 mb-2">
                                                    <div class="inline-flex items-center gap-1 px-2 py-1 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400 rounded-md text-xs font-medium">
                                                        <flux:icon.exclamation-triangle class="w-3 h-3" />
                                                        <span>2+ Months Unpaid</span>
                                                    </div>
                                                </div>
                                            @endif

                                            <div class="flex items-start justify-between gap-2">
                                                <div class="flex-1 min-w-0">
                                                    @if($enrollment)
                                                        <a href="{{ route('enrollments.show', $enrollment) }}"
                                                           wire:navigate
                                                           class="block hover:opacity-80 transition-opacity">
                                                            <div class="font-medium text-blue-600 hover:text-blue-400">{{ $student->user?->name ?? 'N/A' }}</div>
                                                            <div class="text-xs text-gray-600 dark:text-gray-400">{{ $student->phone ?: 'No phone' }}</div>
                                                        </a>
                                                    @else
                                                        <div>
                                                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $student->user?->name ?? 'N/A' }}</div>
                                                            <div class="text-xs text-gray-600 dark:text-gray-400">{{ $student->phone ?: 'No phone' }}</div>
                                                        </div>
                                                    @endif

                                                    {{-- Class Enrollment Date --}}
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                        <flux:icon name="calendar" class="w-3 h-3 inline-block mr-1" />
                                                        Joined: {{ $classStudent->enrolled_at->format('M d, Y') }}
                                                    </div>

                                                    {{-- Next Billing Date - Only for automatic payments --}}
                                                    @if($enrollment && $enrollment->payment_method_type === 'automatic' && in_array($enrollment->subscription_status, ['active', 'trialing']))
                                                        @php
                                                            // Try to get next billing date from stored data or trial end
                                                            $nextBillingDate = $enrollment->getNextPaymentDate() ?? $enrollment->trial_end_at ?? $enrollment->next_payment_date;
                                                        @endphp
                                                        @if($nextBillingDate)
                                                            <div class="text-xs mt-0.5 {{ $enrollment->subscription_status === 'trialing' ? 'text-purple-600' : 'text-blue-600' }}">
                                                                <flux:icon name="arrow-path" class="w-3 h-3 inline-block mr-1" />
                                                                Bills: {{ $nextBillingDate->format('d M Y') }}
                                                                @if($enrollment->subscription_status === 'trialing')
                                                                    <span class="text-purple-500">(trial ends)</span>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    @endif

                                                    <div class="flex items-center gap-2 mt-2">
                                                        @if($enrollment)
                                                            {{-- Subscription Status Badge --}}
                                                            @if($enrollment->subscription_status === 'active')
                                                                <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400 rounded-md text-xs font-medium">
                                                                    <flux:icon name="check-circle" class="w-3 h-3" />
                                                                    <span>Active</span>
                                                                </div>
                                                            @elseif($enrollment->subscription_status === 'canceled')
                                                                <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400 rounded-md text-xs font-medium">
                                                                    <flux:icon name="x-circle" class="w-3 h-3" />
                                                                    <span>Canceled</span>
                                                                </div>
                                                            @elseif($enrollment->subscription_status === 'trialing')
                                                                <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-400 rounded-md text-xs font-medium">
                                                                    <flux:icon name="clock" class="w-3 h-3" />
                                                                    <span>Trial</span>
                                                                </div>
                                                            @elseif($enrollment->subscription_status === 'past_due')
                                                                <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-400 rounded-md text-xs font-medium">
                                                                    <flux:icon name="exclamation-triangle" class="w-3 h-3" />
                                                                    <span>Past Due</span>
                                                                </div>
                                                            @else
                                                                <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 dark:bg-zinc-700 text-gray-700 dark:text-gray-300 rounded-md text-xs font-medium">
                                                                    <flux:icon name="minus-circle" class="w-3 h-3" />
                                                                    <span>{{ ucfirst($enrollment->subscription_status ?? 'Inactive') }}</span>
                                                                </div>
                                                            @endif

                                                            {{-- Payment Method Type Badge --}}
                                                            @if($enrollment->payment_method_type === 'automatic')
                                                                <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-400 rounded-md text-xs font-medium">
                                                                    <flux:icon name="credit-card" class="w-3 h-3" />
                                                                    <span>Auto</span>
                                                                </div>
                                                            @else
                                                                <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-400 rounded-md text-xs font-medium">
                                                                    <flux:icon name="banknotes" class="w-3 h-3" />
                                                                    <span>Manual</span>
                                                                </div>
                                                            @endif
                                                        @endif

                                                        @if($student->phone)
                                                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $student->phone) }}"
                                                               target="_blank"
                                                               class="inline-flex items-center gap-1 text-green-600 hover:text-green-800 transition-colors"
                                                               title="WhatsApp">
                                                                <flux:icon name="chat-bubble-left-right" class="w-4 h-4" />
                                                            </a>
                                                            <a href="tel:{{ $student->phone }}"
                                                               class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 transition-colors"
                                                               title="Call">
                                                                <flux:icon name="phone" class="w-4 h-4" />
                                                            </a>
                                                        @endif

                                                        {{-- Magic Link Indicator --}}
                                                        @php
                                                            $magicLink = $student->magicLinks->first();
                                                            $hasMagicLink = $magicLink && $magicLink->isValid();
                                                        @endphp
                                                        <div wire:key="magic-link-{{ $student->id }}-{{ $hasMagicLink ? $magicLink->id : 'none' }}" class="relative"
                                                             x-data="{
                                                                 copied: false,
                                                                 showMenu: false,
                                                                 copyLink() {
                                                                     @if($hasMagicLink)
                                                                     navigator.clipboard.writeText('{{ $magicLink->getMagicLinkUrl() }}').then(() => {
                                                                         this.copied = true;
                                                                         setTimeout(() => { this.copied = false }, 2000);
                                                                     });
                                                                     @endif
                                                                 }
                                                             }"
                                                             @click.away="showMenu = false">
                                                            @if($hasMagicLink)
                                                                <button
                                                                    @click="copyLink()"
                                                                    @contextmenu.prevent="showMenu = !showMenu"
                                                                    class="relative inline-flex items-center justify-center text-purple-600 hover:text-purple-800 transition-colors cursor-pointer"
                                                                    title="Click to copy magic link (expires {{ $magicLink->expires_at->format('M d, Y') }}) • Right-click for options">
                                                                    <template x-if="!copied">
                                                                        <span class="inline-flex">
                                                                            <flux:icon name="clipboard-document" class="w-4 h-4" />
                                                                        </span>
                                                                    </template>
                                                                    <template x-if="copied">
                                                                        <span class="inline-flex text-green-600">
                                                                            <flux:icon name="clipboard-document-check" class="w-4 h-4" />
                                                                        </span>
                                                                    </template>
                                                                </button>
                                                                {{-- Right-click context menu --}}
                                                                <div x-show="showMenu"
                                                                     x-cloak
                                                                     x-transition:enter="transition ease-out duration-100"
                                                                     x-transition:enter-start="transform opacity-0 scale-95"
                                                                     x-transition:enter-end="transform opacity-100 scale-100"
                                                                     x-transition:leave="transition ease-in duration-75"
                                                                     x-transition:leave-start="transform opacity-100 scale-100"
                                                                     x-transition:leave-end="transform opacity-0 scale-95"
                                                                     class="absolute left-0 top-6 z-50 w-48 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-gray-200 dark:border-zinc-700 py-1">
                                                                    <button @click="copyLink(); showMenu = false"
                                                                            class="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-zinc-700 flex items-center gap-2">
                                                                        <flux:icon name="clipboard-document" class="w-4 h-4" />
                                                                        Copy Link
                                                                    </button>
                                                                    <button wire:click="regenerateMagicLinkForStudent({{ $student->id }})"
                                                                            wire:confirm="This will invalidate the current magic link. The client will need to use the new link. Are you sure?"
                                                                            @click="showMenu = false"
                                                                            class="w-full px-3 py-2 text-left text-sm text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/30 flex items-center gap-2">
                                                                        <flux:icon name="arrow-path" class="w-4 h-4" />
                                                                        Regenerate Link
                                                                    </button>
                                                                    <div class="border-t border-gray-100 dark:border-zinc-700 my-1"></div>
                                                                    <div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                                                                        Expires: {{ $magicLink->expires_at->format('M d, Y') }}
                                                                    </div>
                                                                </div>
                                                            @else
                                                                <button
                                                                    wire:click="generateMagicLinkForStudent({{ $student->id }})"
                                                                    wire:loading.attr="disabled"
                                                                    wire:loading.class="opacity-50"
                                                                    wire:target="generateMagicLinkForStudent({{ $student->id }})"
                                                                    class="inline-flex items-center justify-center text-gray-400 hover:text-purple-600 transition-colors cursor-pointer disabled:cursor-wait"
                                                                    title="Generate Magic Link">
                                                                    <span wire:loading.remove wire:target="generateMagicLinkForStudent({{ $student->id }})">
                                                                        <flux:icon name="link" class="w-4 h-4" />
                                                                    </span>
                                                                    <span wire:loading wire:target="generateMagicLinkForStudent({{ $student->id }})" class="inline-flex">
                                                                        <svg class="animate-spin w-4 h-4 text-purple-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                        </svg>
                                                                    </span>
                                                                </button>
                                                            @endif
                                                        </div>

                                                        {{-- Payment Method Indicator --}}
                                                        @php
                                                            $paymentMethods = $student->user?->paymentMethods ?? collect();
                                                            $hasPaymentMethod = $paymentMethods->where('is_active', true)->count() > 0;
                                                            $defaultPaymentMethod = $paymentMethods->where('is_active', true)->where('is_default', true)->first() ?? $paymentMethods->where('is_active', true)->first();
                                                        @endphp
                                                        @if($hasPaymentMethod && $defaultPaymentMethod)
                                                            <span
                                                                class="inline-flex items-center gap-1 text-green-600"
                                                                title="Card: {{ ucfirst($defaultPaymentMethod->card_brand ?? 'Card') }} •••• {{ $defaultPaymentMethod->card_last_four ?? '****' }}">
                                                                <flux:icon name="credit-card" class="w-4 h-4" />
                                                            </span>
                                                        @else
                                                            <span
                                                                class="inline-flex items-center gap-1 text-gray-300"
                                                                title="No payment method saved">
                                                                <flux:icon name="credit-card" class="w-4 h-4" />
                                                            </span>
                                                        @endif

                                                        {{-- Stripe Dashboard Link --}}
                                                        @if($enrollment?->stripe_subscription_id)
                                                            <a
                                                                href="https://dashboard.stripe.com/subscriptions/{{ $enrollment->stripe_subscription_id }}"
                                                                target="_blank"
                                                                class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 transition-colors"
                                                                title="View Subscription in Stripe">
                                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                                                    <path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/>
                                                                </svg>
                                                            </a>
                                                        @elseif($student->user?->stripe_id)
                                                            <a
                                                                href="https://dashboard.stripe.com/customers/{{ $student->user->stripe_id }}"
                                                                target="_blank"
                                                                class="inline-flex items-center gap-1 text-gray-400 hover:text-indigo-600 transition-colors"
                                                                title="View Customer in Stripe (No subscription)">
                                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                                                    <path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/>
                                                                </svg>
                                                            </a>
                                                        @else
                                                            <span
                                                                class="inline-flex items-center gap-1 text-gray-300"
                                                                title="Not connected to Stripe">
                                                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                                                    <path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/>
                                                                </svg>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>

                                                @if($enrollment)
                                                    <div class="flex items-center gap-1 flex-shrink-0">
                                                        {{-- Only show Activate button for manual subscriptions --}}
                                                        @if($enrollment->subscription_status !== 'active' && $enrollment->manual_payment_required)
                                                            <flux:button
                                                                wire:click="activateEnrollment({{ $enrollment->id }})"
                                                                variant="primary"
                                                                size="sm"
                                                                title="Activate Enrollment">
                                                                <div class="flex items-center justify-center gap-1">
                                                                    <flux:icon name="play" class="w-3 h-3" />
                                                                    <span class="text-xs">Activate</span>
                                                                </div>
                                                            </flux:button>
                                                        @endif
                                                        <flux:button
                                                            wire:click="openEditEnrollmentModal({{ $enrollment->id }})"
                                                            variant="ghost"
                                                            size="sm"
                                                            title="Edit Enrollment">
                                                            <div class="flex items-center justify-center">
                                                                <flux:icon name="pencil" class="w-4 h-4" />
                                                            </div>
                                                        </flux:button>
                                                        @if($enrollment->subscription_status === 'active')
                                                            <flux:button
                                                                wire:click="openCancelSubscriptionModal({{ $enrollment->id }})"
                                                                variant="ghost"
                                                                size="sm"
                                                                title="Cancel Subscription">
                                                                <div class="flex items-center justify-center">
                                                                    <flux:icon name="x-circle" class="w-4 h-4 text-red-600" />
                                                                </div>
                                                            </flux:button>
                                                        @endif
                                                    </div>
                                                @else
                                                    <div class="flex items-center gap-1 flex-shrink-0">
                                                        <flux:button
                                                            wire:click="openCreateFirstEnrollmentModal({{ $student->id }})"
                                                            variant="primary"
                                                            size="sm"
                                                            title="Create First Enrollment">
                                                            <div class="flex items-center justify-center gap-1">
                                                                <flux:icon name="plus-circle" class="w-3 h-3" />
                                                                <span class="text-xs">Create Enrollment</span>
                                                            </div>
                                                        </flux:button>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 {{ $hasConsecutiveUnpaid ? 'bg-red-50 dark:bg-red-900/20' : 'bg-white dark:bg-zinc-800' }} border-l border-gray-100 dark:border-zinc-700">
                                            @if($enrollment && $enrollment->enrolledBy)
                                                <a href="{{ route('enrollments.show', $enrollment) }}"
                                                   wire:navigate
                                                   class="block hover:opacity-80 transition-opacity">
                                                    <div class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">{{ $enrollment->enrolledBy?->name ?? 'N/A' }}</div>
                                                </a>
                                            @else
                                                <div class="text-xs text-gray-400 dark:text-gray-500">N/A</div>
                                            @endif
                                        </td>
                                        @foreach($this->visible_payment_period_columns as $period)
                                            @php
                                                $payment = $this->class_payment_data[$student->id][$period['label']] ?? ['status' => 'no_data', 'paid_amount' => 0, 'expected_amount' => 0];
                                                $totalPaid += $payment['paid_amount'] ?? 0;
                                                $totalExpected += $payment['expected_amount'] ?? 0;
                                                $totalUnpaid += $payment['unpaid_amount'] ?? 0;
                                            @endphp
                                            <td class="py-3 px-3 text-center border-l border-gray-100 dark:border-zinc-700">
                                                @switch($payment['status'])
                                                    @case('paid')
                                                        <div class="space-y-1">
                                                            @if(isset($payment['paid_orders']) && $payment['paid_orders']->count() > 0)
                                                                <a href="{{ route('orders.show', $payment['paid_orders']->first()) }}"
                                                                   class="block hover:opacity-80 cursor-pointer"
                                                                   wire:navigate>
                                                                    <div class="inline-flex items-center justify-center w-6 h-6 bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 rounded-full mb-1">
                                                                        <flux:icon.check class="w-4 h-4" />
                                                                    </div>
                                                                </a>
                                                            @else
                                                                <div class="inline-flex items-center justify-center w-6 h-6 bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 rounded-full mb-1">
                                                                    <flux:icon.check class="w-4 h-4" />
                                                                </div>
                                                            @endif
                                                            <div class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                                RM {{ number_format($payment['paid_amount'] ?? 0, 2) }}
                                                            </div>
                                                        </div>
                                                        @break

                                                    @case('unpaid')
                                                        <div class="space-y-1 cursor-pointer hover:opacity-75 transition-opacity"
                                                             wire:click="openManualPaymentModal({{ $student->id }}, '{{ $period['label'] }}', '{{ $period['period_start']->format('Y-m-d') }}', '{{ $period['period_end']->format('Y-m-d') }}', {{ $payment['unpaid_amount'] ?? 0 }}, {{ $payment['paid_amount'] ?? 0 }}, {{ $payment['expected_amount'] ?? 0 }})">
                                                            <div class="inline-flex items-center justify-center w-6 h-6 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded-full mb-1">
                                                                <flux:icon.exclamation-triangle class="w-4 h-4" />
                                                            </div>
                                                            <div class="text-xs font-medium text-red-600 dark:text-red-400">
                                                                RM {{ number_format($payment['unpaid_amount'] ?? 0, 2) }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Click to pay</div>
                                                        </div>
                                                        @break

                                                    @case('partial_payment')
                                                        <div class="space-y-1 cursor-pointer hover:opacity-75 transition-opacity"
                                                             wire:click="openManualPaymentModal({{ $student->id }}, '{{ $period['label'] }}', '{{ $period['period_start']->format('Y-m-d') }}', '{{ $period['period_end']->format('Y-m-d') }}', {{ $payment['unpaid_amount'] ?? 0 }}, {{ $payment['paid_amount'] ?? 0 }}, {{ $payment['expected_amount'] ?? 0 }})">
                                                            <div class="inline-flex items-center justify-center w-6 h-6 bg-yellow-100 dark:bg-yellow-900/40 text-yellow-600 dark:text-yellow-400 rounded-full mb-1">
                                                                <flux:icon.minus class="w-4 h-4" />
                                                            </div>
                                                            <div class="text-xs font-medium text-yellow-600 dark:text-yellow-400">
                                                                RM {{ number_format($payment['paid_amount'] ?? 0, 2) }}
                                                            </div>
                                                            <div class="text-xs text-red-500 dark:text-red-400">
                                                                RM {{ number_format($payment['unpaid_amount'] ?? 0, 2) }} due
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Click to pay</div>
                                                        </div>
                                                        @break

                                                    @case('pending_payment')
                                                        <div class="space-y-1 cursor-pointer hover:opacity-75 transition-opacity"
                                                             wire:click="openManualPaymentModal({{ $student->id }}, '{{ $period['label'] }}', '{{ $period['period_start']->format('Y-m-d') }}', '{{ $period['period_end']->format('Y-m-d') }}', {{ $payment['expected_amount'] ?? 0 }}, {{ $payment['paid_amount'] ?? 0 }}, {{ $payment['expected_amount'] ?? 0 }})">
                                                            <div class="inline-flex items-center justify-center w-6 h-6 bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 rounded-full mb-1">
                                                                <flux:icon.clock class="w-4 h-4" />
                                                            </div>
                                                            <div class="text-xs font-medium text-purple-600 dark:text-purple-400">
                                                                Pending
                                                            </div>
                                                            @if($payment['expected_amount'] > 0)
                                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                    RM {{ number_format($payment['expected_amount'], 2) }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                        @break

                                                    @case('not_started')
                                                        <div class="space-y-1 cursor-pointer hover:opacity-75 transition-opacity"
                                                             wire:click="openManualPaymentModal({{ $student->id }}, '{{ $period['label'] }}', '{{ $period['period_start']->format('Y-m-d') }}', '{{ $period['period_end']->format('Y-m-d') }}', {{ $payment['expected_amount'] ?? 0 }}, {{ $payment['paid_amount'] ?? 0 }}, {{ $payment['expected_amount'] ?? 0 }})">
                                                            <div class="inline-flex items-center justify-center w-6 h-6 bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 rounded-full mb-1">
                                                                <flux:icon.clock class="w-4 h-4" />
                                                            </div>
                                                            <div class="text-xs text-blue-600 dark:text-blue-400">
                                                                Not started
                                                            </div>
                                                            @if($payment['expected_amount'] > 0)
                                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                    RM {{ number_format($payment['expected_amount'], 2) }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                        @break

                                                    @case('cancelled_this_period')
                                                        <div class="space-y-1">
                                                            <div class="inline-flex items-center justify-center w-6 h-6 bg-orange-100 dark:bg-orange-900/40 text-orange-600 dark:text-orange-400 rounded-full mb-1">
                                                                <flux:icon.x-circle class="w-4 h-4" />
                                                            </div>
                                                            <div class="text-xs text-orange-600 dark:text-orange-400">
                                                                Canceled
                                                            </div>
                                                        </div>
                                                        @break

                                                    @case('cancelled_before')
                                                        <div class="space-y-1">
                                                            <div class="inline-flex items-center justify-center w-6 h-6 bg-gray-100 dark:bg-zinc-700 text-gray-500 dark:text-gray-400 rounded-full mb-1">
                                                                <flux:icon.x-circle class="w-4 h-4" />
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                Canceled
                                                            </div>
                                                        </div>
                                                        @break

                                                    @case('withdrawn')
                                                        <div class="space-y-1">
                                                            <div class="inline-flex items-center justify-center w-6 h-6 bg-red-100 dark:bg-red-900/40 text-red-500 dark:text-red-400 rounded-full mb-1">
                                                                <flux:icon.user-minus class="w-4 h-4" />
                                                            </div>
                                                            <div class="text-xs text-red-500 dark:text-red-400">
                                                                Withdrawn
                                                            </div>
                                                        </div>
                                                        @break

                                                    @case('suspended')
                                                        <div class="space-y-1">
                                                            <div class="inline-flex items-center justify-center w-6 h-6 bg-yellow-100 dark:bg-yellow-900/40 text-yellow-500 dark:text-yellow-400 rounded-full mb-1">
                                                                <flux:icon.pause class="w-4 h-4" />
                                                            </div>
                                                            <div class="text-xs text-yellow-500 dark:text-yellow-400">
                                                                Suspended
                                                            </div>
                                                        </div>
                                                        @break

                                                    @default
                                                        <div class="inline-flex items-center justify-center w-6 h-6 bg-gray-100 dark:bg-zinc-700 text-gray-400 rounded-full">
                                                            <flux:icon.x-mark class="w-4 h-4" />
                                                        </div>
                                                @endswitch

                                                <!-- Document Shipment Tracking -->
                                                @if(isset($payment['shipment_item']))
                                                    <div class="mt-2 pt-2 border-t border-gray-200 dark:border-zinc-700">
                                                        @if($payment['shipment_item']->tracking_number)
                                                            <div class="flex items-center justify-center gap-1 text-xs">
                                                                <flux:icon name="truck" class="w-3 h-3 text-blue-600 dark:text-blue-400" />
                                                                <span class="text-gray-600 dark:text-gray-400 font-medium">Tracking:</span>
                                                            </div>
                                                            <div class="mt-1 px-2 py-1 bg-blue-50 dark:bg-blue-900/30 rounded text-xs font-mono text-blue-700 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/50 cursor-pointer transition-colors"
                                                                 title="Click to copy tracking number"
                                                                 onclick="navigator.clipboard.writeText('{{ $payment['shipment_item']->tracking_number }}'); alert('Tracking number copied!');">
                                                                {{ $payment['shipment_item']->tracking_number }}
                                                            </div>
                                                            <div class="mt-1 text-xs flex items-center justify-center gap-1.5 flex-wrap">
                                                                @if($payment['shipment_item']->status === 'delivered')
                                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400 rounded">
                                                                        <flux:icon name="check-circle" class="w-3 h-3" />
                                                                        Delivered
                                                                    </span>
                                                                @elseif($payment['shipment_item']->status === 'shipped')
                                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-400 rounded">
                                                                        <flux:icon name="truck" class="w-3 h-3" />
                                                                        Shipped
                                                                    </span>
                                                                @elseif($payment['shipment_item']->status === 'pending')
                                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-400 rounded">
                                                                        <flux:icon name="clock" class="w-3 h-3" />
                                                                        Pending
                                                                    </span>
                                                                @elseif($payment['shipment_item']->status === 'failed')
                                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400 rounded">
                                                                        <flux:icon name="exclamation-triangle" class="w-3 h-3" />
                                                                        Failed
                                                                    </span>
                                                                @endif
                                                            </div>
                                                            <!-- Action Buttons -->
                                                            <div class="mt-2 flex items-center justify-center gap-1">
                                                                <button wire:click="editShipmentItem({{ $payment['shipment_item']->id }})"
                                                                        title="Edit Tracking & Status"
                                                                        class="inline-flex items-center justify-center w-6 h-6 bg-gray-100 dark:bg-zinc-700 hover:bg-gray-200 dark:hover:bg-zinc-600 text-gray-600 dark:text-gray-300 rounded transition-colors">
                                                                    <flux:icon name="pencil" class="w-3 h-3" />
                                                                </button>
                                                                @if($payment['shipment_item']->product_order_id)
                                                                    <a href="{{ route('admin.orders.show', $payment['shipment_item']->product_order_id) }}"
                                                                       wire:navigate
                                                                       title="View Product Order"
                                                                       class="inline-flex items-center justify-center w-6 h-6 bg-blue-100 dark:bg-blue-900/40 hover:bg-blue-200 dark:hover:bg-blue-900/60 text-blue-600 dark:text-blue-400 rounded transition-colors">
                                                                        <flux:icon name="eye" class="w-3 h-3" />
                                                                    </a>
                                                                @endif
                                                            </div>
                                                        @else
                                                            <div class="flex items-center justify-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                                                <flux:icon name="truck" class="w-3 h-3" />
                                                                <span class="text-xs">{{ ucfirst($payment['shipment_item']->status) }}</span>
                                                            </div>
                                                            <!-- Action Buttons for items without tracking -->
                                                            <div class="mt-2 flex items-center justify-center gap-1">
                                                                <button wire:click="editShipmentItem({{ $payment['shipment_item']->id }})"
                                                                        title="Add Tracking & Update Status"
                                                                        class="inline-flex items-center justify-center gap-1 px-2 py-1 text-xs bg-blue-100 dark:bg-blue-900/40 hover:bg-blue-200 dark:hover:bg-blue-900/60 text-blue-700 dark:text-blue-400 rounded transition-colors">
                                                                    <flux:icon name="pencil" class="w-3 h-3" />
                                                                    <span>Edit</span>
                                                                </button>
                                                                @if($payment['shipment_item']->product_order_id)
                                                                    <a href="{{ route('admin.orders.show', $payment['shipment_item']->product_order_id) }}"
                                                                       wire:navigate
                                                                       title="View Product Order"
                                                                       class="inline-flex items-center justify-center w-6 h-6 bg-blue-100 dark:bg-blue-900/40 hover:bg-blue-200 dark:hover:bg-blue-900/60 text-blue-600 dark:text-blue-400 rounded transition-colors">
                                                                        <flux:icon name="eye" class="w-3 h-3" />
                                                                    </a>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                @elseif(isset($payment['shipment']))
                                                    <div class="mt-2 pt-2 border-t border-gray-200 dark:border-zinc-700">
                                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                                            <flux:icon name="information-circle" class="w-3 h-3 inline" />
                                                            Not in shipment
                                                        </div>
                                                    </div>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="py-3 px-4 text-center font-medium border-l-2 border-gray-300 dark:border-zinc-600 bg-white dark:bg-zinc-800">
                                            <div class="space-y-1">
                                                <div class="text-emerald-600 dark:text-emerald-400 font-medium">
                                                    RM {{ number_format($totalPaid, 2) }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    Expected: RM {{ number_format($totalExpected, 2) }}
                                                </div>
                                                @if($totalUnpaid > 0)
                                                    <div class="text-xs text-red-500 dark:text-red-400 font-medium">
                                                        Unpaid: RM {{ number_format($totalUnpaid, 2) }}
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8 px-4">
                        <flux:icon.users class="w-8 h-8 text-zinc-300 dark:text-zinc-600 mx-auto mb-3" />
                        <h4 class="text-sm font-medium text-zinc-500 dark:text-zinc-400 mb-1">No students enrolled</h4>
                        <p class="text-xs text-zinc-400 dark:text-zinc-500">
                            This class doesn't have any enrolled students yet.
                        </p>
                    </div>
                @endif
            </div>

            <!-- Pagination -->
            @if($this->class_students_for_payment_report->hasPages())
                <div class="mt-4">
                    {{ $this->class_students_for_payment_report->links() }}
                </div>
            @endif

            <!-- Legend -->
            <div class="mt-6 rounded-lg border border-zinc-200 dark:border-zinc-700">
                <div class="border-b border-zinc-200 dark:border-zinc-700 px-5 py-3">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Payment Status Legend</h3>
                </div>
                <div class="px-5 py-4 space-y-4">
                    <!-- Consecutive Unpaid Warning -->
                    <div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                        <div class="flex items-start gap-2.5">
                            <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400 rounded-md text-[11px] font-medium shrink-0">
                                <flux:icon.exclamation-triangle class="w-3 h-3" />
                                <span>2+ Months Unpaid</span>
                            </div>
                            <div class="flex-1">
                                <p class="text-xs font-medium text-red-900 dark:text-red-300">Critical Payment Alert</p>
                                <p class="text-[11px] text-red-700 dark:text-red-400 mt-0.5 leading-relaxed">
                                    Students with this indicator have 2 or more consecutive months of unpaid or partial payments. The entire row is highlighted in light red for easy identification. Consider immediate follow-up action.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2">
                                <div class="inline-flex items-center justify-center w-5 h-5 bg-emerald-100 text-emerald-600 rounded-full">
                                    <flux:icon.check class="w-3 h-3" />
                                </div>
                                <span class="text-xs text-zinc-600 dark:text-zinc-400">Payment Received</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="inline-flex items-center justify-center w-5 h-5 bg-yellow-100 text-yellow-600 rounded-full">
                                    <flux:icon.minus class="w-3 h-3" />
                                </div>
                                <span class="text-xs text-zinc-600 dark:text-zinc-400">Partial Payment</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="inline-flex items-center justify-center w-5 h-5 bg-red-100 text-red-600 rounded-full">
                                    <flux:icon.exclamation-triangle class="w-3 h-3" />
                                </div>
                                <span class="text-xs text-zinc-600 dark:text-zinc-400">Unpaid</span>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2">
                                <div class="inline-flex items-center justify-center w-5 h-5 bg-purple-100 text-purple-600 rounded-full">
                                    <flux:icon.clock class="w-3 h-3" />
                                </div>
                                <span class="text-xs text-zinc-600 dark:text-zinc-400">Pending Payment</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="inline-flex items-center justify-center w-5 h-5 bg-blue-100 text-blue-600 rounded-full">
                                    <flux:icon.clock class="w-3 h-3" />
                                </div>
                                <span class="text-xs text-zinc-600 dark:text-zinc-400">Not Started</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="inline-flex items-center justify-center w-5 h-5 bg-orange-100 text-orange-600 rounded-full">
                                    <flux:icon.x-circle class="w-3 h-3" />
                                </div>
                                <span class="text-xs text-zinc-600 dark:text-zinc-400">Cancelled</span>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2">
                                <div class="inline-flex items-center justify-center w-5 h-5 bg-red-100 text-red-500 rounded-full">
                                    <flux:icon.user-minus class="w-3 h-3" />
                                </div>
                                <span class="text-xs text-zinc-600 dark:text-zinc-400">Withdrawn</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="inline-flex items-center justify-center w-5 h-5 bg-yellow-100 text-yellow-500 rounded-full">
                                    <flux:icon.pause class="w-3 h-3" />
                                </div>
                                <span class="text-xs text-zinc-600 dark:text-zinc-400">Suspended</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manual Payment Modal -->
            <flux:modal name="manual-payment" :show="$showManualPaymentModal" wire:model="showManualPaymentModal">
                <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
                    <flux:heading size="lg">Record Manual Payment</flux:heading>
                    <flux:text class="mt-2">Upload payment receipt and record payment for this period</flux:text>
                </div>

                @if($selectedStudent && $selectedPeriod)
                    <div class="space-y-4">
                        <!-- Student Info -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <flux:text class="text-sm font-medium text-gray-600">Student</flux:text>
                                    <flux:text class="text-sm text-gray-900 mt-1">
                                        {{ \App\Models\Student::find($selectedStudent)?->user->name ?? 'N/A' }}
                                    </flux:text>
                                </div>
                                <div>
                                    <flux:text class="text-sm font-medium text-gray-600">Period</flux:text>
                                    <flux:text class="text-sm text-gray-900 mt-1">
                                        {{ $selectedPeriodLabel }} ({{ \Carbon\Carbon::parse($selectedPeriod['start'])->format('M j') }} - {{ \Carbon\Carbon::parse($selectedPeriod['end'])->format('M j, Y') }})
                                    </flux:text>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Summary -->
                        @if($expectedAmount > 0 || $paidAmountForPeriod > 0)
                            <div class="bg-blue-50 rounded-lg p-4">
                                <flux:text class="text-sm font-medium text-blue-800 mb-2">Payment Summary</flux:text>
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <flux:text class="text-xs text-blue-600">Expected</flux:text>
                                        <flux:text class="text-sm font-semibold text-blue-900">
                                            RM {{ number_format($expectedAmount, 2) }}
                                        </flux:text>
                                    </div>
                                    <div>
                                        <flux:text class="text-xs text-green-600">Paid</flux:text>
                                        <flux:text class="text-sm font-semibold text-green-700">
                                            RM {{ number_format($paidAmountForPeriod, 2) }}
                                        </flux:text>
                                    </div>
                                    <div>
                                        <flux:text class="text-xs text-red-600">Remaining</flux:text>
                                        <flux:text class="text-sm font-semibold text-red-700">
                                            RM {{ number_format(max(0, $expectedAmount - $paidAmountForPeriod), 2) }}
                                        </flux:text>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Error Messages -->
                        @if($errors->has('general'))
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <flux:text class="text-sm text-red-800">{{ $errors->first('general') }}</flux:text>
                            </div>
                        @endif

                        <!-- Payment Amount -->
                        <flux:field>
                            <flux:label>Payment Amount (RM)</flux:label>
                            <flux:input
                                wire:model="paymentAmount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                placeholder="0.00"
                            />
                            <flux:error name="paymentAmount" />
                        </flux:field>

                        <!-- Receipt Upload -->
                        <flux:field>
                            <flux:label>Payment Receipt (Optional)</flux:label>
                            <flux:input
                                wire:model="receiptFile"
                                type="file"
                                accept="image/*,.pdf,application/pdf"
                            />
                            <flux:description>Upload an image or PDF of the payment receipt (Max: 10MB)</flux:description>
                            <flux:error name="receiptFile" />

                            @if($receiptFile)
                                <div class="mt-2 p-2 bg-green-50 border border-green-200 rounded">
                                    <flux:text class="text-sm text-green-800">Receipt uploaded: {{ $receiptFile->getClientOriginalName() }}</flux:text>
                                </div>
                            @endif
                        </flux:field>

                        <!-- Payment Notes -->
                        <flux:field>
                            <flux:label>Notes (Optional)</flux:label>
                            <flux:textarea
                                wire:model="paymentNotes"
                                rows="3"
                                placeholder="Add any notes about this payment..."
                            />
                            <flux:error name="paymentNotes" />
                        </flux:field>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-4">
                            <flux:button variant="outline" wire:click="closeManualPaymentModal">
                                Cancel
                            </flux:button>
                            <flux:button variant="primary" wire:click="createManualPayment">
                                <div class="flex items-center justify-center">
                                    <flux:icon name="check" class="w-4 h-4 mr-1" />
                                    Record Payment
                                </div>
                            </flux:button>
                        </div>
                    </div>
                @endif
            </flux:modal>

            <!-- Edit Enrollment Modal -->
            <flux:modal name="edit-enrollment" :show="$showEditEnrollmentModal" wire:model="showEditEnrollmentModal">
                <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
                    <flux:heading size="lg">Edit Enrollment</flux:heading>
                    <flux:text class="mt-2">Update enrollment date, amount, and subscription type</flux:text>
                </div>

                @if($editingEnrollment)
                    <div class="space-y-4">
                        <!-- Student Info -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <flux:text class="text-sm font-medium text-gray-600">Student</flux:text>
                            <flux:text class="text-sm text-gray-900 mt-1">
                                {{ $editingEnrollment->student->user?->name ?? 'N/A' }}
                            </flux:text>
                        </div>

                        <!-- Stripe Billing Info - Only for automatic subscriptions -->
                        @if($editStripeBillingInfo && $editingEnrollment->payment_method_type === 'automatic')
                            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-3">
                                    <svg class="w-5 h-5 text-indigo-600" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/>
                                    </svg>
                                    <flux:text class="text-sm font-semibold text-indigo-800">Stripe Billing Information</flux:text>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <flux:text class="text-xs font-medium text-indigo-600">Status</flux:text>
                                        <div class="mt-1">
                                            @if($editStripeBillingInfo['status'] === 'active')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Active</span>
                                            @elseif($editStripeBillingInfo['status'] === 'trialing')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Trial</span>
                                            @elseif($editStripeBillingInfo['status'] === 'past_due')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Past Due</span>
                                            @elseif($editStripeBillingInfo['status'] === 'canceled')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">Canceled</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">{{ ucfirst($editStripeBillingInfo['status']) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <flux:text class="text-xs font-medium text-indigo-600">Next Billing Date</flux:text>
                                        <flux:text class="text-sm text-indigo-900 mt-1 font-semibold">
                                            @if($editStripeBillingInfo['trial_end'])
                                                {{ $editStripeBillingInfo['trial_end']->format('d M Y') }}
                                                <span class="text-xs font-normal text-indigo-600">(Trial ends)</span>
                                            @elseif($editStripeBillingInfo['current_period_end'])
                                                {{ $editStripeBillingInfo['current_period_end']->format('d M Y') }}
                                            @else
                                                <span class="text-gray-500">Not available</span>
                                            @endif
                                        </flux:text>
                                    </div>
                                    @if($editStripeBillingInfo['current_period_start'] && $editStripeBillingInfo['current_period_end'])
                                        <div>
                                            <flux:text class="text-xs font-medium text-indigo-600">Current Period</flux:text>
                                            <flux:text class="text-sm text-indigo-900 mt-1">
                                                {{ $editStripeBillingInfo['current_period_start']->format('d M') }} - {{ $editStripeBillingInfo['current_period_end']->format('d M Y') }}
                                            </flux:text>
                                        </div>
                                    @endif
                                    @if($editStripeBillingInfo['cancel_at'])
                                        <div>
                                            <flux:text class="text-xs font-medium text-red-600">Cancels On</flux:text>
                                            <flux:text class="text-sm text-red-700 mt-1">
                                                {{ $editStripeBillingInfo['cancel_at']->format('d M Y') }}
                                            </flux:text>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Editable Next Billing Date -->
                            @if(!$editStripeBillingInfo['cancel_at'] && in_array($editStripeBillingInfo['status'], ['active', 'trialing']))
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <div class="flex items-start gap-3">
                                        <flux:icon.calendar class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
                                        <div class="flex-1">
                                            <flux:text class="text-sm font-medium text-blue-800">Change Next Billing Date</flux:text>
                                            <flux:text class="text-xs text-blue-700 mt-1">
                                                Update when the next automatic charge will occur. This will adjust the billing cycle in Stripe.
                                            </flux:text>
                                        </div>
                                    </div>
                                </div>

                                <flux:field>
                                    <flux:label>Next Billing Date</flux:label>
                                    <flux:input
                                        wire:model="editNextBillingDate"
                                        type="date"
                                        min="{{ now()->addDay()->format('Y-m-d') }}"
                                    />
                                    <flux:description>
                                        The next automatic charge will happen on this date.
                                        @if($editStripeBillingInfo['trial_end'])
                                            Currently in trial until {{ $editStripeBillingInfo['trial_end']->format('d M Y') }}.
                                        @endif
                                    </flux:description>
                                    <flux:error name="editNextBillingDate" />
                                </flux:field>
                            @endif
                        @endif

                        <!-- Error Messages -->
                        @if($errors->has('general'))
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <flux:text class="text-sm text-red-800">{{ $errors->first('general') }}</flux:text>
                            </div>
                        @endif

                        <!-- Enrollment Date -->
                        <flux:field>
                            <flux:label>Enrollment Date</flux:label>
                            <flux:input
                                wire:model="editEnrollmentDate"
                                type="date"
                            />
                            <flux:error name="editEnrollmentDate" />
                        </flux:field>

                        <!-- Enrollment Amount -->
                        <flux:field>
                            <flux:label>Enrollment Amount (RM)</flux:label>
                            <flux:input
                                wire:model="editEnrollmentFee"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                            />
                            <flux:description>Monthly fee for this enrollment</flux:description>
                            <flux:error name="editEnrollmentFee" />
                        </flux:field>

                        <!-- Payment Method Type -->
                        <flux:field>
                            <flux:label>Subscription Type</flux:label>
                            <flux:select wire:model.live="editPaymentMethodType" class="w-full">
                                <flux:select.option value="automatic">Automatic (Card Payment)</flux:select.option>
                                <flux:select.option value="manual">Manual Payment</flux:select.option>
                            </flux:select>
                            <flux:description>Choose how the student will make payments</flux:description>
                            <flux:error name="editPaymentMethodType" />
                        </flux:field>

                        <!-- Next Payment Date - Only show when switching from Manual to Automatic -->
                        @if($editOriginalPaymentMethodType === 'manual' && $editPaymentMethodType === 'automatic')
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-start gap-3">
                                    <flux:icon.information-circle class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
                                    <div class="flex-1">
                                        <flux:text class="text-sm font-medium text-blue-800">Schedule First Automatic Charge</flux:text>
                                        <flux:text class="text-xs text-blue-700 mt-1">
                                            Since you're switching to automatic payments, choose when the first charge should happen.
                                            This is useful if the student has already paid manually for this month.
                                        </flux:text>
                                    </div>
                                </div>
                            </div>

                            <flux:field>
                                <flux:label>First Payment Date</flux:label>
                                <flux:input
                                    wire:model="editNextPaymentDate"
                                    type="date"
                                    min="{{ now()->addDay()->format('Y-m-d') }}"
                                />
                                <flux:description>
                                    The first automatic charge will happen on this date. Must be a future date.
                                </flux:description>
                                <flux:error name="editNextPaymentDate" />
                            </flux:field>

                            @php
                                $hasPaymentMethod = $editingEnrollment->student->user?->paymentMethods()
                                    ->where('is_active', true)
                                    ->exists();
                            @endphp

                            @if(!$hasPaymentMethod)
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <div class="flex items-start gap-3">
                                        <flux:icon.exclamation-triangle class="w-5 h-5 text-yellow-600 mt-0.5 flex-shrink-0" />
                                        <div class="flex-1">
                                            <flux:text class="text-sm font-medium text-yellow-800">No Payment Method</flux:text>
                                            <flux:text class="text-xs text-yellow-700 mt-1">
                                                This student doesn't have a saved payment method yet. They need to add a card before you can switch to automatic payments.
                                                Generate a magic link to allow them to add one.
                                            </flux:text>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-4">
                            <flux:button variant="outline" wire:click="closeEditEnrollmentModal">
                                Cancel
                            </flux:button>
                            <flux:button variant="primary" wire:click="updateEnrollment">
                                <div class="flex items-center justify-center">
                                    <flux:icon name="check" class="w-4 h-4 mr-1" />
                                    Update Enrollment
                                </div>
                            </flux:button>
                        </div>
                    </div>
                @endif
            </flux:modal>

            <!-- Create First Enrollment Modal -->
            <flux:modal name="create-first-enrollment" :show="$showCreateFirstEnrollmentModal" wire:model="showCreateFirstEnrollmentModal">
                <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
                    <flux:heading size="lg">Create First Enrollment</flux:heading>
                    <flux:text class="mt-2">Set up the initial enrollment for this student</flux:text>
                </div>

                @if($creatingEnrollmentForStudentId)
                    @php
                        $student = \App\Models\Student::find($creatingEnrollmentForStudentId);
                    @endphp

                    @if($student)
                        <div class="space-y-4">
                            <!-- Student Info -->
                            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                                <div class="flex items-start gap-3">
                                    <flux:icon.information-circle class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
                                    <div>
                                        <flux:text class="text-sm font-medium text-blue-800">Creating enrollment for:</flux:text>
                                        <flux:text class="text-sm text-blue-900 mt-1 font-semibold">
                                            {{ $student->user?->name ?? 'N/A' }}
                                        </flux:text>
                                        <flux:text class="text-xs text-blue-700 mt-1">
                                            Course: {{ $class->course->title }}
                                        </flux:text>
                                    </div>
                                </div>
                            </div>

                            <!-- Error Messages -->
                            @if($errors->has('general'))
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <flux:text class="text-sm text-red-800">{{ $errors->first('general') }}</flux:text>
                                </div>
                            @endif

                            <!-- Enrollment Date -->
                            <flux:field>
                                <flux:label>Enrollment Date</flux:label>
                                <flux:input
                                    wire:model="newEnrollmentDate"
                                    type="date"
                                />
                                <flux:description>The date when the student enrolled in this course</flux:description>
                                <flux:error name="newEnrollmentDate" />
                            </flux:field>

                            <!-- Enrollment Fee -->
                            <flux:field>
                                <flux:label>Enrollment Fee (RM)</flux:label>
                                <flux:input
                                    wire:model="newEnrollmentFee"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                />
                                <flux:description>Monthly fee amount for this enrollment</flux:description>
                                <flux:error name="newEnrollmentFee" />
                            </flux:field>

                            <!-- Payment Method Type -->
                            <flux:field>
                                <flux:label>Payment Method</flux:label>
                                <flux:select wire:model="newPaymentMethodType" class="w-full">
                                    <flux:select.option value="automatic">Automatic (Card Payment)</flux:select.option>
                                    <flux:select.option value="manual">Manual Payment</flux:select.option>
                                </flux:select>
                                <flux:description>
                                    <span class="font-medium">Automatic:</span> Student will be charged automatically via Stripe.<br>
                                    <span class="font-medium">Manual:</span> Student will make manual payments.
                                </flux:description>
                                <flux:error name="newPaymentMethodType" />
                            </flux:field>

                            <!-- Actions -->
                            <div class="flex justify-end gap-3 pt-4">
                                <flux:button variant="outline" wire:click="closeCreateFirstEnrollmentModal">
                                    Cancel
                                </flux:button>
                                <flux:button variant="primary" wire:click="createFirstEnrollment">
                                    <div class="flex items-center justify-center">
                                        <flux:icon name="plus-circle" class="w-4 h-4 mr-1" />
                                        Create Enrollment
                                    </div>
                                </flux:button>
                            </div>
                        </div>
                    @endif
                @endif
            </flux:modal>

            <!-- Cancel Subscription Modal -->
            <flux:modal name="cancel-subscription" :show="$showCancelSubscriptionModal" wire:model="showCancelSubscriptionModal">
                <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
                    <flux:heading size="lg">Cancel Subscription</flux:heading>
                    <flux:text class="mt-2">Set the cancellation date for this subscription</flux:text>
                </div>

                @if($cancelingEnrollment)
                    <div class="space-y-4">
                        <!-- Student Info -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <flux:text class="text-sm font-medium text-gray-600">Student</flux:text>
                                    <flux:text class="text-sm text-gray-900 mt-1">
                                        {{ $cancelingEnrollment->student->user?->name ?? 'N/A' }}
                                    </flux:text>
                                </div>
                                <div>
                                    <flux:text class="text-sm font-medium text-gray-600">Subscription Type</flux:text>
                                    <div class="mt-1">
                                        @if(empty($cancelingEnrollment->stripe_subscription_id))
                                            <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 text-gray-700 rounded-md text-xs font-medium">
                                                <flux:icon name="document-text" class="w-3 h-3" />
                                                <span>Active Enrollment (No Subscription)</span>
                                            </div>
                                        @elseif($cancelingEnrollment->isStripeSubscription())
                                            <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-100 text-blue-700 rounded-md text-xs font-medium">
                                                <flux:icon name="credit-card" class="w-3 h-3" />
                                                <span>Stripe Subscription</span>
                                            </div>
                                        @else
                                            <div class="inline-flex items-center gap-1 px-2 py-0.5 bg-orange-100 text-orange-700 rounded-md text-xs font-medium">
                                                <flux:icon name="banknotes" class="w-3 h-3" />
                                                <span>Manual Subscription</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Warning Message -->
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex items-start gap-2">
                                <flux:icon.exclamation-triangle class="w-5 h-5 text-yellow-600 mt-0.5 flex-shrink-0" />
                                <div>
                                    <flux:text class="text-sm text-yellow-800 font-medium">Warning</flux:text>
                                    <flux:text class="text-xs text-yellow-700 mt-1">
                                        @if(empty($cancelingEnrollment->stripe_subscription_id))
                                            Canceling this enrollment will stop all future orders and mark the enrollment as canceled. The student will lose access to the course on the cancellation date.
                                        @elseif($cancelingEnrollment->isStripeSubscription())
                                            Canceling the Stripe subscription will stop future automatic payments and notify Stripe to cancel the subscription. The student will lose access to the course on the cancellation date.
                                        @else
                                            Canceling the manual subscription will mark it as canceled in the system. No payments are automatically processed for manual subscriptions.
                                        @endif
                                    </flux:text>
                                </div>
                            </div>
                        </div>

                        <!-- Error Messages -->
                        @if($errors->has('general'))
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <flux:text class="text-sm text-red-800">{{ $errors->first('general') }}</flux:text>
                            </div>
                        @endif

                        <!-- Cancellation Date -->
                        <flux:field>
                            <flux:label>Cancellation Date</flux:label>
                            <flux:input
                                wire:model="cancellationDate"
                                type="date"
                            />
                            <flux:description>Choose when the subscription should be cancelled</flux:description>
                            <flux:error name="cancellationDate" />
                        </flux:field>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-4">
                            <flux:button variant="outline" wire:click="closeCancelSubscriptionModal">
                                Cancel
                            </flux:button>
                            <flux:button variant="danger" wire:click="cancelSubscription">
                                <div class="flex items-center justify-center">
                                    <flux:icon name="x-circle" class="w-4 h-4 mr-1" />
                                    Cancel Subscription
                                </div>
                            </flux:button>
                        </div>
                    </div>
                @endif
            </flux:modal>
            @endif
        </div>
        <!-- End Payment Reports Tab -->

        <!-- PIC Performance Tab -->
        <div class="{{ $activeTab === 'pic-performance' ? 'block' : 'hidden' }}">
            @if($activeTab === 'pic-performance')
                <!-- Summary Statistics -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-3">
                        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Total PICs</span>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $this->pic_performance_summary['total_pics'] }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-3">
                        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Total Students</span>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $this->pic_performance_summary['total_students'] }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-3">
                        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Collection Rate</span>
                        <p class="mt-1 text-xl font-semibold tabular-nums {{ $this->pic_performance_summary['overall_collection_rate'] >= 80 ? 'text-green-600' : ($this->pic_performance_summary['overall_collection_rate'] >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                            {{ $this->pic_performance_summary['overall_collection_rate'] }}%
                        </p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-3">
                        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Total Collected</span>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">RM {{ number_format($this->pic_performance_summary['total_collected'], 2) }}</p>
                        <span class="text-xs text-zinc-400 dark:text-zinc-500">of RM {{ number_format($this->pic_performance_summary['total_expected'], 2) }}</span>
                    </div>
                </div>

                <!-- PIC Performance Table -->
                @if($this->pic_performance_data->count() > 0)
                    <div class="space-y-4">
                        @foreach($this->pic_performance_data as $index => $picData)
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                                <!-- PIC Header -->
                                <div class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 px-5 py-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <flux:avatar size="xs">
                                                {{ $picData['pic']?->initials() ?? 'N/A' }}
                                            </flux:avatar>
                                            <div>
                                                <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $picData['pic']?->name ?? 'N/A' }}</h4>
                                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $picData['pic']?->email ?? 'N/A' }}</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-5">
                                            <div class="text-right">
                                                <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Students</span>
                                                <p class="text-sm font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $picData['student_count'] }}</p>
                                            </div>
                                            <div class="text-right">
                                                <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Collection</span>
                                                <p class="text-sm font-semibold tabular-nums {{ $picData['payment_stats']['collection_rate'] >= 80 ? 'text-green-600' : ($picData['payment_stats']['collection_rate'] >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                                                    {{ $picData['payment_stats']['collection_rate'] }}%
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- PIC Statistics -->
                                <div class="px-5 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                        <div>
                                            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Expected</span>
                                            <p class="text-sm font-medium tabular-nums text-zinc-900 dark:text-zinc-100">RM {{ number_format($picData['payment_stats']['total_expected'], 2) }}</p>
                                        </div>
                                        <div>
                                            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Collected</span>
                                            <p class="text-sm font-medium tabular-nums text-green-600">RM {{ number_format($picData['payment_stats']['total_collected'], 2) }}</p>
                                        </div>
                                        <div>
                                            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Pending</span>
                                            <p class="text-sm font-medium tabular-nums text-yellow-600">RM {{ number_format($picData['payment_stats']['total_pending'], 2) }}</p>
                                        </div>
                                        <div>
                                            <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Overdue</span>
                                            <p class="text-sm font-medium tabular-nums text-red-600">RM {{ number_format($picData['payment_stats']['total_overdue'], 2) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Student Status Distribution -->
                                <div class="px-5 py-3 bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
                                    <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Status Distribution</span>
                                    <div class="flex flex-wrap gap-1.5 mt-2">
                                        @if($picData['status_distribution']['enrolled'] > 0)
                                            <flux:badge variant="primary" size="sm">Enrolled: {{ $picData['status_distribution']['enrolled'] }}</flux:badge>
                                        @endif
                                        @if($picData['status_distribution']['active'] > 0)
                                            <flux:badge variant="success" size="sm">Active: {{ $picData['status_distribution']['active'] }}</flux:badge>
                                        @endif
                                        @if($picData['status_distribution']['completed'] > 0)
                                            <flux:badge variant="outline" size="sm">Completed: {{ $picData['status_distribution']['completed'] }}</flux:badge>
                                        @endif
                                        @if($picData['status_distribution']['pending'] > 0)
                                            <flux:badge variant="warning" size="sm">Pending: {{ $picData['status_distribution']['pending'] }}</flux:badge>
                                        @endif
                                        @if($picData['status_distribution']['suspended'] > 0)
                                            <flux:badge variant="danger" size="sm">Suspended: {{ $picData['status_distribution']['suspended'] }}</flux:badge>
                                        @endif
                                        @if($picData['status_distribution']['dropped'] > 0)
                                            <flux:badge size="sm">Dropped: {{ $picData['status_distribution']['dropped'] }}</flux:badge>
                                        @endif
                                    </div>
                                </div>

                                <!-- Student Pivot Table -->
                                <div class="px-5 py-4 bg-white dark:bg-zinc-900">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Student Payment Details</h4>
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $picData['student_count'] }} student{{ $picData['student_count'] !== 1 ? 's' : '' }} total</span>
                                    </div>

                                    <!-- Search and Filters -->
                                    <div class="mb-3 flex flex-col md:flex-row gap-3">
                                        <div class="flex-1">
                                            <flux:input
                                                wire:model.live.debounce.300ms="picSearchQueries.{{ $picData['pic']->id }}"
                                                placeholder="Search by name or email..."
                                                class="w-full"
                                            >
                                                <x-slot name="iconTrailing">
                                                    <flux:icon.magnifying-glass />
                                                </x-slot>
                                            </flux:input>
                                        </div>
                                        <div class="w-44 shrink-0">
                                            <flux:select wire:model.live="picStatusFilters.{{ $picData['pic']->id }}">
                                                <flux:select.option value="all">All Status</flux:select.option>
                                                <flux:select.option value="enrolled">Enrolled</flux:select.option>
                                                <flux:select.option value="active">Active</flux:select.option>
                                                <flux:select.option value="completed">Completed</flux:select.option>
                                                <flux:select.option value="pending">Pending</flux:select.option>
                                                <flux:select.option value="suspended">Suspended</flux:select.option>
                                                <flux:select.option value="dropped">Dropped</flux:select.option>
                                            </flux:select>
                                        </div>
                                        <div class="w-40 shrink-0">
                                            <flux:select wire:model.live="picPaymentFilters.{{ $picData['pic']->id }}">
                                                <flux:select.option value="all">All Payments</flux:select.option>
                                                <flux:select.option value="paid">Fully Paid</flux:select.option>
                                                <flux:select.option value="pending">Has Pending</flux:select.option>
                                                <flux:select.option value="overdue">Has Overdue</flux:select.option>
                                            </flux:select>
                                        </div>
                                    </div>

                                    @php
                                        $paginatedData = $this->getPicStudents($picData['pic']->id, $picData['students']);
                                        $students = $paginatedData['students'];
                                        $totalFiltered = $paginatedData['total'];
                                        $currentPage = $paginatedData['current_page'];
                                        $totalPages = $paginatedData['total_pages'];
                                    @endphp

                                    @if($students->count() > 0)
                                        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                                            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                                                <thead>
                                                    <tr class="bg-zinc-50 dark:bg-zinc-800">
                                                        <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400">Student</th>
                                                        <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400">Status</th>
                                                        <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400">Enrolled Date</th>
                                                        <th class="px-3 py-2 text-right text-[11px] font-medium uppercase tracking-wider text-zinc-400">Expected</th>
                                                        <th class="px-3 py-2 text-right text-[11px] font-medium uppercase tracking-wider text-zinc-400">Paid</th>
                                                        <th class="px-3 py-2 text-right text-[11px] font-medium uppercase tracking-wider text-zinc-400">Pending</th>
                                                        <th class="px-3 py-2 text-right text-[11px] font-medium uppercase tracking-wider text-zinc-400">Overdue</th>
                                                        <th class="px-3 py-2 text-right text-[11px] font-medium uppercase tracking-wider text-zinc-400">Collection %</th>
                                                        <th class="px-3 py-2 text-center text-[11px] font-medium uppercase tracking-wider text-zinc-400">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                                                    @foreach($students as $student)
                                                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                                                            <td class="px-3 py-2 whitespace-nowrap">
                                                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $student['name'] }}</div>
                                                                <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ $student['email'] }}</div>
                                                            </td>
                                                            <td class="px-3 py-2 whitespace-nowrap">
                                                                @php
                                                                    $statusColors = [
                                                                        'enrolled' => 'blue',
                                                                        'active' => 'green',
                                                                        'completed' => 'gray',
                                                                        'pending' => 'yellow',
                                                                        'suspended' => 'red',
                                                                        'dropped' => 'gray',
                                                                    ];
                                                                    $color = $statusColors[$student['enrollment_status']] ?? 'gray';
                                                                @endphp
                                                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-{{ $color }}-100 text-{{ $color }}-800 dark:bg-{{ $color }}-900/30 dark:text-{{ $color }}-400">
                                                                    {{ ucfirst($student['enrollment_status']) }}
                                                                </span>
                                                            </td>
                                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                                                {{ $student['enrollment_date'] ? \Carbon\Carbon::parse($student['enrollment_date'])->format('d M Y') : 'N/A' }}
                                                            </td>
                                                            <td class="px-3 py-2 whitespace-nowrap text-right text-sm tabular-nums text-zinc-900 dark:text-zinc-100">
                                                                RM {{ number_format($student['payment_summary']['expected'], 2) }}
                                                            </td>
                                                            <td class="px-3 py-2 whitespace-nowrap text-right text-sm tabular-nums font-medium text-green-600">
                                                                RM {{ number_format($student['payment_summary']['paid'], 2) }}
                                                            </td>
                                                            <td class="px-3 py-2 whitespace-nowrap text-right text-sm tabular-nums text-yellow-600">
                                                                RM {{ number_format($student['payment_summary']['pending'], 2) }}
                                                            </td>
                                                            <td class="px-3 py-2 whitespace-nowrap text-right text-sm tabular-nums font-medium text-red-600">
                                                                RM {{ number_format($student['payment_summary']['overdue'], 2) }}
                                                            </td>
                                                            <td class="px-3 py-2 whitespace-nowrap text-right text-sm tabular-nums font-semibold">
                                                                @php
                                                                    $collectionRate = $student['payment_summary']['expected'] > 0
                                                                        ? round(($student['payment_summary']['paid'] / $student['payment_summary']['expected']) * 100, 1)
                                                                        : 0;
                                                                @endphp
                                                                <span class="{{ $collectionRate >= 80 ? 'text-green-600' : ($collectionRate >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                                                                    {{ $collectionRate }}%
                                                                </span>
                                                            </td>
                                                            <td class="px-3 py-2 whitespace-nowrap text-center">
                                                                <a href="{{ route('enrollments.show', $student['enrollment_id']) }}" class="text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 inline-flex items-center gap-1 transition-colors">
                                                                    <flux:icon.eye class="w-4 h-4" />
                                                                    <span class="text-xs">View</span>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Pagination -->
                                        @if($totalPages > 1)
                                            <div class="mt-3 flex items-center justify-between border-t border-zinc-200 dark:border-zinc-700 pt-3">
                                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                    Showing <span class="font-medium tabular-nums">{{ (($currentPage - 1) * $picPerPage) + 1 }}</span> to
                                                    <span class="font-medium tabular-nums">{{ min($currentPage * $picPerPage, $totalFiltered) }}</span> of
                                                    <span class="font-medium tabular-nums">{{ $totalFiltered }}</span> result{{ $totalFiltered !== 1 ? 's' : '' }}
                                                </span>
                                                <div class="flex items-center gap-2">
                                                    @if($currentPage <= 1)
                                                        <flux:button
                                                            variant="outline"
                                                            size="sm"
                                                            disabled
                                                        >
                                                            <flux:icon.chevron-left class="w-4 h-4" />
                                                        </flux:button>
                                                    @else
                                                        <flux:button
                                                            variant="outline"
                                                            size="sm"
                                                            wire:click="previousPicPage({{ $picData['pic']->id }})"
                                                        >
                                                            <flux:icon.chevron-left class="w-4 h-4" />
                                                        </flux:button>
                                                    @endif

                                                    <span class="text-xs text-zinc-500 dark:text-zinc-400 tabular-nums">
                                                        Page <span class="font-medium">{{ $currentPage }}</span> of <span class="font-medium">{{ $totalPages }}</span>
                                                    </span>

                                                    @if($currentPage >= $totalPages)
                                                        <flux:button
                                                            variant="outline"
                                                            size="sm"
                                                            disabled
                                                        >
                                                            <flux:icon.chevron-right class="w-4 h-4" />
                                                        </flux:button>
                                                    @else
                                                        <flux:button
                                                            variant="outline"
                                                            size="sm"
                                                            wire:click="nextPicPage({{ $picData['pic']->id }}, {{ $totalPages }})"
                                                        >
                                                            <flux:icon.chevron-right class="w-4 h-4" />
                                                        </flux:button>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    @else
                                        <div class="text-center py-8 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                            <flux:icon.magnifying-glass class="mx-auto w-8 h-8 text-zinc-300 dark:text-zinc-600" />
                                            <h4 class="mt-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">No students found</h4>
                                            <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-1">Try adjusting your search or filter criteria</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <!-- Empty State -->
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <div class="py-8 text-center">
                            <flux:icon.user-group class="mx-auto w-8 h-8 text-zinc-300 dark:text-zinc-600" />
                            <h4 class="mt-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">No PIC Performance Data</h4>
                            <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-1">
                                There are no enrollments for this class yet, or no students have been enrolled by PICs.
                            </p>
                        </div>
                    </div>
                @endif
            @endif
        </div>
        <!-- End PIC Performance Tab -->

        <!-- Document Shipments Tab -->
        <div class="{{ $activeTab === 'shipments' ? 'block' : 'hidden' }}">
            @if($activeTab === 'shipments')
                @if($class->enable_document_shipment)
                    <div class="space-y-4">
                        <!-- Shipment Overview -->
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <div class="border-b border-zinc-200 dark:border-zinc-700 px-5 py-3 flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Document Shipments</h3>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Track and manage monthly document shipments to students</p>
                                </div>
                                <flux:button wire:click="generateShipmentForCurrentMonth" variant="primary" size="sm">
                                    <flux:icon.plus class="h-4 w-4 mr-1" />
                                    Generate Current Month
                                </flux:button>
                            </div>

                            <!-- Shipment Configuration Info -->
                            <div class="px-5 py-3">
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div>
                                        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Product</span>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $class->shipmentProduct?->name ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Frequency</span>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mt-0.5">{{ ucfirst($class->shipment_frequency) }}</p>
                                    </div>
                                    <div>
                                        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Qty/Student</span>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mt-0.5 tabular-nums">{{ $class->shipment_quantity_per_student }}</p>
                                    </div>
                                    <div>
                                        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Warehouse</span>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mt-0.5">{{ $class->shipmentWarehouse?->name ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Shipments Table -->
                        @php
                            $shipments = $this->filteredShipments;
                            // Get available years from shipments (database-agnostic)
                            $availableYears = $class->documentShipments()
                                ->get()
                                ->pluck('period_start_date')
                                ->map(fn($date) => $date->year)
                                ->unique()
                                ->sortDesc()
                                ->values();
                        @endphp

                        @if($class->documentShipments()->count() === 0)
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <div class="py-8 text-center">
                                    <flux:icon.truck class="w-8 h-8 mx-auto text-zinc-400 dark:text-zinc-500 mb-3" />
                                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-1">No Shipments Yet</h4>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-4">Generate your first shipment to start tracking document deliveries</p>
                                    <flux:button wire:click="generateShipmentForCurrentMonth" variant="primary" size="sm">
                                        Generate Shipment for {{ now()->format('F Y') }}
                                    </flux:button>
                                </div>
                            </div>
                        @else
                            <!-- Filters and Shipments Table -->
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <div class="border-b border-zinc-200 dark:border-zinc-700 px-5 py-3">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <!-- Year Filter -->
                                        <div class="w-40 shrink-0">
                                            <flux:select wire:model.live="filterYear" placeholder="All Years">
                                                <option value="">All Years</option>
                                                @foreach($availableYears as $year)
                                                    <option value="{{ $year }}">{{ $year }}</option>
                                                @endforeach
                                            </flux:select>
                                        </div>

                                        <!-- Month Filter -->
                                        <div class="w-40 shrink-0">
                                            <flux:select wire:model.live="filterMonth" placeholder="All Months" :disabled="!$filterYear">
                                                <option value="">All Months</option>
                                                @for($i = 1; $i <= 12; $i++)
                                                    <option value="{{ $i }}">{{ \Carbon\Carbon::create()->month($i)->format('F') }}</option>
                                                @endfor
                                            </flux:select>
                                        </div>

                                        <!-- Status Filter -->
                                        <div class="w-44 shrink-0">
                                            <flux:select wire:model.live="filterStatus" placeholder="All Statuses">
                                                <option value="">All Statuses</option>
                                                <option value="pending">Pending</option>
                                                <option value="processing">Processing</option>
                                                <option value="shipped">Shipped</option>
                                                <option value="delivered">Delivered</option>
                                                <option value="cancelled">Cancelled</option>
                                            </flux:select>
                                        </div>

                                        <!-- Reset Filters -->
                                        @if($filterYear || $filterMonth || $filterStatus)
                                            <flux:button wire:click="resetTableFilters" variant="ghost" size="sm">
                                                <flux:icon.x-mark class="h-4 w-4 mr-1" />
                                                Reset
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>

                                <!-- Table -->
                                @if($shipments->isEmpty())
                                    <div class="py-8 text-center">
                                        <flux:icon.truck class="w-8 h-8 mx-auto text-zinc-400 dark:text-zinc-500 mb-2" />
                                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">No shipments found</h4>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Try adjusting your filters</p>
                                    </div>
                                @else
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                                            <thead>
                                                <tr class="bg-zinc-50 dark:bg-zinc-800">
                                                    <th scope="col" class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                                                        Period
                                                    </th>
                                                    <th scope="col" class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                                                        Shipment #
                                                    </th>
                                                    <th scope="col" class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                                                        Status
                                                    </th>
                                                    <th scope="col" class="px-3 py-2 text-center text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                                                        Recipients
                                                    </th>
                                                    <th scope="col" class="px-3 py-2 text-center text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                                                        Quantity
                                                    </th>
                                                    <th scope="col" class="px-3 py-2 text-right text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                                                        Total Cost
                                                    </th>
                                                    <th scope="col" class="px-3 py-2 text-center text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                                                        Delivery Rate
                                                    </th>
                                                    <th scope="col" class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                                                        Scheduled
                                                    </th>
                                                    <th scope="col" class="px-3 py-2 text-center text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">
                                                        Actions
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($shipments as $shipment)
                                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                                        <!-- Period -->
                                                        <td class="px-3 py-2 whitespace-nowrap">
                                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $shipment->period_label }}</div>
                                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $shipment->period_start_date->format('M d') }} - {{ $shipment->period_end_date->format('M d, Y') }}</div>
                                                        </td>

                                                        <!-- Shipment # -->
                                                        <td class="px-3 py-2 whitespace-nowrap">
                                                            <div class="text-sm text-zinc-900 dark:text-zinc-100">{{ $shipment->shipment_number }}</div>
                                                        </td>

                                                        <!-- Status -->
                                                        <td class="px-3 py-2 whitespace-nowrap">
                                                            <flux:badge variant="{{ $shipment->status_color }}" size="sm">
                                                                {{ $shipment->status_label }}
                                                            </flux:badge>
                                                        </td>

                                                        <!-- Recipients -->
                                                        <td class="px-3 py-2 whitespace-nowrap text-center">
                                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 tabular-nums">{{ $shipment->total_recipients }}</div>
                                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">students</div>
                                                        </td>

                                                        <!-- Quantity -->
                                                        <td class="px-3 py-2 whitespace-nowrap text-center">
                                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 tabular-nums">{{ $shipment->total_quantity }}</div>
                                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">items</div>
                                                        </td>

                                                        <!-- Total Cost -->
                                                        <td class="px-3 py-2 whitespace-nowrap text-right">
                                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 tabular-nums">{{ $shipment->formatted_total_cost }}</div>
                                                        </td>

                                                        <!-- Delivery Rate -->
                                                        <td class="px-3 py-2 whitespace-nowrap text-center">
                                                            @php
                                                                $deliveryRate = $shipment->getDeliveryRate();
                                                                $rateColor = $deliveryRate >= 80 ? 'text-green-600' : ($deliveryRate >= 50 ? 'text-yellow-600' : 'text-red-600');
                                                            @endphp
                                                            <div class="text-sm font-medium tabular-nums {{ $rateColor }}">{{ $deliveryRate }}%</div>
                                                            <div class="text-xs text-zinc-500 dark:text-zinc-400 tabular-nums">
                                                                {{ $shipment->getDeliveredItemsCount() }}/{{ $shipment->items()->count() }}
                                                            </div>
                                                        </td>

                                                        <!-- Scheduled -->
                                                        <td class="px-3 py-2 whitespace-nowrap">
                                                            @if($shipment->scheduled_at)
                                                                <div class="text-sm text-zinc-900 dark:text-zinc-100">{{ $shipment->scheduled_at->format('M d, Y') }}</div>
                                                                @if($shipment->shipped_at)
                                                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">Shipped: {{ $shipment->shipped_at->format('M d') }}</div>
                                                                @elseif($shipment->delivered_at)
                                                                    <div class="text-xs text-green-600">Delivered: {{ $shipment->delivered_at->format('M d') }}</div>
                                                                @endif
                                                            @else
                                                                <span class="text-sm text-zinc-400 dark:text-zinc-500">Not scheduled</span>
                                                            @endif
                                                        </td>

                                                        <!-- Actions -->
                                                        <td class="px-3 py-2 whitespace-nowrap text-center">
                                                            <div class="flex items-center justify-center gap-1">
                                                                <flux:button wire:click="viewShipmentDetails({{ $shipment->id }})" size="sm" variant="ghost">
                                                                    <flux:icon.eye class="h-4 w-4" />
                                                                </flux:button>
                                                            </div>
                                                        </td>
                                                    </tr>

                                                    <!-- Expandable Details Row -->
                                                    @if($selectedShipmentId === $shipment->id)
                                                        <tr>
                                                            <td colspan="9" class="px-4 py-4 bg-zinc-50 dark:bg-zinc-800/50">
                                            <!-- Student-Level Details -->
                                            <div>
                                                <div class="mb-4 flex items-center justify-between flex-wrap gap-3">
                                                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Student Shipment Details</h4>
                                                    <div class="flex gap-2">
                                                        <flux:button wire:click="openImportModal({{ $shipment->id }})" size="sm" variant="outline">
                                                            <div class="flex items-center justify-center">
                                                                <flux:icon name="arrow-up-tray" class="w-4 h-4 mr-1" />
                                                                Import CSV
                                                            </div>
                                                        </flux:button>
                                                        <flux:button wire:click="exportShipmentItems({{ $shipment->id }})" size="sm" variant="outline">
                                                            <div class="flex items-center justify-center">
                                                                <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-1" />
                                                                Export CSV
                                                            </div>
                                                        </flux:button>
                                                    </div>
                                                </div>

                                                <!-- Minimalist Status Cards -->
                                                <div class="mb-4 grid grid-cols-2 md:grid-cols-4 gap-2">
                                                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-2.5">
                                                        <div class="text-[11px] font-medium uppercase tracking-wider text-yellow-600 dark:text-yellow-400 mb-0.5">Pending</div>
                                                        <div class="text-base font-bold text-yellow-900 dark:text-yellow-100 tabular-nums">
                                                            {{ $shipment->items->where('status', 'pending')->count() }}
                                                        </div>
                                                    </div>
                                                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-2.5">
                                                        <div class="text-[11px] font-medium uppercase tracking-wider text-blue-600 dark:text-blue-400 mb-0.5">Processing</div>
                                                        <div class="text-base font-bold text-blue-900 dark:text-blue-100 tabular-nums">
                                                            {{ $shipment->items->where('status', 'processing')->count() }}
                                                        </div>
                                                    </div>
                                                    <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-2.5">
                                                        <div class="text-[11px] font-medium uppercase tracking-wider text-purple-600 dark:text-purple-400 mb-0.5">Shipped</div>
                                                        <div class="text-base font-bold text-purple-900 dark:text-purple-100 tabular-nums">
                                                            {{ $shipment->items->where('status', 'shipped')->count() }}
                                                        </div>
                                                    </div>
                                                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-2.5">
                                                        <div class="text-[11px] font-medium uppercase tracking-wider text-green-600 dark:text-green-400 mb-0.5">Delivered</div>
                                                        <div class="text-base font-bold text-green-900 dark:text-green-100 tabular-nums">
                                                            {{ $shipment->items->where('status', 'delivered')->count() }}
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Import Progress Indicator -->
                                                @if($importProcessing)
                                                    <div class="mb-3 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
                                                        <div class="flex items-center space-x-3">
                                                            <svg class="animate-spin h-4 w-4 text-zinc-600 dark:text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                            </svg>
                                                            <div class="flex-1">
                                                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">Processing import...</p>
                                                                @if(!empty($importProgress))
                                                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                                                        Imported {{ $importProgress['imported'] ?? 0 }} rows,
                                                                        updated {{ $importProgress['updated'] ?? 0 }} tracking numbers
                                                                    </p>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- Bulk Actions for Student Items -->
                                                @if(count($selectedShipmentItemIds) > 0)
                                                    <div class="mb-3 px-3 py-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                                        <div class="flex flex-wrap gap-2 items-center">
                                                            <span class="text-sm font-medium text-blue-900 dark:text-blue-100">
                                                                {{ count($selectedShipmentItemIds) }} student(s) selected
                                                            </span>
                                                            <div class="flex gap-2">
                                                                <flux:button wire:click="bulkMarkItemsAsShipped" variant="outline" size="sm">
                                                                    <div class="flex items-center justify-center">
                                                                        <flux:icon name="truck" class="w-4 h-4 mr-1" />
                                                                        Mark as Shipped
                                                                    </div>
                                                                </flux:button>
                                                                <flux:button wire:click="bulkMarkItemsAsDelivered" variant="outline" size="sm">
                                                                    <div class="flex items-center justify-center">
                                                                        <flux:icon name="check-circle" class="w-4 h-4 mr-1" />
                                                                        Mark as Delivered
                                                                    </div>
                                                                </flux:button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- Search and Filter Bar -->
                                                <div class="mb-3 flex items-center gap-2 flex-wrap">
                                                    <div class="flex-1 min-w-[200px]">
                                                        <flux:input
                                                            wire:model.live.debounce.300ms="shipmentSearch"
                                                            placeholder="Search by student name..."
                                                            class="w-full"
                                                        >
                                                            <x-slot name="iconTrailing">
                                                                <flux:icon.magnifying-glass />
                                                            </x-slot>
                                                        </flux:input>
                                                    </div>
                                                    <div class="w-44 shrink-0">
                                                        <flux:select wire:model.live="shipmentStatusFilter" class="w-full">
                                                            <flux:select.option value="">All Statuses</flux:select.option>
                                                            <flux:select.option value="pending">Pending</flux:select.option>
                                                            <flux:select.option value="shipped">Shipped</flux:select.option>
                                                            <flux:select.option value="delivered">Delivered</flux:select.option>
                                                        </flux:select>
                                                    </div>
                                                    @if($shipmentSearch || $shipmentStatusFilter)
                                                        <flux:button wire:click="resetShipmentFilters" size="sm" variant="ghost">
                                                            <div class="flex items-center justify-center">
                                                                <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                                                                Clear
                                                            </div>
                                                        </flux:button>
                                                    @endif
                                                </div>

                                                @php
                                                    $filteredItems = $this->getFilteredShipmentItems($shipment->id);
                                                @endphp

                                                @if($filteredItems->isEmpty())
                                                    <div class="py-8 text-center rounded-lg border border-zinc-200 dark:border-zinc-700">
                                                        <flux:icon.magnifying-glass class="w-8 h-8 mx-auto text-zinc-400 dark:text-zinc-500 mb-2" />
                                                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-1">No students found</h4>
                                                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                                            @if($shipmentSearch || $shipmentStatusFilter)
                                                                Try adjusting your search or filters
                                                            @else
                                                                No students in this shipment
                                                            @endif
                                                        </p>
                                                    </div>
                                                @else
                                                    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                                                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                                                            <thead>
                                                                <tr class="bg-zinc-50 dark:bg-zinc-800">
                                                                    <th class="px-3 py-2 text-left">
                                                                        @php
                                                                            $filteredItemIds = $filteredItems->pluck('id')->toArray();
                                                                            $allSelected = count(array_intersect($this->selectedShipmentItemIds, $filteredItemIds)) === count($filteredItemIds) && count($filteredItemIds) > 0;
                                                                        @endphp
                                                                        <input
                                                                            type="checkbox"
                                                                            wire:click="toggleSelectAllItems({{ $shipment->id }})"
                                                                            @checked($allSelected)
                                                                            class="rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500"
                                                                        />
                                                                    </th>
                                                                    <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Student</th>
                                                                    <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Quantity</th>
                                                                    <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Payment</th>
                                                                    <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Notes</th>
                                                                    <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Status</th>
                                                                    <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Tracking</th>
                                                                    <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                                                                @foreach($filteredItems as $item)
                                                                    <tr class="{{ in_array($item->id, $selectedShipmentItemIds) ? 'bg-blue-50 dark:bg-blue-900/20' : '' }} hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                                                        <td class="px-3 py-2 whitespace-nowrap">
                                                                            <input
                                                                                type="checkbox"
                                                                                wire:model.live="selectedShipmentItemIds"
                                                                                value="{{ $item->id }}"
                                                                                class="rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500"
                                                                            />
                                                                        </td>
                                                                        <td class="px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100">
                                                                            {{ $item->student->name }}
                                                                        </td>
                                                                        <td class="px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 tabular-nums">
                                                                            {{ $item->quantity }}
                                                                        </td>
                                                                        <td class="px-3 py-2 text-sm">
                                                                            @php
                                                                                $enrollment = $item->student->enrollments()
                                                                                    ->where('course_id', $this->class->course_id)
                                                                                    ->first();
                                                                                $paidAmount = 0;
                                                                                if ($enrollment) {
                                                                                    // Match orders where the billing period START date falls within the shipment period
                                                                                    // This aligns with Payment Reports logic
                                                                                    $paidAmount = \App\Models\Order::where('enrollment_id', $enrollment->id)
                                                                                        ->where('student_id', $item->student_id)
                                                                                        ->where('period_start', '>=', $shipment->period_start_date)
                                                                                        ->where('period_start', '<=', $shipment->period_end_date)
                                                                                        ->where('status', 'paid')
                                                                                        ->sum('amount');
                                                                                }
                                                                            @endphp
                                                                            @if($paidAmount > 0)
                                                                                <span class="text-green-600 font-medium tabular-nums">RM {{ number_format($paidAmount, 2) }}</span>
                                                                            @else
                                                                                <span class="text-red-500">Unpaid</span>
                                                                            @endif
                                                                        </td>
                                                                        <td class="px-3 py-2 text-sm max-w-xs">
                                                                            @php
                                                                                $enrollmentNotes = $enrollment?->notes;
                                                                            @endphp
                                                                            @if($enrollmentNotes)
                                                                                <span class="text-zinc-600 dark:text-zinc-400 truncate block" title="{{ $enrollmentNotes }}">
                                                                                    {{ \Str::limit($enrollmentNotes, 30) }}
                                                                                </span>
                                                                            @else
                                                                                <span class="text-zinc-400 dark:text-zinc-500">-</span>
                                                                            @endif
                                                                        </td>
                                                                        <td class="px-3 py-2 text-sm">
                                                                            <flux:badge variant="{{ $item->status_color }}" size="sm">
                                                                                {{ $item->status_label }}
                                                                            </flux:badge>
                                                                        </td>
                                                                        <td class="px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100">
                                                                            {{ $item->tracking_number ?? '-' }}
                                                                        </td>
                                                                        <td class="px-3 py-2 text-sm">
                                                                            <div class="flex items-center gap-1">
                                                                                <flux:button wire:click="viewStudentShipmentDetails({{ $item->id }})" size="xs" variant="ghost" title="View Details">
                                                                                    <flux:icon name="eye" class="w-4 h-4" />
                                                                                </flux:button>
                                                                                <flux:button wire:click="editShipmentItem({{ $item->id }})" size="xs" variant="ghost" title="Edit">
                                                                                    <flux:icon name="pencil" class="w-4 h-4" />
                                                                                </flux:button>
                                                                                @if($item->status === 'pending')
                                                                                    <flux:button wire:click="markItemAsShipped({{ $item->id }})" size="xs" variant="outline">
                                                                                        Ship
                                                                                    </flux:button>
                                                                                @elseif($item->status === 'shipped')
                                                                                    <flux:button wire:click="markItemAsDelivered({{ $item->id }})" size="xs" variant="outline">
                                                                                        Deliver
                                                                                    </flux:button>
                                                                                @endif
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>

                                                    <!-- Pagination -->
                                                    @if($filteredItems->hasPages())
                                                        <div class="mt-3">
                                                            {{ $filteredItems->links() }}
                                                        </div>
                                                    @endif
                                                @endif
                                            </div>
                                                                </td>
                                                            </tr>
                                                        @endif
                            @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                            </div>
                        @endif
                    </div>
                @else
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <div class="py-8 text-center">
                            <flux:icon.x-circle class="w-8 h-8 mx-auto text-zinc-400 dark:text-zinc-500 mb-3" />
                            <h4 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-1">Document Shipments Not Enabled</h4>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-4">Enable document shipments in class settings to start tracking deliveries</p>
                            <flux:button href="{{ route('classes.edit', $class) }}" variant="primary" size="sm">
                                Go to Class Settings
                            </flux:button>
                        </div>
                    </div>
                @endif
            @endif
        </div>
        <!-- End Document Shipments Tab -->

        <!-- Notifications Tab -->
        <div class="{{ $activeTab === 'notifications' ? 'block' : 'hidden' }}">
            @if($activeTab === 'notifications')
                <livewire:admin.class-notification-settings :class="$class" />
            @endif
        </div>
        <!-- End Notifications Tab -->

        <!-- Upsell Tab -->
        <div class="{{ $activeTab === 'upsell' ? 'block' : 'hidden' }}">
            @if($class->sessions->count() > 0)
                {{-- Summary Stats --}}
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Sessions with Upsell</span>
                        <p class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1 tabular-nums">{{ $this->upsellStats['total_sessions'] }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Visitors</span>
                        <p class="text-xl font-semibold text-blue-600 dark:text-blue-400 mt-1 tabular-nums">{{ $this->upsellStats['total_visitors'] }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Conversions</span>
                        <p class="text-xl font-semibold text-emerald-600 dark:text-emerald-400 mt-1 tabular-nums">{{ $this->upsellStats['total_conversions'] }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Revenue</span>
                        <p class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1 tabular-nums">RM {{ number_format($this->upsellStats['total_revenue'], 2) }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                        <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Conversion Rate</span>
                        <p class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 mt-1 tabular-nums">{{ $this->upsellStats['conversion_rate'] }}%</p>
                    </div>
                </div>

                {{-- Filters --}}
                <div class="flex items-end gap-2 flex-wrap mb-4">
                    <div class="w-36 shrink-0">
                        <flux:input type="date" wire:model.live="upsellDateFrom" label="From" size="sm" />
                    </div>
                    <div class="w-36 shrink-0">
                        <flux:input type="date" wire:model.live="upsellDateTo" label="To" size="sm" />
                    </div>
                    <div class="w-40 shrink-0">
                        <flux:select wire:model.live="upsellFilterFunnelId" label="Funnel" size="sm" placeholder="All Funnels">
                            @foreach($this->availableFunnels as $funnel)
                                <flux:select.option value="{{ $funnel->id }}">{{ $funnel->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="w-40 shrink-0">
                        <flux:select wire:model.live="upsellFilterPicId" label="PIC" size="sm" placeholder="All PICs">
                            @foreach($this->upsellAvailablePics as $pic)
                                <flux:select.option value="{{ $pic->id }}">{{ $pic->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>

                {{-- Session List with Inline Upsell Management --}}
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <div class="border-b border-zinc-200 dark:border-zinc-700 px-5 py-3">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Session Upsell Management</h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Assign funnels and PICs directly to each session</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                                    <th class="text-left py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Date</th>
                                    <th class="text-left py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Day</th>
                                    <th class="text-left py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Time</th>
                                    <th class="text-left py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Status</th>
                                    <th class="text-left py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500 min-w-[180px]">Funnel</th>
                                    <th class="text-left py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500 min-w-[160px]">PIC</th>
                                    <th class="text-left py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Teacher</th>
                                    <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Commission %</th>
                                    <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Visitors</th>
                                    <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Orders</th>
                                    <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Revenue</th>
                                    <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Commission</th>
                                    <th class="text-center py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-100 dark:divide-zinc-800">
                                @forelse($this->upsellSessions as $session)
                                    <tr wire:key="upsell-session-{{ $session->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors">
                                        <td class="py-2 px-3 text-sm text-zinc-900 dark:text-zinc-100 tabular-nums whitespace-nowrap">
                                            {{ \Carbon\Carbon::parse($session->session_date)->format('M d, Y') }}
                                        </td>
                                        <td class="py-2 px-3 text-sm text-zinc-600 dark:text-zinc-400">
                                            {{ ucfirst(\Carbon\Carbon::parse($session->session_date)->format('l')) }}
                                        </td>
                                        <td class="py-2 px-3 text-sm text-zinc-600 dark:text-zinc-400 tabular-nums">
                                            {{ \Carbon\Carbon::parse($session->session_time)->format('h:i A') }}
                                        </td>
                                        <td class="py-2 px-3">
                                            @php
                                                $statusColors = [
                                                    'scheduled' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400',
                                                    'ongoing' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400',
                                                    'completed' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400',
                                                    'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400',
                                                    'no_show' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-500/20 dark:text-zinc-400',
                                                ];
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusColors[$session->status] ?? 'bg-zinc-100 text-zinc-700' }}">
                                                {{ ucfirst(str_replace('_', ' ', $session->status)) }}
                                            </span>
                                        </td>
                                        {{-- Funnel Multi-Select --}}
                                        <td class="py-1.5 px-3"
                                            x-data="{
                                                open: false,
                                                search: '',
                                                selectedIds: @js($session->upsell_funnel_ids ?? []),
                                                items: @js($this->availableFunnels->map(fn($f) => ['id' => $f->id, 'name' => $f->name])->values()),
                                                get filtered() {
                                                    let available = this.items.filter(i => !this.selectedIds.includes(i.id));
                                                    if (!this.search) return available;
                                                    return available.filter(i => i.name.toLowerCase().includes(this.search.toLowerCase()));
                                                },
                                                getName(id) {
                                                    let item = this.items.find(i => i.id === id);
                                                    return item ? item.name : '';
                                                },
                                                add(id) {
                                                    if (!this.selectedIds.includes(id)) {
                                                        this.selectedIds.push(id);
                                                        $wire.addSessionFunnel({{ $session->id }}, id);
                                                    }
                                                    this.search = '';
                                                },
                                                remove(id) {
                                                    this.selectedIds = this.selectedIds.filter(i => i !== id);
                                                    $wire.removeSessionFunnel({{ $session->id }}, id);
                                                }
                                            }"
                                            @click.outside="open = false; search = ''"
                                        >
                                            <div class="relative">
                                                <div class="flex flex-wrap gap-1 items-center min-h-[30px]">
                                                    <template x-for="id in selectedIds" :key="id">
                                                        <span class="inline-flex items-center gap-0.5 bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300 text-[10px] leading-tight px-1.5 py-0.5 rounded-full max-w-[120px]">
                                                            <span x-text="getName(id)" class="truncate"></span>
                                                            <button @click.stop="remove(id)" type="button" class="hover:text-blue-900 dark:hover:text-blue-100 shrink-0">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                            </button>
                                                        </span>
                                                    </template>
                                                    <button @click="open = !open" type="button" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 p-0.5 rounded hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                    </button>
                                                </div>
                                                <div x-show="open" x-transition.opacity.duration.150ms class="absolute z-50 mt-1 w-56 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-600 rounded-lg shadow-lg" style="display: none;">
                                                    <div class="p-1.5">
                                                        <input x-model="search" @keydown.escape="open = false; search = ''" type="text" placeholder="Search funnel..." class="w-full text-xs rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-200 py-1.5 px-2 focus:border-blue-500 focus:ring-blue-500" @click.stop />
                                                    </div>
                                                    <div class="max-h-40 overflow-y-auto">
                                                        <template x-for="item in filtered" :key="item.id">
                                                            <button @click="add(item.id)" type="button" class="w-full text-left px-3 py-1.5 text-xs text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors" x-text="item.name"></button>
                                                        </template>
                                                        <div x-show="filtered.length === 0 && search" class="px-3 py-2 text-xs text-zinc-400">No funnels found</div>
                                                        <div x-show="filtered.length === 0 && !search" class="px-3 py-2 text-xs text-zinc-400">All funnels assigned</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        {{-- PIC Multi-Select --}}
                                        <td class="py-1.5 px-3"
                                            x-data="{
                                                open: false,
                                                search: '',
                                                selectedIds: @js($session->upsell_pic_user_ids ?? []),
                                                items: @js($this->upsellAvailablePics->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values()),
                                                get filtered() {
                                                    let available = this.items.filter(i => !this.selectedIds.includes(i.id));
                                                    if (!this.search) return available;
                                                    return available.filter(i => i.name.toLowerCase().includes(this.search.toLowerCase()));
                                                },
                                                getName(id) {
                                                    let item = this.items.find(i => i.id === id);
                                                    return item ? item.name : '';
                                                },
                                                add(id) {
                                                    if (!this.selectedIds.includes(id)) {
                                                        this.selectedIds.push(id);
                                                        $wire.addSessionPic({{ $session->id }}, id);
                                                    }
                                                    this.search = '';
                                                },
                                                remove(id) {
                                                    this.selectedIds = this.selectedIds.filter(i => i !== id);
                                                    $wire.removeSessionPic({{ $session->id }}, id);
                                                }
                                            }"
                                            @click.outside="open = false; search = ''"
                                        >
                                            <div class="relative">
                                                <div class="flex flex-wrap gap-1 items-center min-h-[30px]">
                                                    <template x-for="id in selectedIds" :key="id">
                                                        <span class="inline-flex items-center gap-0.5 bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-[10px] leading-tight px-1.5 py-0.5 rounded-full max-w-[120px]">
                                                            <span x-text="getName(id)" class="truncate"></span>
                                                            <button @click.stop="remove(id)" type="button" class="hover:text-emerald-900 dark:hover:text-emerald-100 shrink-0">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                            </button>
                                                        </span>
                                                    </template>
                                                    <button @click="open = !open" type="button" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 p-0.5 rounded hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                    </button>
                                                </div>
                                                <div x-show="open" x-transition.opacity.duration.150ms class="absolute z-50 mt-1 w-56 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-600 rounded-lg shadow-lg" style="display: none;">
                                                    <div class="p-1.5">
                                                        <input x-model="search" @keydown.escape="open = false; search = ''" type="text" placeholder="Search PIC..." class="w-full text-xs rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-200 py-1.5 px-2 focus:border-blue-500 focus:ring-blue-500" @click.stop />
                                                    </div>
                                                    <div class="max-h-40 overflow-y-auto">
                                                        <template x-for="item in filtered" :key="item.id">
                                                            <button @click="add(item.id)" type="button" class="w-full text-left px-3 py-1.5 text-xs text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors" x-text="item.name"></button>
                                                        </template>
                                                        <div x-show="filtered.length === 0 && search" class="px-3 py-2 text-xs text-zinc-400">No PICs found</div>
                                                        <div x-show="filtered.length === 0 && !search" class="px-3 py-2 text-xs text-zinc-400">All PICs assigned</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        {{-- Teacher Multi-Select --}}
                                        <td class="py-1.5 px-3"
                                            x-data="{
                                                open: false,
                                                search: '',
                                                selectedIds: @js($session->upsell_teacher_ids ?? []),
                                                items: @js($this->upsellAvailableTeachers->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->values()),
                                                get filtered() {
                                                    let available = this.items.filter(i => !this.selectedIds.includes(i.id));
                                                    if (!this.search) return available;
                                                    return available.filter(i => i.name.toLowerCase().includes(this.search.toLowerCase()));
                                                },
                                                getName(id) {
                                                    let item = this.items.find(i => i.id === id);
                                                    return item ? item.name : '';
                                                },
                                                add(id) {
                                                    if (!this.selectedIds.includes(id)) {
                                                        this.selectedIds.push(id);
                                                        $wire.addSessionTeacher({{ $session->id }}, id);
                                                    }
                                                    this.search = '';
                                                },
                                                remove(id) {
                                                    this.selectedIds = this.selectedIds.filter(i => i !== id);
                                                    $wire.removeSessionTeacher({{ $session->id }}, id);
                                                }
                                            }"
                                            @click.outside="open = false; search = ''"
                                        >
                                            <div class="relative">
                                                <div class="flex flex-wrap gap-1 items-center min-h-[30px]">
                                                    <template x-for="id in selectedIds" :key="id">
                                                        <span class="inline-flex items-center gap-0.5 bg-violet-100 dark:bg-violet-500/20 text-violet-700 dark:text-violet-300 text-[10px] leading-tight px-1.5 py-0.5 rounded-full max-w-[120px]">
                                                            <span x-text="getName(id)" class="truncate"></span>
                                                            <button @click.stop="remove(id)" type="button" class="hover:text-violet-900 dark:hover:text-violet-100 shrink-0">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                            </button>
                                                        </span>
                                                    </template>
                                                    <button @click="open = !open" type="button" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 p-0.5 rounded hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                    </button>
                                                </div>
                                                <div x-show="open" x-transition.opacity.duration.150ms class="absolute z-50 mt-1 w-56 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-600 rounded-lg shadow-lg" style="display: none;">
                                                    <div class="p-1.5">
                                                        <input x-model="search" @keydown.escape="open = false; search = ''" type="text" placeholder="Search teacher..." class="w-full text-xs rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 dark:text-zinc-200 py-1.5 px-2 focus:border-blue-500 focus:ring-blue-500" @click.stop />
                                                    </div>
                                                    <div class="max-h-40 overflow-y-auto">
                                                        <template x-for="item in filtered" :key="item.id">
                                                            <button @click="add(item.id)" type="button" class="w-full text-left px-3 py-1.5 text-xs text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors" x-text="item.name"></button>
                                                        </template>
                                                        <div x-show="filtered.length === 0 && search" class="px-3 py-2 text-xs text-zinc-400">No teachers found</div>
                                                        <div x-show="filtered.length === 0 && !search" class="px-3 py-2 text-xs text-zinc-400">All teachers assigned</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        {{-- Commission Rate --}}
                                        <td class="py-1.5 px-3 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <input type="number"
                                                       value="{{ $session->upsell_teacher_commission_rate ?? '' }}"
                                                       min="0" max="100" step="0.5"
                                                       placeholder="—"
                                                       class="w-16 text-right text-xs rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 py-1.5 px-2 focus:border-blue-500 focus:ring-blue-500 tabular-nums"
                                                       wire:change="updateSessionCommissionRate({{ $session->id }}, $event.target.value)" />
                                                <span class="text-xs text-zinc-400">%</span>
                                            </div>
                                        </td>
                                        <td class="py-2 px-3 text-right font-medium text-blue-600 dark:text-blue-400 tabular-nums">
                                            {{ $session->upsell_visitors_count ?? 0 }}
                                        </td>
                                        <td class="py-2 px-3 text-right font-medium text-zinc-900 dark:text-zinc-100 tabular-nums">
                                            {{ $session->upsell_orders_count ?? 0 }}
                                        </td>
                                        <td class="py-2 px-3 text-right font-medium text-zinc-900 dark:text-zinc-100 tabular-nums whitespace-nowrap">
                                            RM {{ number_format($session->upsell_revenue ?? 0, 2) }}
                                        </td>
                                        {{-- Calculated Commission --}}
                                        <td class="py-2 px-3 text-right font-medium tabular-nums whitespace-nowrap {{ ($session->upsell_teacher_commission_rate && ($session->upsell_revenue ?? 0) > 0) ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-400' }}">
                                            @if($session->upsell_teacher_commission_rate && ($session->upsell_revenue ?? 0) > 0)
                                                RM {{ number_format(($session->upsell_revenue * $session->upsell_teacher_commission_rate / 100), 2) }}
                                            @else
                                                RM 0.00
                                            @endif
                                        </td>
                                        <td class="py-2 px-3 text-center">
                                            <flux:button variant="ghost" size="sm" wire:click="showUpsellDetail({{ $session->id }})">
                                                <flux:icon name="eye" class="w-4 h-4" />
                                            </flux:button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="13" class="py-8 text-center">
                                            <flux:icon name="calendar" class="w-8 h-8 mx-auto text-zinc-300 dark:text-zinc-600 mb-2" />
                                            <p class="text-xs text-zinc-400 dark:text-zinc-500">No sessions found matching your filters.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                {{-- No Sessions Empty State --}}
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 py-12 px-6 text-center">
                    <flux:icon name="calendar" class="w-8 h-8 mx-auto text-zinc-300 dark:text-zinc-600 mb-2" />
                    <h4 class="text-sm font-medium text-zinc-500 dark:text-zinc-400">No Sessions Available</h4>
                    <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-1">This class doesn't have any sessions yet. Configure a timetable and generate sessions first.</p>
                </div>
            @endif
        </div>
        <!-- End Upsell Tab -->

    </div>

    <!-- Upsell Session Detail Modal -->
    <flux:modal name="upsell-detail" :show="$showUpsellDetailModal" wire:model="showUpsellDetailModal" class="md:w-3xl">
        @if($this->upsellDetailSession)
            @php
                $detail = $this->upsellDetailSession;
                $funnels = $detail->upsellFunnels();
                $pics = $detail->upsellPics();
                $orders = $detail->funnelOrders->where('class_session_id', $detail->id);
                $statusColors = [
                    'scheduled' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400',
                    'ongoing' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400',
                    'completed' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400',
                    'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400',
                    'no_show' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-500/20 dark:text-zinc-400',
                ];
            @endphp
            <div class="pt-6">
                {{-- Header --}}
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <flux:heading size="lg">Session Upsell Detail</flux:heading>
                        <flux:text class="mt-1">{{ $detail->session_date->format('l, M d, Y') }} at {{ \Carbon\Carbon::parse($detail->session_time)->format('h:i A') }}</flux:text>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $statusColors[$detail->status] ?? 'bg-zinc-100 text-zinc-700' }}">
                        {{ ucfirst(str_replace('_', ' ', $detail->status)) }}
                    </span>
                </div>

                {{-- Summary Cards --}}
                <div class="grid grid-cols-4 gap-3 mb-6">
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 text-center">
                        <span class="text-[10px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Visitors</span>
                        <p class="text-lg font-semibold text-blue-600 dark:text-blue-400 mt-0.5 tabular-nums">{{ \App\Models\FunnelSession::where('class_session_id', $detail->id)->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 text-center">
                        <span class="text-[10px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Funnels</span>
                        <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5 tabular-nums">{{ $funnels->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 text-center">
                        <span class="text-[10px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Orders</span>
                        <p class="text-lg font-semibold text-emerald-600 dark:text-emerald-400 mt-0.5 tabular-nums">{{ $detail->upsell_orders_count ?? 0 }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 text-center">
                        <span class="text-[10px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Revenue</span>
                        <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mt-0.5 tabular-nums">RM {{ number_format($detail->upsell_revenue ?? 0, 2) }}</p>
                    </div>
                </div>

                {{-- Assigned Funnels --}}
                <div class="mb-5">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2">Assigned Funnels</h4>
                    @if($funnels->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($funnels as $funnel)
                                @php
                                    $funnelOrders = $orders->where('funnel_id', $funnel->id);
                                    $funnelRevenue = $funnelOrders->sum('funnel_revenue');
                                    $salesUrl = route('funnel.show', $funnel->slug) . '?cls=' . $detail->id;
                                @endphp
                                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="flex items-center justify-center w-7 h-7 rounded-full bg-blue-100 dark:bg-blue-500/20">
                                                <flux:icon name="funnel" class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
                                            </span>
                                            <div>
                                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $funnel->name }}</p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xs font-medium text-zinc-900 dark:text-zinc-100 tabular-nums">{{ $funnelOrders->count() }} {{ Str::plural('order', $funnelOrders->count()) }}</p>
                                            <p class="text-[11px] text-zinc-400 tabular-nums">RM {{ number_format($funnelRevenue, 2) }}</p>
                                        </div>
                                    </div>
                                    {{-- Sales Page Link --}}
                                    <div class="flex items-center gap-2 mt-2 rounded-md bg-zinc-50 dark:bg-zinc-800 px-3 py-2"
                                         x-data="{ copied: false }">
                                        <flux:icon name="link" class="w-3.5 h-3.5 text-zinc-400 shrink-0" />
                                        <a href="{{ $salesUrl }}" target="_blank" class="text-xs text-blue-600 dark:text-blue-400 hover:underline truncate flex-1">
                                            {{ $salesUrl }}
                                        </a>
                                        <button type="button"
                                                @click="navigator.clipboard.writeText('{{ $salesUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                                class="shrink-0 text-xs text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 transition-colors flex items-center gap-1">
                                            <template x-if="!copied">
                                                <span class="flex items-center gap-1">
                                                    <flux:icon name="clipboard" class="w-3.5 h-3.5" />
                                                    Copy
                                                </span>
                                            </template>
                                            <template x-if="copied">
                                                <span class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                                    <flux:icon name="check" class="w-3.5 h-3.5" />
                                                    Copied!
                                                </span>
                                            </template>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-lg border border-dashed border-zinc-200 dark:border-zinc-700 py-4 text-center">
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">No funnels assigned to this session</p>
                        </div>
                    @endif
                </div>

                {{-- Assigned PICs --}}
                <div class="mb-5">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2">Assigned PICs</h4>
                    @if($pics->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach($pics as $pic)
                                <div class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2">
                                    <span class="flex items-center justify-center w-7 h-7 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs font-semibold">
                                        {{ strtoupper(substr($pic->name, 0, 1)) }}
                                    </span>
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $pic->name }}</p>
                                        <p class="text-[11px] text-zinc-400 dark:text-zinc-500">{{ ucfirst(str_replace('_', ' ', $pic->role ?? '')) }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-lg border border-dashed border-zinc-200 dark:border-zinc-700 py-4 text-center">
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">No PICs assigned to this session</p>
                        </div>
                    @endif
                </div>

                {{-- Teachers & Commission --}}
                <div class="mb-5">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2">Teachers & Commission</h4>
                    @php
                        $upsellTeachers = $detail->upsellTeachers();
                        $commissionRate = $detail->upsell_teacher_commission_rate;
                        $revenue = $detail->upsell_revenue ?? 0;
                        $commissionAmount = ($commissionRate && $revenue > 0) ? ($revenue * $commissionRate / 100) : 0;
                    @endphp
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-3">
                        {{-- Commission Summary --}}
                        <div class="flex items-center justify-between mb-3 pb-3 border-b border-zinc-100 dark:border-zinc-700">
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                @if($commissionRate)
                                    Commission Rate: <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $commissionRate }}%</span>
                                @else
                                    No commission rate set
                                @endif
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold {{ $commissionAmount > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-400' }} tabular-nums">
                                    RM {{ number_format($commissionAmount, 2) }}
                                </p>
                            </div>
                        </div>
                        {{-- Teacher List --}}
                        @if($upsellTeachers->isNotEmpty())
                            <div class="flex flex-wrap gap-2">
                                @foreach($upsellTeachers as $teacher)
                                    <div class="inline-flex items-center gap-2 rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2">
                                        <span class="flex items-center justify-center w-7 h-7 rounded-full bg-violet-100 dark:bg-violet-500/20 text-violet-700 dark:text-violet-400 text-xs font-semibold">
                                            {{ strtoupper(substr($teacher->name, 0, 1)) }}
                                        </span>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $teacher->name }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">No teachers assigned to this session</p>
                        @endif
                    </div>
                </div>

                {{-- Orders Table --}}
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2">Order History</h4>
                    @if($orders->isNotEmpty())
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                                        <th class="text-left py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Order</th>
                                        <th class="text-left py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Funnel</th>
                                        <th class="text-left py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Type</th>
                                        <th class="text-right py-2 px-3 text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @foreach($orders as $order)
                                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                            <td class="py-2 px-3 text-xs text-zinc-900 dark:text-zinc-100 tabular-nums">#{{ $order->id }}</td>
                                            <td class="py-2 px-3 text-xs text-zinc-600 dark:text-zinc-400">{{ $order->funnel?->name ?? '—' }}</td>
                                            <td class="py-2 px-3">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $order->order_type === 'main' ? 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400' }}">
                                                    {{ ucfirst($order->order_type ?? 'main') }}
                                                </span>
                                            </td>
                                            <td class="py-2 px-3 text-right text-xs font-medium text-zinc-900 dark:text-zinc-100 tabular-nums">
                                                RM {{ number_format($order->funnel_revenue, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-t border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                                    <tr>
                                        <td colspan="3" class="py-2 px-3 text-xs font-semibold text-zinc-600 dark:text-zinc-400">Total</td>
                                        <td class="py-2 px-3 text-right text-xs font-semibold text-zinc-900 dark:text-zinc-100 tabular-nums">
                                            RM {{ number_format($orders->sum('funnel_revenue'), 2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="rounded-lg border border-dashed border-zinc-200 dark:border-zinc-700 py-6 text-center">
                            <flux:icon name="shopping-bag" class="w-6 h-6 mx-auto text-zinc-300 dark:text-zinc-600 mb-1" />
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">No orders linked to this session yet</p>
                        </div>
                    @endif
                </div>

                {{-- Close Button --}}
                <div class="mt-6 flex justify-end">
                    <flux:button variant="ghost" wire:click="closeUpsellDetail">Close</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Generate Shipment Modal -->
    <flux:modal name="generate-shipment" :show="$showShipmentModal" wire:model="showShipmentModal">
        <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
            <flux:heading size="lg">Generate Document Shipment</flux:heading>
            <flux:text class="mt-2">Select the month and year for the shipment generation</flux:text>
        </div>

        <div class="space-y-4">
            <flux:field>
                <flux:label>Year</flux:label>
                <flux:select wire:model="shipmentYear" class="w-full">
                    @for($y = now()->year; $y <= now()->year + 1; $y++)
                        <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                    @endfor
                    @for($y = now()->year - 1; $y >= now()->year - 5; $y--)
                        <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                    @endfor
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Month</flux:label>
                <flux:select wire:model="shipmentMonth" class="w-full">
                    <flux:select.option value="1">January</flux:select.option>
                    <flux:select.option value="2">February</flux:select.option>
                    <flux:select.option value="3">March</flux:select.option>
                    <flux:select.option value="4">April</flux:select.option>
                    <flux:select.option value="5">May</flux:select.option>
                    <flux:select.option value="6">June</flux:select.option>
                    <flux:select.option value="7">July</flux:select.option>
                    <flux:select.option value="8">August</flux:select.option>
                    <flux:select.option value="9">September</flux:select.option>
                    <flux:select.option value="10">October</flux:select.option>
                    <flux:select.option value="11">November</flux:select.option>
                    <flux:select.option value="12">December</flux:select.option>
                </flux:select>
            </flux:field>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start space-x-2">
                    <flux:icon.information-circle class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
                    <div>
                        <flux:text class="text-blue-800 font-medium text-sm">
                            Subscription Filter Active
                        </flux:text>
                        <flux:text class="text-blue-700 text-xs mt-0.5">
                            Only students with active subscriptions will be included in this shipment
                        </flux:text>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2 mt-6">
            <flux:button variant="ghost" wire:click="closeShipmentModal">Cancel</flux:button>
            <flux:button variant="primary" wire:click="generateShipment" wire:loading.attr="disabled">
                <div wire:loading.remove wire:target="generateShipment">
                    <flux:icon.plus class="h-4 w-4 mr-1" />
                    Generate Shipment
                </div>
                <div wire:loading wire:target="generateShipment" class="flex items-center">
                    <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Processing...
                </div>
            </flux:button>
        </div>
    </flux:modal>

    <!-- Import CSV Modal -->
    <flux:modal name="import-csv" :show="$showImportModal" wire:model="showImportModal">
        <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
            <flux:heading size="lg">Import Tracking Numbers</flux:heading>
            <flux:text class="mt-2">Upload a CSV file to update tracking numbers for this shipment</flux:text>
        </div>

        <div class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start space-x-2">
                    <flux:icon.information-circle class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
                    <div>
                        <flux:text class="text-blue-800 font-medium text-sm mb-2">
                            CSV Format Instructions
                        </flux:text>
                        <ul class="text-blue-700 text-xs space-y-1 list-disc list-inside">
                            <li>Use the exported CSV as a template</li>
                            <li>Fill in the "Tracking Number" column (column 11)</li>
                            <li>You can update student addresses in columns 3-8 (optional)</li>
                            <li>Leave header row intact</li>
                            <li>Choose matching method below (name or phone)</li>
                            <li>Maximum file size: 10MB</li>
                        </ul>
                    </div>
                </div>
            </div>

            <flux:field>
                <flux:label>Match Students By</flux:label>
                <flux:radio.group wire:model="matchBy">
                    <flux:radio value="name" label="Student Name" description="Match students by their full name (must match exactly)" />
                    <flux:radio value="phone" label="Phone Number" description="Match students by their phone number (recommended for accuracy)" />
                </flux:radio.group>
                <flux:error name="matchBy" />
            </flux:field>

            <flux:field>
                <flux:label>Select CSV File</flux:label>
                <flux:input type="file" wire:model.live="importFile" accept=".csv,.txt" />
                <flux:error name="importFile" />
                <flux:text class="mt-1 text-xs">
                    Accepted formats: CSV (.csv, .txt). Maximum file size: 10MB
                </flux:text>
            </flux:field>

            <div wire:loading wire:target="importFile" class="text-sm text-gray-600 mt-2">
                <div class="flex items-center">
                    <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Uploading file...
                </div>
            </div>

            @if($importFile)
                <div class="text-sm text-green-600 mt-2 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    File ready: {{ $importFile->getClientOriginalName() }}
                </div>
            @endif
        </div>

        <div class="flex justify-end gap-2 mt-6">
            <flux:button variant="ghost" wire:click="closeImportModal">Cancel</flux:button>
            <flux:button variant="primary" wire:click="importShipmentTracking" wire:loading.attr="disabled" :disabled="!$importFile">
                <div wire:loading.remove wire:target="importShipmentTracking">
                    <flux:icon.arrow-up-tray class="h-4 w-4 mr-1" />
                    Import Tracking Numbers
                </div>
                <div wire:loading wire:target="importShipmentTracking" class="flex items-center">
                    <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Importing...
                </div>
            </flux:button>
        </div>
    </flux:modal>

    <!-- Import Result Modal -->
    <flux:modal name="import-result" :show="$showImportResultModal" wire:model="showImportResultModal">
        <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
            @if(isset($importResult['status']) && $importResult['status'] === 'completed')
                <div class="flex items-center gap-2">
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <flux:icon.check class="w-6 h-6 text-green-600" />
                    </div>
                    <flux:heading size="lg">Import Successful</flux:heading>
                </div>
            @else
                <div class="flex items-center gap-2">
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                        <flux:icon.x-mark class="w-6 h-6 text-red-600" />
                    </div>
                    <flux:heading size="lg">Import Failed</flux:heading>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            @if(isset($importResult['status']) && $importResult['status'] === 'completed')
                <!-- Success Summary -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <flux:text class="text-green-800 font-medium">Total Rows Processed:</flux:text>
                            <flux:text class="text-green-900 font-semibold">{{ $importResult['imported'] ?? 0 }}</flux:text>
                        </div>
                        <div class="flex items-center justify-between">
                            <flux:text class="text-green-800 font-medium">Tracking Numbers Updated:</flux:text>
                            <flux:text class="text-green-900 font-semibold">{{ $importResult['updated'] ?? 0 }}</flux:text>
                        </div>
                    </div>
                </div>

                @if(isset($importResult['errors']) && count($importResult['errors']) > 0)
                    <!-- Errors Section -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <flux:text class="text-yellow-800 font-medium mb-2">Some Issues Occurred:</flux:text>
                        <div class="max-h-48 overflow-y-auto">
                            <ul class="text-yellow-700 text-sm space-y-1 list-disc list-inside">
                                @foreach($importResult['errors'] as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @else
                    <flux:text class="text-gray-600">All tracking numbers were successfully updated!</flux:text>
                @endif
            @else
                <!-- Error Message -->
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <flux:text class="text-red-800">
                        {{ $importResult['error'] ?? 'An unknown error occurred during the import process.' }}
                    </flux:text>
                </div>
            @endif
        </div>

        <div class="flex justify-end gap-2 mt-6">
            <flux:button variant="primary" wire:click="closeImportResultModal">
                Close
            </flux:button>
        </div>
    </flux:modal>

    <!-- Student Shipment Details Modal -->
    <flux:modal name="student-shipment-details" :show="$showStudentShipmentModal" wire:model="showStudentShipmentModal" class="max-w-3xl">
        @if($selectedShipmentItem)
            <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
                <flux:heading size="lg">Shipment Details</flux:heading>
                <flux:text class="mt-2">Complete student and shipment information</flux:text>
            </div>

            <div class="space-y-6">
                <!-- Student Information -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <flux:icon name="user" class="w-5 h-5 text-gray-600" />
                        <flux:heading size="md">Student Information</flux:heading>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <flux:text class="text-gray-500 text-sm">Name</flux:text>
                                <flux:text class="font-medium">{{ $selectedShipmentItem->student->name }}</flux:text>
                            </div>
                            <div>
                                <flux:text class="text-gray-500 text-sm">Phone</flux:text>
                                <flux:text class="font-medium">{{ $selectedShipmentItem->student->phone ?? 'N/A' }}</flux:text>
                            </div>
                            <div>
                                <flux:text class="text-gray-500 text-sm">Email</flux:text>
                                <flux:text class="font-medium">{{ $selectedShipmentItem->student->user->email ?? 'N/A' }}</flux:text>
                            </div>
                            <div>
                                <flux:text class="text-gray-500 text-sm">Registration Number</flux:text>
                                <flux:text class="font-medium">{{ $selectedShipmentItem->student->registration_number ?? 'N/A' }}</flux:text>
                            </div>
                        </div>

                        @if($selectedShipmentItem->student->address_line_1 || $selectedShipmentItem->student->address_line_2 || $selectedShipmentItem->student->city)
                            <div class="pt-3 border-t border-gray-200">
                                <flux:text class="text-gray-500 text-sm mb-2">Shipping Address</flux:text>
                                <flux:text class="font-medium">
                                    @if($selectedShipmentItem->student->address_line_1)
                                        {{ $selectedShipmentItem->student->address_line_1 }}<br>
                                    @endif
                                    @if($selectedShipmentItem->student->address_line_2)
                                        {{ $selectedShipmentItem->student->address_line_2 }}<br>
                                    @endif
                                    @if($selectedShipmentItem->student->city || $selectedShipmentItem->student->state)
                                        {{ $selectedShipmentItem->student->city }}@if($selectedShipmentItem->student->city && $selectedShipmentItem->student->state), @endif{{ $selectedShipmentItem->student->state }}
                                    @endif
                                    @if($selectedShipmentItem->student->postcode)
                                        {{ $selectedShipmentItem->student->postcode }}
                                    @endif
                                    @if($selectedShipmentItem->student->country)
                                        <br>{{ $selectedShipmentItem->student->country }}
                                    @endif
                                </flux:text>
                            </div>
                        @else
                            <div class="pt-3 border-t border-gray-200">
                                <flux:text class="text-gray-500 text-sm">No shipping address on file</flux:text>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Shipment Information -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <flux:icon name="truck" class="w-5 h-5 text-gray-600" />
                        <flux:heading size="md">Shipment Information</flux:heading>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <flux:text class="text-gray-500 text-sm">Shipment Number</flux:text>
                                <flux:text class="font-medium">{{ $selectedShipmentItem->shipment->shipment_number }}</flux:text>
                            </div>
                            <div>
                                <flux:text class="text-gray-500 text-sm">Period</flux:text>
                                <flux:text class="font-medium">{{ $selectedShipmentItem->shipment->period_label }}</flux:text>
                            </div>
                            <div>
                                <flux:text class="text-gray-500 text-sm">Product</flux:text>
                                <flux:text class="font-medium">{{ $selectedShipmentItem->shipment->product->name ?? 'N/A' }}</flux:text>
                            </div>
                            <div>
                                <flux:text class="text-gray-500 text-sm">Warehouse</flux:text>
                                <flux:text class="font-medium">{{ $selectedShipmentItem->shipment->warehouse->name ?? 'N/A' }}</flux:text>
                            </div>
                            <div>
                                <flux:text class="text-gray-500 text-sm">Quantity</flux:text>
                                <flux:text class="font-medium">{{ $selectedShipmentItem->quantity }}</flux:text>
                            </div>
                            <div>
                                <flux:text class="text-gray-500 text-sm">Status</flux:text>
                                <flux:badge variant="{{ $selectedShipmentItem->status_color }}" size="sm">
                                    {{ $selectedShipmentItem->status_label }}
                                </flux:badge>
                            </div>
                        </div>

                        @if($selectedShipmentItem->tracking_number)
                            <div class="pt-3 border-t border-gray-200">
                                <flux:text class="text-gray-500 text-sm">Tracking Number</flux:text>
                                <flux:text class="font-medium text-lg">{{ $selectedShipmentItem->tracking_number }}</flux:text>
                            </div>
                        @endif

                        <div class="pt-3 border-t border-gray-200 grid grid-cols-2 gap-4">
                            @if($selectedShipmentItem->shipped_at)
                                <div>
                                    <flux:text class="text-gray-500 text-sm">Shipped At</flux:text>
                                    <flux:text class="font-medium">{{ $selectedShipmentItem->shipped_at->format('M d, Y H:i') }}</flux:text>
                                </div>
                            @endif
                            @if($selectedShipmentItem->delivered_at)
                                <div>
                                    <flux:text class="text-gray-500 text-sm">Delivered At</flux:text>
                                    <flux:text class="font-medium">{{ $selectedShipmentItem->delivered_at->format('M d, Y H:i') }}</flux:text>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Enrollment Information -->
                @if($selectedShipmentItem->student->enrollments->count() > 0)
                    <div>
                        <div class="flex items-center gap-2 mb-4">
                            <flux:icon name="academic-cap" class="w-5 h-5 text-gray-600" />
                            <flux:heading size="md">Active Enrollments</flux:heading>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="space-y-2">
                                @foreach($selectedShipmentItem->student->enrollments as $enrollment)
                                    <div class="flex items-center justify-between py-2 border-b border-gray-200 last:border-0">
                                        <div>
                                            <flux:text class="font-medium">{{ $enrollment->course->name ?? 'N/A' }}</flux:text>
                                            <flux:text class="text-sm text-gray-500">Enrolled on {{ $enrollment->enrollment_date?->format('M d, Y') ?? 'N/A' }}</flux:text>
                                        </div>
                                        <flux:badge variant="{{ $enrollment->subscription_status === 'active' ? 'success' : 'secondary' }}" size="sm">
                                            {{ ucfirst($enrollment->subscription_status) }}
                                        </flux:badge>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex justify-between gap-2 mt-6 pt-4 border-t border-gray-200">
                <div class="flex gap-2">
                    @if($selectedShipmentItem->status === 'pending')
                        <flux:button wire:click="markItemAsShipped({{ $selectedShipmentItem->id }})" variant="primary">
                            <flux:icon name="truck" class="w-4 h-4 mr-1" />
                            Mark as Shipped
                        </flux:button>
                    @elseif($selectedShipmentItem->status === 'shipped')
                        <flux:button wire:click="markItemAsDelivered({{ $selectedShipmentItem->id }})" variant="primary">
                            <flux:icon name="check-circle" class="w-4 h-4 mr-1" />
                            Mark as Delivered
                        </flux:button>
                    @endif
                </div>
                <flux:button variant="ghost" wire:click="closeStudentShipmentModal">
                    Close
                </flux:button>
            </div>
        @endif
    </flux:modal>

    <!-- Edit Shipment Item Modal -->
    <flux:modal name="edit-shipment-item" :show="$showEditShipmentItemModal" wire:model="showEditShipmentItemModal" class="max-w-2xl">
        @if($editingShipmentItem)
            <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
                <flux:heading size="lg">Edit Shipment Item</flux:heading>
                <flux:text class="mt-2">Update tracking number and status for {{ $editingShipmentItem->student->name }}</flux:text>
            </div>

            <div class="space-y-6">
                <!-- Student Info (Read-only) -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <flux:text class="text-gray-500 text-sm">Student</flux:text>
                            <flux:text class="font-medium">{{ $editingShipmentItem->student->name }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="text-gray-500 text-sm">Quantity</flux:text>
                            <flux:text class="font-medium">{{ $editingShipmentItem->quantity }}</flux:text>
                        </div>
                    </div>
                </div>

                <!-- Shipment Details Section -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <flux:icon name="truck" class="w-5 h-5 text-gray-600" />
                        <flux:heading size="md">Shipment Details</flux:heading>
                    </div>
                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Tracking Number</flux:label>
                            <flux:input
                                wire:model="editTrackingNumber"
                                placeholder="Enter tracking number"
                                class="w-full"
                            />
                            <flux:error name="editTrackingNumber" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Status</flux:label>
                            <flux:select wire:model="editStatus" class="w-full">
                                <flux:select.option value="pending">Pending</flux:select.option>
                                <flux:select.option value="shipped">Shipped</flux:select.option>
                                <flux:select.option value="delivered">Delivered</flux:select.option>
                            </flux:select>
                            <flux:error name="editStatus" />
                        </flux:field>
                    </div>
                </div>

                <!-- Shipping Address Section -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <flux:icon name="map-pin" class="w-5 h-5 text-gray-600" />
                        <flux:heading size="md">Shipping Address</flux:heading>
                    </div>
                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Address Line 1</flux:label>
                            <flux:input
                                wire:model="editAddressLine1"
                                placeholder="Street address, P.O. box"
                                class="w-full"
                            />
                            <flux:error name="editAddressLine1" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Address Line 2 (Optional)</flux:label>
                            <flux:input
                                wire:model="editAddressLine2"
                                placeholder="Apartment, suite, unit, building, floor, etc."
                                class="w-full"
                            />
                            <flux:error name="editAddressLine2" />
                        </flux:field>

                        <div class="grid grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>City</flux:label>
                                <flux:input
                                    wire:model="editCity"
                                    placeholder="City"
                                    class="w-full"
                                />
                                <flux:error name="editCity" />
                            </flux:field>

                            <flux:field>
                                <flux:label>State/Province</flux:label>
                                <flux:input
                                    wire:model="editState"
                                    placeholder="State/Province"
                                    class="w-full"
                                />
                                <flux:error name="editState" />
                            </flux:field>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>Postal Code</flux:label>
                                <flux:input
                                    wire:model="editPostcode"
                                    placeholder="Postal code"
                                    class="w-full"
                                />
                                <flux:error name="editPostcode" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Country</flux:label>
                                <flux:input
                                    wire:model="editCountry"
                                    placeholder="Country"
                                    class="w-full"
                                />
                                <flux:error name="editCountry" />
                            </flux:field>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6 pt-4 border-t border-gray-200">
                <flux:button variant="ghost" wire:click="closeEditShipmentItemModal">
                    Cancel
                </flux:button>
                <flux:button variant="primary" wire:click="updateShipmentItem" wire:loading.attr="disabled">
                    <div wire:loading.remove wire:target="updateShipmentItem">
                        <flux:icon name="check" class="w-4 h-4 mr-1" />
                        Update Item
                    </div>
                    <div wire:loading wire:target="updateShipmentItem" class="flex items-center">
                        <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Updating...
                    </div>
                </flux:button>
            </div>
        @endif
    </flux:modal>

    <!-- Create Session Modal -->
    <flux:modal name="create-session" :show="$showCreateSessionModal" wire:model="showCreateSessionModal">
        <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
            <flux:heading size="lg">Create New Session</flux:heading>
        </div>
        
        <div class="space-y-4 mb-6">
            <flux:text>Create a new session for this class. Enter the session details below.</flux:text>
            
            <div class="space-y-4">
                <div>
                    <flux:field>
                        <flux:label>Session Date</flux:label>
                        <flux:input type="date" wire:model="sessionDate" />
                        <flux:error name="sessionDate" />
                    </flux:field>
                </div>
                
                <div>
                    <flux:field>
                        <flux:label>Session Time</flux:label>
                        <flux:input type="time" wire:model="sessionTime" />
                        <flux:error name="sessionTime" />
                    </flux:field>
                </div>
                
                <div>
                    <flux:field>
                        <flux:label>Duration (minutes)</flux:label>
                        <flux:input type="number" wire:model="duration" placeholder="60" />
                        <flux:error name="duration" />
                    </flux:field>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
            <flux:button variant="ghost" wire:click="closeCreateSessionModal">Cancel</flux:button>
            <flux:button variant="primary" wire:click="createSession">Create Session</flux:button>
        </div>
    </flux:modal>

    <!-- Enroll Students Modal -->
    <flux:modal name="enroll-students" :show="$showEnrollStudentsModal" wire:model="showEnrollStudentsModal" max-width="2xl">
        <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">Enroll Students</flux:heading>
                    <flux:text class="text-sm text-gray-600 mt-1">
                        Select students to enroll in this class
                    </flux:text>
                </div>

                @if($this->remaining_capacity !== null)
                    <div class="text-right">
                        <div class="text-sm font-medium {{ $this->remaining_capacity <= 3 ? 'text-orange-600' : 'text-gray-700' }}">
                            {{ $this->remaining_capacity }} / {{ $class->max_capacity }}
                        </div>
                        <div class="text-xs text-gray-500">Spots Available</div>
                    </div>
                @endif
            </div>

            @if($this->capacity_warning)
                <div class="mt-4 p-3 bg-orange-50 border border-orange-200 rounded-lg flex items-center gap-2">
                    <flux:icon.exclamation-triangle class="h-5 w-5 text-orange-600 flex-shrink-0" />
                    <flux:text class="text-sm text-orange-800">{{ $this->capacity_warning }}</flux:text>
                </div>
            @endif
        </div>

        <div class="space-y-4 mb-6">
            <flux:field>
                <flux:label>Search Students</flux:label>
                <flux:input
                    wire:model.live.debounce.300ms="studentSearch"
                    placeholder="Type student name, email or ID..."
                    icon="magnifying-glass"
                />
            </flux:field>

            @if(count($this->available_students) > 0)
                <!-- Bulk Actions -->
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center gap-2">
                        @if(count($selectedStudents) > 0)
                            <flux:icon.check-circle class="h-5 w-5 text-green-600" />
                            <flux:text class="text-sm font-medium text-green-700">
                                {{ count($selectedStudents) }} of {{ count($this->available_students) }} selected
                            </flux:text>
                        @else
                            <flux:text class="text-sm text-gray-600">
                                {{ count($this->available_students) }} student(s) available
                            </flux:text>
                        @endif
                    </div>

                    <div class="flex gap-2">
                        <flux:button
                            variant="ghost"
                            size="sm"
                            wire:click="selectAllStudents"
                            :disabled="count($selectedStudents) === count($this->available_students)"
                        >
                            Select All
                        </flux:button>

                        @if(count($selectedStudents) > 0)
                            <flux:button
                                variant="ghost"
                                size="sm"
                                wire:click="deselectAllStudents"
                            >
                                Clear
                            </flux:button>
                        @endif
                    </div>
                </div>

                <!-- Student List -->
                <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg">
                    @foreach($this->available_students as $student)
                        <div class="flex items-center gap-3 p-3 border-b border-gray-100 last:border-b-0 hover:bg-gray-50 transition-colors {{ in_array($student->id, $selectedStudents) ? 'bg-blue-50' : '' }}">
                            <flux:checkbox
                                wire:model.live="selectedStudents"
                                value="{{ $student->id }}"
                                class="flex-shrink-0"
                            />

                            <flux:avatar size="sm" :name="$student->user?->name ?? 'N/A'" class="flex-shrink-0" />

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <flux:text class="font-medium text-gray-900">
                                        {{ $student->user?->name ?? 'N/A' }}
                                    </flux:text>
                                    <flux:badge size="sm" variant="outline">
                                        {{ $student->student_id }}
                                    </flux:badge>
                                </div>
                                <flux:text class="text-sm text-gray-600">
                                    {{ $student->user?->email ?? 'N/A' }}
                                </flux:text>
                            </div>

                            <input
                                type="text"
                                wire:model="enrollOrderIds.{{ $student->id }}"
                                placeholder="Order ID"
                                class="w-28 px-2 py-1.5 text-xs font-mono border border-gray-200 rounded bg-white dark:bg-zinc-700 dark:text-white hover:border-gray-300 dark:hover:border-zinc-500 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none transition-colors flex-shrink-0"
                            />

                            <flux:button
                                variant="outline"
                                size="sm"
                                wire:click="enrollStudent({{ $student->id }})"
                                icon="user-plus"
                                class="flex-shrink-0"
                            >
                                Enroll
                            </flux:button>
                        </div>
                    @endforeach
                </div>

                <!-- Selected Students Preview -->
                @if(count($selectedStudents) > 0)
                    <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center gap-2 mb-3">
                            <flux:icon.check-circle class="h-5 w-5 text-green-600" />
                            <flux:text class="font-medium text-green-900">
                                Ready to enroll {{ count($selectedStudents) }} student(s)
                            </flux:text>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @foreach($this->available_students->whereIn('id', $selectedStudents) as $student)
                                <div class="flex items-center gap-2 px-3 py-1.5 bg-white dark:bg-zinc-800 rounded-full border border-green-200 dark:border-green-800">
                                    <flux:avatar size="xs" :name="$student->user?->name ?? 'N/A'" />
                                    <span class="text-sm text-gray-700">{{ $student->user?->name ?? 'N/A' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @else
                <div class="p-6 text-center bg-gray-50 rounded-lg">
                    <flux:icon.users class="mx-auto h-8 w-8 text-gray-400 mb-2" />
                    @if(empty($studentSearch))
                        <flux:text class="text-gray-500">
                            No students available to enroll. All students are already in this class.
                        </flux:text>
                    @else
                        <flux:text class="text-gray-500">
                            No students found matching "{{ $studentSearch }}"
                        </flux:text>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex justify-between items-center gap-3 pt-4 border-t border-gray-200">
            <flux:button variant="ghost" wire:click="closeEnrollStudentsModal">Cancel</flux:button>

            <div class="flex gap-2">
                <flux:button
                    variant="primary"
                    wire:click="enrollSelectedStudents"
                    :disabled="count($selectedStudents) === 0"
                    icon="user-plus"
                >
                    Enroll {{ count($selectedStudents) > 0 ? count($selectedStudents) . ' Selected' : 'Selected' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Session Management Modal -->
    <flux:modal name="session-management" :show="$showSessionModal" wire:model="showSessionModal" max-width="4xl">
        @if($currentSession)
            <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
                <flux:heading size="lg">Manage Session - {{ $currentSession->formatted_date_time }}</flux:heading>
                <flux:text class="text-gray-600">Mark students as present, late, absent, or excused during this session</flux:text>
            </div>
            
            <!-- Session Timer -->
            <div 
                x-data="sessionTimer('{{ $currentSession->started_at ? $currentSession->started_at->toISOString() : now()->toISOString() }}')" 
                x-init="startTimer()"
                class="flex items-center gap-3 mb-6 p-4 bg-yellow-50 /20 rounded-lg border border-yellow-200"
            >
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 bg-yellow-500 rounded-full animate-pulse"></div>
                    <span class="font-medium text-yellow-800">Session Running:</span>
                    <span class="font-mono font-bold text-yellow-900" x-text="formattedTime"></span>
                </div>
                
                <div class="ml-auto flex items-center gap-2 text-sm text-yellow-700">
                    <span>{{ $currentSession->attendances->where('status', 'present')->count() }} present</span>
                    <span>•</span>
                    <span>{{ $currentSession->attendances->count() }} total</span>
                </div>
            </div>

            <!-- Session Bookmark -->
            <div class="mb-6 p-4 bg-amber-50 /20 rounded-lg border border-amber-200">
                <div class="flex items-center gap-2 mb-3">
                    <flux:icon.bookmark class="h-5 w-5 text-amber-600" />
                    <flux:heading size="sm" class="text-amber-800">Session Bookmark</flux:heading>
                </div>
                
                <div class="space-y-3">
                    <flux:input
                        wire:model="bookmarkText"
                        wire:change="updateSessionBookmark"
                        placeholder="e.g., Stopped at page 45, Chapter 3"
                        class="w-full"
                    />
                    
                    
                    @if($currentSession->hasBookmark())
                        <div class="text-sm text-amber-700">
                            Current bookmark: <span class="font-medium">{{ $currentSession->bookmark }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Student Attendance List -->
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @foreach($currentSession->attendances as $attendance)
                    <div class="flex items-center justify-between p-3 border border-gray-200  rounded-lg">
                        <div class="flex items-center gap-3">
                            <flux:avatar size="sm" :name="$attendance->student->fullName" />
                            <div>
                                <div class="font-medium text-gray-900">{{ $attendance->student->fullName }}</div>
                                <div class="text-sm text-gray-500">{{ $attendance->student->student_id }}</div>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            @foreach(['present', 'late', 'absent', 'excused'] as $status)
                                <flux:button
                                    wire:click="updateStudentAttendance({{ $attendance->student_id }}, '{{ $status }}')"
                                    variant="{{ $attendance->status === $status ? 'primary' : 'outline' }}"
                                    size="sm"
                                    class="{{ match($status) {
                                        'present' => 'text-green-600 border-green-600 hover:bg-green-50',
                                        'late' => 'text-yellow-600 border-yellow-600 hover:bg-yellow-50',
                                        'absent' => 'text-red-600 border-red-600 hover:bg-red-50',
                                        'excused' => 'text-blue-600 border-blue-600 hover:bg-blue-50',
                                        default => ''
                                    } }}"
                                >
                                    {{ ucfirst($status) }}
                                </flux:button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-between items-center gap-3 pt-6 border-t border-gray-200 mt-6">
                <flux:button variant="ghost" wire:click="closeSessionModal">Close</flux:button>
                
                <div class="flex gap-2">
                    <flux:button 
                        wire:click="openCompletionModal({{ $currentSession->id }})"
                        variant="primary"
                        icon="check"
                    >
                        Complete Session
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Session Completion Modal -->
    <flux:modal name="session-completion" :show="$showCompletionModal" wire:model="showCompletionModal">
        @if($completingSession)
            <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
                <flux:heading size="lg">Complete Session</flux:heading>
                <flux:text class="text-gray-600">{{ $completingSession->formatted_date_time }}</flux:text>
            </div>
            
            <div class="space-y-4 mb-6">
                <flux:text>Please add a bookmark to track your progress before completing this session.</flux:text>
                
                <div>
                    <flux:field>
                        <flux:label>Session Bookmark <span class="text-red-500">*</span></flux:label>
                        <flux:textarea 
                            wire:model="completionBookmark" 
                            placeholder="e.g., Completed Chapter 3, stopped at page 45, reviewed exercises 1-10"
                            rows="3"
                        />
                        <flux:error name="completionBookmark" />
                        <flux:description>Describe what was covered or where you stopped in this session.</flux:description>
                    </flux:field>
                </div>
                
                @if($completingSession->attendances->count() > 0)
                    <div class="p-3 bg-green-50 /20 rounded border border-green-200">
                        <div class="flex items-center gap-2 text-sm text-green-800">
                            <flux:icon.check-circle class="h-4 w-4" />
                            <span>{{ $completingSession->attendances->where('status', 'present')->count() }} of {{ $completingSession->attendances->count() }} students marked as present</span>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <flux:button variant="ghost" wire:click="closeCompletionModal">Cancel</flux:button>
                <flux:button 
                    variant="primary" 
                    wire:click="completeSessionWithBookmark"
                    icon="check"
                >
                    Complete Session
                </flux:button>
            </div>
        @endif
    </flux:modal>

    <!-- Attendance View Modal -->
    <flux:modal name="attendance-view" :show="$showAttendanceViewModal" wire:model="showAttendanceViewModal" max-width="2xl">
        @if($viewingSession)
            <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
                <flux:heading size="lg">Session Attendance</flux:heading>
                <flux:text class="text-gray-600">{{ $viewingSession->formatted_date_time }}</flux:text>
            </div>

            <!-- Session Summary -->
            <div class="mb-6 p-4 bg-gray-50  rounded-lg">
                <div class="grid grid-cols-4 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-semibold text-gray-900">{{ $viewingSession->attendances->count() }}</div>
                        <div class="text-sm text-gray-600">Total</div>
                    </div>
                    <div>
                        <div class="text-2xl font-semibold text-green-600">{{ $viewingSession->attendances->where('status', 'present')->count() }}</div>
                        <div class="text-sm text-gray-600">Present</div>
                    </div>
                    <div>
                        <div class="text-2xl font-semibold text-yellow-600">{{ $viewingSession->attendances->where('status', 'late')->count() }}</div>
                        <div class="text-sm text-gray-600">Late</div>
                    </div>
                    <div>
                        <div class="text-2xl font-semibold text-red-600">{{ $viewingSession->attendances->where('status', 'absent')->count() }}</div>
                        <div class="text-sm text-gray-600">Absent</div>
                    </div>
                </div>
            </div>

            @if($viewingSession->hasBookmark())
                <div class="mb-6 p-4 bg-blue-50 /20 rounded-lg border border-blue-200">
                    <div class="flex items-start gap-3">
                        <flux:icon.bookmark class="h-5 w-5 text-blue-600 mt-0.5 flex-shrink-0" />
                        <div>
                            <div class="text-sm font-medium text-blue-900  mb-1">Session Bookmark</div>
                            <div class="text-sm text-blue-800">{{ $viewingSession->bookmark }}</div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Student Attendance List -->
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @foreach($viewingSession->attendances->sortBy('student.user.name') as $attendance)
                    <div class="flex items-center justify-between p-3 border border-gray-200  rounded-lg">
                        <div class="flex items-center gap-3">
                            <flux:avatar size="sm" :name="$attendance->student->fullName" />
                            <div>
                                <div class="font-medium text-gray-900">{{ $attendance->student->fullName }}</div>
                                <div class="text-sm text-gray-500">{{ $attendance->student->student_id }}</div>
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <flux:badge 
                                size="sm"
                                :class="match($attendance->status) {
                                    'present' => 'text-green-700 bg-green-100  /20',
                                    'late' => 'text-yellow-700 bg-yellow-100  /20',
                                    'absent' => 'text-red-700 bg-red-100  /20',
                                    'excused' => 'text-blue-700 bg-blue-100  /20',
                                    default => 'text-gray-700 bg-gray-100  /20'
                                }"
                            >
                                {{ ucfirst($attendance->status) }}
                            </flux:badge>
                            @if($attendance->checked_in_at)
                                <div class="text-xs text-gray-500  mt-1">
                                    {{ $attendance->checked_in_at->format('g:i A') }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex justify-end">
                <flux:button 
                    wire:click="closeAttendanceViewModal"
                    variant="outline"
                >
                    Close
                </flux:button>
            </div>
        @endif
    </flux:modal>

    <!-- View Student Modal -->
    <flux:modal name="view-student" :show="$showViewStudentModal" wire:model="showViewStudentModal" max-width="2xl">
        @if($selectedClassStudent)
            <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
                <flux:heading size="lg">Student Enrollment Details</flux:heading>
                <flux:text class="text-sm text-gray-600 mt-1">
                    View detailed information about this student's enrollment
                </flux:text>
            </div>

            <!-- Student Info -->
            <div class="mb-6">
                <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg">
                    <flux:avatar size="lg" :name="$selectedClassStudent->student->user?->name ?? 'N/A'" />
                    <div class="flex-1">
                        <div class="font-semibold text-lg text-gray-900">{{ $selectedClassStudent->student->user?->name ?? 'N/A' }}</div>
                        <div class="text-sm text-gray-600">{{ $selectedClassStudent->student->user?->email ?? 'N/A' }}</div>
                        <div class="flex gap-3 mt-2">
                            <flux:badge size="sm" variant="outline">{{ $selectedClassStudent->student->student_id }}</flux:badge>
                            <flux:badge size="sm" class="badge-green">{{ ucfirst($selectedClassStudent->status) }}</flux:badge>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enrollment Information -->
            <div class="space-y-4 mb-6">
                <div>
                    <flux:text class="text-sm font-medium text-gray-700">Course</flux:text>
                    <flux:text class="text-base text-gray-900 mt-1">{{ $selectedClassStudent->class->course->title }}</flux:text>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-gray-700">Class</flux:text>
                    <flux:text class="text-base text-gray-900 mt-1">{{ $selectedClassStudent->class->title }}</flux:text>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:text class="text-sm font-medium text-gray-700">Enrolled On</flux:text>
                        <flux:text class="text-base text-gray-900 mt-1">{{ $selectedClassStudent->enrolled_at->format('M d, Y') }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="text-sm font-medium text-gray-700">Days Enrolled</flux:text>
                        <flux:text class="text-base text-gray-900 mt-1">{{ $selectedClassStudent->enrolled_at->diffInDays(now()) }} days</flux:text>
                    </div>
                </div>

                @if($selectedClassStudent->notes)
                    <div>
                        <flux:text class="text-sm font-medium text-gray-700">Notes</flux:text>
                        <div class="mt-1 p-3 bg-gray-50 rounded-lg">
                            <flux:text class="text-sm text-gray-700">{{ $selectedClassStudent->notes }}</flux:text>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Attendance Summary -->
            @php
                $studentAttendances = collect();
                foreach($class->sessions as $session) {
                    $attendance = $session->attendances->where('student_id', $selectedClassStudent->student->id)->first();
                    if($attendance) {
                        $studentAttendances->push($attendance);
                    }
                }
                $presentCount = $studentAttendances->where('status', 'present')->count();
                $lateCount = $studentAttendances->where('status', 'late')->count();
                $absentCount = $studentAttendances->where('status', 'absent')->count();
                $totalRecords = $studentAttendances->count();
                $attendanceRate = $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 1) : 0;
            @endphp

            <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg mb-6">
                <flux:heading size="sm" class="mb-4">Attendance Summary</flux:heading>

                <div class="grid grid-cols-4 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900">{{ $totalRecords }}</div>
                        <div class="text-xs text-gray-600 mt-1">Total Sessions</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">{{ $presentCount }}</div>
                        <div class="text-xs text-gray-600 mt-1">Present</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-yellow-600">{{ $lateCount }}</div>
                        <div class="text-xs text-gray-600 mt-1">Late</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-red-600">{{ $absentCount }}</div>
                        <div class="text-xs text-gray-600 mt-1">Absent</div>
                    </div>
                </div>

                @if($totalRecords > 0)
                    <div class="mt-4 pt-4 border-t border-blue-300">
                        <div class="flex items-center justify-between mb-2">
                            <flux:text class="text-sm font-medium text-gray-700">Attendance Rate</flux:text>
                            <flux:text class="text-lg font-bold {{ $attendanceRate >= 80 ? 'text-green-600' : ($attendanceRate >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ $attendanceRate }}%
                            </flux:text>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div class="h-3 rounded-full {{ $attendanceRate >= 80 ? 'bg-green-500' : ($attendanceRate >= 60 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                 style="width: {{ $attendanceRate }}%"></div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex justify-end">
                <flux:button wire:click="closeViewStudentModal" variant="outline">
                    Close
                </flux:button>
            </div>
        @endif
    </flux:modal>

    <!-- Edit Student Modal -->
    <flux:modal name="edit-student" :show="$showEditStudentModal" wire:model="showEditStudentModal" max-width="lg">
        @if($selectedClassStudent)
            <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
                <flux:heading size="lg">Edit Student Enrollment</flux:heading>
                <flux:text class="text-sm text-gray-600 mt-1">
                    Update enrollment settings for {{ $selectedClassStudent->student->user?->name ?? 'N/A' }}
                </flux:text>
            </div>

            <!-- Student Info Summary -->
            <div class="mb-6 p-3 bg-gray-50 rounded-lg">
                <div class="flex items-center gap-3">
                    <flux:avatar size="sm" :name="$selectedClassStudent->student->user?->name ?? 'N/A'" />
                    <div>
                        <div class="font-medium text-gray-900">{{ $selectedClassStudent->student->user?->name ?? 'N/A' }}</div>
                        <div class="text-sm text-gray-600">{{ $selectedClassStudent->student->student_id }}</div>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <div class="space-y-4 mb-6">
                <flux:field>
                    <flux:label>Enrollment Notes</flux:label>
                    <flux:textarea
                        wire:model="editNotes"
                        rows="4"
                        placeholder="Add notes about this student's enrollment..."
                    />
                    <flux:error name="editNotes" />
                    <flux:description>Maximum 1000 characters</flux:description>
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="closeEditStudentModal" variant="outline">
                    Cancel
                </flux:button>
                <flux:button wire:click="saveStudentEdit" variant="primary">
                    Save Changes
                </flux:button>
            </div>
        @endif
    </flux:modal>

    <!-- Unenroll Confirmation Modal -->
    <flux:modal name="unenroll-confirm" :show="$showUnenrollConfirmModal" wire:model="showUnenrollConfirmModal" max-width="md">
        @if($selectedClassStudent)
            <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
                <div class="flex items-center gap-3 mb-2">
                    <flux:icon.exclamation-triangle class="h-8 w-8 text-red-600" />
                    <flux:heading size="lg" class="text-red-600">Confirm Unenrollment</flux:heading>
                </div>
                <flux:text class="text-sm text-gray-600">
                    This action will unenroll the student from this class
                </flux:text>
            </div>

            <!-- Student Info -->
            <div class="mb-6">
                <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center gap-3 mb-3">
                        <flux:avatar size="sm" :name="$selectedClassStudent->student->user->name" />
                        <div>
                            <div class="font-medium text-gray-900">{{ $selectedClassStudent->student->user->name }}</div>
                            <div class="text-sm text-gray-600">{{ $selectedClassStudent->student->student_id }}</div>
                        </div>
                    </div>

                    <div class="text-sm text-gray-700">
                        <p class="mb-2"><strong>Class:</strong> {{ $selectedClassStudent->class->title }}</p>
                        <p class="mb-2"><strong>Enrolled:</strong> {{ $selectedClassStudent->enrolled_at->format('M d, Y') }}</p>
                        <p><strong>Days Enrolled:</strong> {{ $selectedClassStudent->enrolled_at->diffInDays(now()) }} days</p>
                    </div>
                </div>
            </div>

            <!-- Warning Message -->
            <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div class="flex gap-2">
                    <flux:icon.exclamation-triangle class="h-5 w-5 text-yellow-600 flex-shrink-0 mt-0.5" />
                    <div>
                        <flux:text class="text-sm font-medium text-yellow-900">Warning</flux:text>
                        <flux:text class="text-sm text-yellow-800 mt-1">
                            The student's enrollment status will be changed to "unenrolled" and they will no longer appear in the active students list. Their attendance records will be preserved.
                        </flux:text>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="closeUnenrollConfirmModal" variant="outline">
                    Cancel
                </flux:button>
                <flux:button wire:click="unenrollStudent" variant="danger">
                    <div class="flex items-center justify-center">
                        <flux:icon name="trash" class="w-4 h-4 mr-1" />
                        Unenroll Student
                    </div>
                </flux:button>
            </div>
        @endif
    </flux:modal>

    <!-- Import Students Modal -->
    <flux:modal name="import-students" :show="$showImportStudentModal" wire:model="showImportStudentModal">
        <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
            <flux:heading size="lg">Import Students to Class</flux:heading>
            <flux:text class="mt-2">Upload a CSV file to import students into this class</flux:text>
        </div>

        <div class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start space-x-2">
                        <flux:icon.information-circle class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
                        <div>
                            <flux:text class="font-medium text-blue-800">CSV Format Requirements</flux:text>
                            <flux:text class="text-sm text-blue-700 mt-1">
                                Your CSV must include a <strong>phone</strong> column (required). Optional columns: <strong>name</strong>, <strong>email</strong>, <strong>order_id</strong>
                            </flux:text>
                        </div>
                    </div>
                    <flux:button variant="outline" size="sm" wire:click="downloadImportStudentSample" class="flex-shrink-0">
                        <div class="flex items-center justify-center">
                            <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-1" />
                            Sample
                        </div>
                    </flux:button>
                </div>
            </div>

            <flux:field>
                <flux:label>Select CSV File</flux:label>
                <flux:input type="file" wire:model.live="importStudentFile" accept=".csv,.txt" />
                <flux:error name="importStudentFile" />
                <flux:text class="mt-1 text-xs">
                    Accepted formats: CSV (.csv, .txt). Maximum file size: 10MB
                </flux:text>
            </flux:field>

            <div wire:loading wire:target="importStudentFile" class="text-sm text-gray-600 mt-2">
                <div class="flex items-center">
                    <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Uploading file...
                </div>
            </div>

            @if($importStudentFile)
                <div class="text-sm text-green-600 mt-2 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    File ready: {{ $importStudentFile->getClientOriginalName() }}
                </div>
            @endif

            <div class="border-t border-gray-200 pt-4 space-y-4">
                <flux:heading size="sm">Import Options</flux:heading>

                <flux:field>
                    <flux:checkbox wire:model.live="autoEnrollImported" label="Auto-enroll imported students" description="Automatically add matched/created students to this class" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model.live="createMissingStudents" label="Create missing students" description="Create new student accounts for phone numbers not found in the system" />
                </flux:field>

                @if($createMissingStudents)
                    <flux:field>
                        <flux:label>Default Password for New Students</flux:label>
                        <flux:input type="text" wire:model="importStudentPassword" placeholder="Enter default password" />
                        <flux:error name="importStudentPassword" />
                        <flux:text class="text-xs text-gray-500 mt-1">
                            This password will be used for all newly created student accounts
                        </flux:text>
                    </flux:field>
                @endif
            </div>
        </div>

        <div class="flex justify-end gap-2 mt-6">
            <flux:button variant="ghost" wire:click="closeImportStudentModal">Cancel</flux:button>
            <flux:button variant="primary" wire:click="importStudentsToClass" wire:loading.attr="disabled" :disabled="!$importStudentFile">
                <div wire:loading.remove wire:target="importStudentsToClass">
                    <flux:icon.arrow-up-tray class="h-4 w-4 mr-1" />
                    Import Students
                </div>
                <div wire:loading wire:target="importStudentsToClass" class="flex items-center">
                    <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Importing...
                </div>
            </flux:button>
        </div>
    </flux:modal>

    <!-- Import Students Result Modal -->
    <flux:modal name="import-students-result" :show="$showImportStudentResultModal" wire:model="showImportStudentResultModal" class="max-w-2xl">
        <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
            <flux:heading size="lg">Import Results</flux:heading>
            <flux:text class="mt-2">Summary of the student import process</flux:text>
        </div>

        <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-2">
            @if(!empty($importStudentResult))
                <!-- Summary Stats -->
                <div class="grid grid-cols-4 gap-4">
                    <div class="bg-green-50 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-green-600">{{ count($importStudentResult['enrolled'] ?? []) }}</div>
                        <div class="text-xs text-green-700">Enrolled</div>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ count($importStudentResult['created'] ?? []) }}</div>
                        <div class="text-xs text-blue-700">Created</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-gray-600">{{ count($importStudentResult['already_enrolled'] ?? []) }}</div>
                        <div class="text-xs text-gray-700">Already Enrolled</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-yellow-600">{{ count($importStudentResult['skipped'] ?? []) }}</div>
                        <div class="text-xs text-yellow-700">Skipped</div>
                    </div>
                </div>

                <!-- Enrolled Students -->
                @if(!empty($importStudentResult['enrolled']))
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <flux:heading size="sm" class="text-green-800 mb-2">Students Enrolled ({{ count($importStudentResult['enrolled']) }})</flux:heading>
                        <div class="space-y-1">
                            @foreach($importStudentResult['enrolled'] as $student)
                                <div class="text-sm text-green-700 flex items-center">
                                    <flux:icon.check-circle class="w-4 h-4 mr-2" />
                                    {{ $student['name'] }} ({{ $student['phone'] }})@if(!empty($student['order_id'])) - Order: {{ $student['order_id'] }}@endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Created Students -->
                @if(!empty($importStudentResult['created']))
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <flux:heading size="sm" class="text-blue-800 mb-2">New Students Created ({{ count($importStudentResult['created']) }})</flux:heading>
                        <div class="space-y-1">
                            @foreach($importStudentResult['created'] as $student)
                                <div class="text-sm text-blue-700 flex items-center">
                                    <flux:icon.user-plus class="w-4 h-4 mr-2" />
                                    {{ $student['name'] }} ({{ $student['phone'] }}) - ID: {{ $student['student_id'] }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Already Enrolled -->
                @if(!empty($importStudentResult['already_enrolled']))
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <flux:heading size="sm" class="text-gray-800 mb-2">Already Enrolled ({{ count($importStudentResult['already_enrolled']) }})</flux:heading>
                        <div class="space-y-1">
                            @foreach($importStudentResult['already_enrolled'] as $student)
                                <div class="text-sm text-gray-600 flex items-center">
                                    <flux:icon.minus-circle class="w-4 h-4 mr-2" />
                                    {{ $student['name'] }} ({{ $student['phone'] }})
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Skipped -->
                @if(!empty($importStudentResult['skipped']))
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <flux:heading size="sm" class="text-yellow-800 mb-2">Skipped ({{ count($importStudentResult['skipped']) }})</flux:heading>
                        <div class="space-y-1">
                            @foreach($importStudentResult['skipped'] as $student)
                                <div class="text-sm text-yellow-700 flex items-center">
                                    <flux:icon.exclamation-triangle class="w-4 h-4 mr-2" />
                                    {{ $student['name'] }} ({{ $student['phone'] }}) - {{ $student['reason'] }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Errors -->
                @if(!empty($importStudentResult['errors']))
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <flux:heading size="sm" class="text-red-800 mb-2">Errors ({{ count($importStudentResult['errors']) }})</flux:heading>
                        <div class="space-y-1">
                            @foreach($importStudentResult['errors'] as $error)
                                <div class="text-sm text-red-700 flex items-center">
                                    <flux:icon.x-circle class="w-4 h-4 mr-2" />
                                    {{ $error }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>

        <div class="flex justify-end gap-2 mt-6">
            <flux:button variant="primary" wire:click="closeImportStudentResultModal">Close</flux:button>
        </div>
    </flux:modal>

    <!-- Student Import Progress Modal -->
    <flux:modal name="student-import-progress" :show="$importStudentProcessing" wire:model="importStudentProcessing" class="md:w-[600px]">
        <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
            <flux:heading size="lg">Importing Students</flux:heading>
            <flux:text class="mt-2">Please wait while students are being imported...</flux:text>
        </div>

        <div class="space-y-4">
            @if($studentImportProgress)
                <!-- Progress Bar -->
                <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                    <div class="bg-blue-600 h-4 rounded-full transition-all duration-300 flex items-center justify-center"
                         style="width: {{ $studentImportProgress['progress_percentage'] ?? 0 }}%">
                        @if(($studentImportProgress['progress_percentage'] ?? 0) > 15)
                            <span class="text-xs text-white font-medium">{{ $studentImportProgress['progress_percentage'] ?? 0 }}%</span>
                        @endif
                    </div>
                </div>

                <div class="text-center text-sm text-gray-600">
                    Processing {{ $studentImportProgress['processed_rows'] ?? 0 }} of {{ $studentImportProgress['total_rows'] ?? 0 }} rows
                </div>

                <!-- Live Stats -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
                    <div class="bg-blue-50 rounded-lg p-3 text-center">
                        <div class="text-xl font-bold text-blue-600">{{ $studentImportProgress['matched_count'] ?? 0 }}</div>
                        <div class="text-xs text-blue-700">Matched</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-3 text-center">
                        <div class="text-xl font-bold text-green-600">{{ $studentImportProgress['created_count'] ?? 0 }}</div>
                        <div class="text-xs text-green-700">Created</div>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-3 text-center">
                        <div class="text-xl font-bold text-purple-600">{{ $studentImportProgress['enrolled_count'] ?? 0 }}</div>
                        <div class="text-xs text-purple-700">Enrolled</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-3 text-center">
                        <div class="text-xl font-bold text-yellow-600">{{ $studentImportProgress['skipped_count'] ?? 0 }}</div>
                        <div class="text-xs text-yellow-700">Skipped</div>
                    </div>
                </div>

                @if(($studentImportProgress['error_count'] ?? 0) > 0)
                    <div class="bg-red-50 rounded-lg p-3 text-center">
                        <div class="text-xl font-bold text-red-600">{{ $studentImportProgress['error_count'] }}</div>
                        <div class="text-xs text-red-700">Errors</div>
                    </div>
                @endif

                <!-- Animated Loading Indicator -->
                <div class="flex items-center justify-center gap-2 text-gray-500">
                    <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-sm">Processing in background...</span>
                </div>
            @else
                <!-- Initial Loading State -->
                <div class="flex flex-col items-center justify-center py-8">
                    <svg class="animate-spin h-10 w-10 text-blue-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-gray-600">Starting import process...</p>
                </div>
            @endif
        </div>

        <div class="flex justify-end gap-2 mt-6">
            <flux:button variant="ghost" wire:click="cancelStudentImport">
                Cancel
            </flux:button>
        </div>
    </flux:modal>

    <!-- Create Student Modal -->
    <flux:modal name="create-student" :show="$showCreateStudentModal" wire:model="showCreateStudentModal">
        <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
            <flux:heading size="lg">Create New Student</flux:heading>
            <flux:text class="mt-2">Create a new student and optionally enroll them in this class</flux:text>
        </div>

        {{-- Error Summary --}}
        @if ($errors->any())
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-start gap-3">
                    <flux:icon name="exclamation-circle" class="w-5 h-5 text-red-500 mt-0.5 shrink-0" />
                    <div class="flex-1">
                        <p class="text-sm font-medium text-red-800">Please fix the following errors:</p>
                        <ul class="mt-2 text-sm text-red-700 list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <div class="space-y-6 max-h-[60vh] overflow-y-auto pr-2">
            <!-- Account Information -->
            <div class="space-y-4">
                <flux:heading size="sm">Account Information</flux:heading>

                <flux:field>
                    <flux:label>Full Name <span class="text-red-500">*</span></flux:label>
                    <flux:input
                        wire:model="newStudentName"
                        placeholder="Enter student's full name"
                        :invalid="$errors->has('newStudentName')"
                    />
                    @error('newStudentName')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Email Address (Optional)</flux:label>
                    <flux:input
                        type="email"
                        wire:model="newStudentEmail"
                        placeholder="student@example.com"
                        :invalid="$errors->has('newStudentEmail')"
                    />
                    @error('newStudentEmail')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>IC Number (Optional)</flux:label>
                    <flux:input
                        wire:model="newStudentIcNumber"
                        placeholder="e.g., 961208035935"
                        :invalid="$errors->has('newStudentIcNumber')"
                    />
                    <flux:description>Must be exactly 12 digits without dashes</flux:description>
                    @error('newStudentIcNumber')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Password <span class="text-red-500">*</span></flux:label>
                        <flux:input
                            type="password"
                            wire:model="newStudentPassword"
                            placeholder="Enter password"
                            :invalid="$errors->has('newStudentPassword')"
                        />
                        <flux:description>Minimum 8 characters</flux:description>
                        @error('newStudentPassword')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>
                    <flux:field>
                        <flux:label>Confirm Password <span class="text-red-500">*</span></flux:label>
                        <flux:input
                            type="password"
                            wire:model="newStudentPasswordConfirmation"
                            placeholder="Confirm password"
                            :invalid="$errors->has('newStudentPasswordConfirmation')"
                        />
                        @error('newStudentPasswordConfirmation')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>
                </div>
            </div>

            <!-- Phone Number -->
            <flux:field>
                <flux:label>Phone Number <span class="text-red-500">*</span></flux:label>
                <div class="flex gap-2">
                    <div class="w-28 shrink-0">
                        <flux:select wire:model="newStudentCountryCode" :invalid="$errors->has('newStudentCountryCode')">
                            <flux:select.option value="+60">+60</flux:select.option>
                            <flux:select.option value="+65">+65</flux:select.option>
                            <flux:select.option value="+62">+62</flux:select.option>
                            <flux:select.option value="+66">+66</flux:select.option>
                            <flux:select.option value="+1">+1</flux:select.option>
                            <flux:select.option value="+44">+44</flux:select.option>
                        </flux:select>
                    </div>
                    <div class="flex-1">
                        <flux:input
                            wire:model="newStudentPhone"
                            placeholder="123456789"
                            :invalid="$errors->has('newStudentPhone')"
                        />
                    </div>
                </div>
                <flux:description>Numbers only, without country code prefix</flux:description>
                @error('newStudentPhone')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
                @error('newStudentCountryCode')
                    <flux:error>{{ $message }}</flux:error>
                @enderror
            </flux:field>

            <!-- Enrollment Option -->
            <div class="bg-gray-50 rounded-lg p-4 space-y-4">
                <flux:checkbox
                    wire:model.live="enrollAfterCreate"
                    label="Enroll in this class after creation"
                    description="The student will be automatically added to {{ $class->title }}"
                />

                @if($enrollAfterCreate)
                    <flux:field>
                        <flux:label>Order ID (Optional)</flux:label>
                        <flux:input
                            wire:model="newStudentOrderId"
                            placeholder="e.g., ORD-2024-001"
                        />
                        <flux:description>
                            Associate this enrollment with a specific order
                        </flux:description>
                    </flux:field>

                    {{-- Capacity Warning --}}
                    @if($class->max_capacity && $class->activeStudents()->count() >= $class->max_capacity)
                        <div class="flex items-center gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-amber-500 shrink-0" />
                            <p class="text-sm text-amber-700">
                                <strong>Warning:</strong> This class is at maximum capacity ({{ $class->max_capacity }} students).
                                The student will be created but not enrolled.
                            </p>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3 pt-4 mt-4 border-t border-gray-200">
            <flux:button variant="outline" wire:click="closeCreateStudentModal">
                Cancel
            </flux:button>
            <flux:button variant="primary" wire:click="createStudent">
                <div class="flex items-center justify-center">
                    <flux:icon name="plus" class="w-4 h-4 mr-1" />
                    Create Student
                </div>
            </flux:button>
        </div>
    </flux:modal>
</div>

<script>
function sessionTimer(startedAtISO) {
    return {
        startedAt: new Date(startedAtISO),
        formattedTime: '',
        interval: null,
        
        init() {
            this.updateFormattedTime();
        },
        
        startTimer() {
            this.updateFormattedTime();
            this.interval = setInterval(() => {
                this.updateFormattedTime();
            }, 1000);
        },
        
        stopTimer() {
            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
            }
        },
        
        updateFormattedTime() {
            const now = new Date();
            const diffInSeconds = Math.floor((now - this.startedAt) / 1000);
            
            // Ensure we don't show negative time
            const seconds = Math.max(0, diffInSeconds);
            
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            
            if (hours > 0) {
                this.formattedTime = `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
            } else {
                this.formattedTime = `${minutes}:${String(secs).padStart(2, '0')}`;
            }
        }
    }
}

// Listen for tab changes and update URL
document.addEventListener('livewire:init', () => {
    Livewire.on('update-url', (data) => {
        const url = new URL(window.location);
        url.searchParams.set('tab', data[0].tab);
        window.history.pushState({}, '', url);
    });

    // Listen for shipment expansion state changes and update URL
    Livewire.on('update-shipment-url', (event) => {
        const url = new URL(window.location);
        const expandedShipment = event.expandedShipment || (event[0] && event[0].expandedShipment);

        if (expandedShipment) {
            url.searchParams.set('expandedShipment', expandedShipment);
        } else {
            url.searchParams.delete('expandedShipment');
        }
        window.history.pushState({}, '', url);
    });

    // Import progress polling
    let importPollingInterval = null;

    Livewire.on('start-import-polling', () => {
        if (importPollingInterval) {
            clearInterval(importPollingInterval);
        }

        // Poll every 2 seconds
        importPollingInterval = setInterval(() => {
            Livewire.dispatch('checkImportProgress');
        }, 2000);
    });

    Livewire.on('stop-import-polling', () => {
        if (importPollingInterval) {
            clearInterval(importPollingInterval);
            importPollingInterval = null;
        }
    });

    // Student import progress polling
    let studentImportPollingInterval = null;

    Livewire.on('start-student-import-polling', () => {
        if (studentImportPollingInterval) {
            clearInterval(studentImportPollingInterval);
        }

        // Poll every 2 seconds
        studentImportPollingInterval = setInterval(() => {
            Livewire.dispatch('checkStudentImportProgress');
        }, 2000);
    });

    Livewire.on('stop-student-import-polling', () => {
        if (studentImportPollingInterval) {
            clearInterval(studentImportPollingInterval);
            studentImportPollingInterval = null;
        }
    });
});
</script>