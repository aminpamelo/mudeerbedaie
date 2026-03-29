<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Models\MeetingAttendee;
use App\Models\MeetingDecision;
use App\Models\MeetingSeries;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class HrMeetingSeeder extends Seeder
{
    public function run(): void
    {
        $adminUser = User::where('email', 'admin@example.com')->first();
        $employees = Employee::all();

        if ($employees->count() < 5) {
            $this->command->warn('Need at least 5 employees. Skipping MOM seeder.');

            return;
        }

        // Pick employees for various roles
        $organizer1 = $employees[0];
        $organizer2 = $employees[1];
        $noteTaker1 = $employees[2];
        $noteTaker2 = $employees[3];
        $attendees = $employees->slice(4)->take(10)->values();

        // ─── Meeting Series ───
        $weeklySeries = MeetingSeries::create([
            'name' => 'Mesyuarat Mingguan Jabatan',
            'description' => 'Mesyuarat mingguan untuk kemaskini status projek dan isu semasa.',
            'created_by' => $adminUser->id,
        ]);

        $monthlySeries = MeetingSeries::create([
            'name' => 'Mesyuarat Bulanan Pengurusan',
            'description' => 'Mesyuarat bulanan pengurusan syarikat untuk semakan KPI dan bajet.',
            'created_by' => $adminUser->id,
        ]);

        $projectSeries = MeetingSeries::create([
            'name' => 'Mesyuarat Projek HR System',
            'description' => 'Mesyuarat pembangunan sistem HR baharu.',
            'created_by' => $adminUser->id,
        ]);

        // ─── Meetings ───
        $meetingsData = [
            // Past completed meetings
            [
                'title' => 'Mesyuarat Mingguan #1 - Kickoff Q2',
                'description' => 'Mesyuarat pertama Q2 untuk merancang aktiviti suku tahun.',
                'location' => 'Bilik Mesyuarat Utama',
                'meeting_date' => Carbon::now()->subDays(21)->toDateString(),
                'start_time' => '09:00',
                'end_time' => '10:30',
                'status' => 'completed',
                'series' => $weeklySeries,
                'organizer' => $organizer1,
                'note_taker' => $noteTaker1,
            ],
            [
                'title' => 'Mesyuarat Projek HR - Sprint Review',
                'description' => 'Semakan sprint untuk modul kehadiran dan cuti.',
                'location' => 'Bilik Mesyuarat 2',
                'meeting_date' => Carbon::now()->subDays(14)->toDateString(),
                'start_time' => '14:00',
                'end_time' => '15:30',
                'status' => 'completed',
                'series' => $projectSeries,
                'organizer' => $organizer2,
                'note_taker' => $noteTaker2,
            ],
            [
                'title' => 'Mesyuarat Bulanan Mac 2026',
                'description' => 'Semakan prestasi bulanan dan perancangan bajet.',
                'location' => 'Bilik Mesyuarat Eksekutif',
                'meeting_date' => Carbon::now()->subDays(7)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '12:00',
                'status' => 'completed',
                'series' => $monthlySeries,
                'organizer' => $organizer1,
                'note_taker' => $noteTaker2,
            ],
            [
                'title' => 'Perbincangan Polisi Kerja Dari Rumah',
                'description' => 'Perbincangan mengenai polisi WFH baharu untuk syarikat.',
                'location' => 'Bilik Mesyuarat 3',
                'meeting_date' => Carbon::now()->subDays(5)->toDateString(),
                'start_time' => '11:00',
                'end_time' => '12:00',
                'status' => 'completed',
                'series' => null,
                'organizer' => $organizer2,
                'note_taker' => $noteTaker1,
            ],
            // Upcoming / scheduled meetings
            [
                'title' => 'Mesyuarat Mingguan #5 - Status Update',
                'description' => 'Kemaskini status projek dan penugasan tugas baharu.',
                'location' => 'Bilik Mesyuarat Utama',
                'meeting_date' => Carbon::now()->addDays(2)->toDateString(),
                'start_time' => '09:00',
                'end_time' => '10:30',
                'status' => 'scheduled',
                'series' => $weeklySeries,
                'organizer' => $organizer1,
                'note_taker' => $noteTaker1,
            ],
            [
                'title' => 'Mesyuarat Projek HR - Planning Sprint 6',
                'description' => 'Perancangan sprint untuk modul MOM dan Task Management.',
                'location' => 'Bilik Mesyuarat 2',
                'meeting_date' => Carbon::now()->addDays(4)->toDateString(),
                'start_time' => '14:00',
                'end_time' => '16:00',
                'status' => 'scheduled',
                'series' => $projectSeries,
                'organizer' => $organizer2,
                'note_taker' => $noteTaker2,
            ],
            [
                'title' => 'Mesyuarat Bulanan April 2026',
                'description' => 'Semakan bulanan prestasi dan perancangan Q2.',
                'location' => 'Bilik Mesyuarat Eksekutif',
                'meeting_date' => Carbon::now()->addDays(10)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '12:00',
                'status' => 'scheduled',
                'series' => $monthlySeries,
                'organizer' => $organizer1,
                'note_taker' => $noteTaker2,
            ],
            // Draft meeting
            [
                'title' => 'Bengkel Latihan Sistem HR Baharu',
                'description' => 'Sesi latihan untuk semua kakitangan mengenai sistem HR baharu.',
                'location' => 'Dewan Serbaguna',
                'meeting_date' => Carbon::now()->addDays(14)->toDateString(),
                'start_time' => '09:00',
                'end_time' => '17:00',
                'status' => 'draft',
                'series' => null,
                'organizer' => $organizer1,
                'note_taker' => $noteTaker1,
            ],
            // In progress meeting (today)
            [
                'title' => 'Mesyuarat Kecemasan - Isu Server',
                'description' => 'Perbincangan segera mengenai isu downtime server produksi.',
                'location' => 'Bilik Mesyuarat IT',
                'meeting_date' => Carbon::now()->toDateString(),
                'start_time' => '15:00',
                'end_time' => '16:00',
                'status' => 'in_progress',
                'series' => null,
                'organizer' => $organizer2,
                'note_taker' => $noteTaker1,
            ],
            // Cancelled meeting
            [
                'title' => 'Mesyuarat Vendor - Ditangguhkan',
                'description' => 'Mesyuarat dengan vendor ditangguhkan kerana ketiadaan pihak vendor.',
                'location' => 'Bilik Mesyuarat 1',
                'meeting_date' => Carbon::now()->subDays(3)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '11:00',
                'status' => 'cancelled',
                'series' => null,
                'organizer' => $organizer1,
                'note_taker' => null,
            ],
        ];

        $meetings = [];

        foreach ($meetingsData as $data) {
            $meeting = Meeting::create([
                'meeting_series_id' => $data['series']?->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'location' => $data['location'],
                'meeting_date' => $data['meeting_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'status' => $data['status'],
                'organizer_id' => $data['organizer']->id,
                'note_taker_id' => $data['note_taker']?->id,
                'created_by' => $adminUser->id,
            ]);

            // Add organizer as attendee
            MeetingAttendee::create([
                'meeting_id' => $meeting->id,
                'employee_id' => $data['organizer']->id,
                'role' => 'organizer',
                'attendance_status' => $data['status'] === 'completed' ? 'attended' : 'invited',
            ]);

            // Add note taker as attendee
            if ($data['note_taker']) {
                MeetingAttendee::create([
                    'meeting_id' => $meeting->id,
                    'employee_id' => $data['note_taker']->id,
                    'role' => 'note_taker',
                    'attendance_status' => $data['status'] === 'completed' ? 'attended' : 'invited',
                ]);
            }

            // Add 3-6 random attendees per meeting
            $meetingAttendees = $attendees->random(min(rand(3, 6), $attendees->count()));
            foreach ($meetingAttendees as $att) {
                MeetingAttendee::create([
                    'meeting_id' => $meeting->id,
                    'employee_id' => $att->id,
                    'role' => 'attendee',
                    'attendance_status' => match ($data['status']) {
                        'completed' => collect(['attended', 'attended', 'attended', 'absent', 'excused'])->random(),
                        'cancelled' => 'invited',
                        default => 'invited',
                    },
                ]);
            }

            $meetings[] = $meeting;
        }

        // ─── Agenda Items ───
        $agendaData = [
            // Meeting 0 - Kickoff Q2
            [0, [
                'Pembentangan sasaran Q2',
                'Semakan bajet jabatan',
                'Penugasan projek baharu',
                'Lain-lain hal',
            ]],
            // Meeting 1 - Sprint Review
            [1, [
                'Demo modul kehadiran',
                'Demo modul cuti',
                'Semakan bug & isu',
                'Perancangan sprint seterusnya',
            ]],
            // Meeting 2 - Monthly
            [2, [
                'Laporan prestasi bulanan',
                'Semakan KPI jabatan',
                'Kemaskini bajet',
                'Isu sumber manusia',
                'Lain-lain hal',
            ]],
            // Meeting 3 - WFH Policy
            [3, [
                'Polisi WFH semasa',
                'Cadangan polisi baharu',
                'Kesan terhadap produktiviti',
                'Keputusan dan tindakan',
            ]],
            // Meeting 4 - Weekly #5
            [4, [
                'Kemaskini status projek',
                'Isu dan halangan',
                'Penugasan tugas minggu ini',
            ]],
            // Meeting 8 - Server Emergency
            [8, [
                'Laporan insiden',
                'Punca masalah',
                'Tindakan pemulihan',
                'Langkah pencegahan',
            ]],
        ];

        $agendaItems = [];
        foreach ($agendaData as [$meetingIdx, $items]) {
            foreach ($items as $order => $title) {
                $agendaItems["{$meetingIdx}_{$order}"] = MeetingAgendaItem::create([
                    'meeting_id' => $meetings[$meetingIdx]->id,
                    'title' => $title,
                    'description' => null,
                    'sort_order' => $order + 1,
                ]);
            }
        }

        // ─── Decisions (for completed meetings) ───
        $decisionsData = [
            [0, 'Sasaran Q2 diluluskan', 'Semua sasaran Q2 telah dipersetujui oleh pengurusan.', $organizer1->id, 0],
            [0, 'Bajet jabatan IT dinaikkan 15%', 'Kenaikan bajet untuk pembelian peralatan baru.', $organizer1->id, 1],
            [1, 'Modul kehadiran sedia untuk UAT', 'Modul kehadiran akan diuji oleh pengguna minggu depan.', $organizer2->id, 0],
            [1, 'Bug kritikal perlu diselesaikan sebelum deployment', 'Tiga bug kritikal dikenal pasti perlu diperbaiki.', $organizer2->id, 2],
            [2, 'Bonus prestasi Q1 diluluskan', 'Bonus prestasi akan dibayar pada bulan April.', $organizer1->id, 0],
            [2, 'Pengambilan 3 kakitangan baru', 'Jawatan baru dibuka untuk jabatan IT dan pemasaran.', $organizer1->id, 3],
            [3, 'Polisi WFH 2 hari seminggu diluluskan', 'Kakitangan boleh WFH pada hari Rabu dan Jumaat.', $organizer2->id, 3],
        ];

        foreach ($decisionsData as [$meetingIdx, $title, $description, $decidedBy, $agendaOrder]) {
            $agendaItemId = $agendaItems["{$meetingIdx}_{$agendaOrder}"]->id ?? null;
            MeetingDecision::create([
                'meeting_id' => $meetings[$meetingIdx]->id,
                'agenda_item_id' => $agendaItemId,
                'title' => $title,
                'description' => $description,
                'decided_by' => $decidedBy,
                'decided_at' => Carbon::parse($meetings[$meetingIdx]->meeting_date)->setHour(10),
            ]);
        }

        // ─── Tasks (polymorphic to meetings) ───
        $tasksData = [
            // From Meeting 0 - Kickoff
            [0, 'Sediakan laporan sasaran Q2 terperinci', 'Senaraikan semua sasaran Q2 mengikut jabatan.', 'high', 'completed', 7, $organizer1->id],
            [0, 'Kemaskini dokumen bajet jabatan', 'Kemaskini spreadsheet bajet dengan angka terkini.', 'medium', 'completed', 5, $organizer1->id],
            // From Meeting 1 - Sprint Review
            [1, 'Perbaiki bug kehadiran #142', 'Bug pada pengiraan jam kerja lebih masa.', 'urgent', 'completed', 3, $organizer2->id],
            [1, 'Perbaiki bug cuti #155', 'Baki cuti tidak dikemaskini selepas kelulusan.', 'urgent', 'in_progress', 5, $organizer2->id],
            [1, 'Sediakan skrip UAT modul kehadiran', 'Tulis skrip ujian untuk pengguna.', 'high', 'in_progress', 7, $organizer2->id],
            // From Meeting 2 - Monthly
            [2, 'Sediakan tawaran kerja untuk 3 jawatan', 'Buka iklan jawatan di portal pekerjaan.', 'high', 'pending', 14, $organizer1->id],
            [2, 'Proses bonus prestasi Q1', 'Kira dan proses pembayaran bonus.', 'urgent', 'in_progress', 10, $organizer1->id],
            // From Meeting 3 - WFH Policy
            [3, 'Draf dokumen polisi WFH', 'Sediakan dokumen polisi WFH rasmi.', 'high', 'pending', 7, $organizer2->id],
            [3, 'Setup sistem tracking WFH', 'Konfigurasi sistem untuk mengesan kehadiran WFH.', 'medium', 'pending', 14, $organizer2->id],
            // From Meeting 8 - Server Emergency
            [8, 'Pasang monitoring server baharu', 'Install Grafana dan Prometheus untuk pemantauan.', 'urgent', 'in_progress', 3, $organizer2->id],
            [8, 'Backup strategy review', 'Semak dan kemaskini strategi backup sedia ada.', 'high', 'pending', 7, $organizer2->id],
            [8, 'Dokumentasi insiden', 'Sediakan laporan post-mortem untuk insiden server.', 'medium', 'pending', 5, $organizer2->id],
        ];

        $createdTasks = [];
        foreach ($tasksData as [$meetingIdx, $title, $description, $priority, $status, $daysToDeadline, $assignedBy]) {
            $assignee = $attendees->random();
            $deadline = Carbon::parse($meetings[$meetingIdx]->meeting_date)->addDays($daysToDeadline);

            $task = Task::create([
                'taskable_type' => Meeting::class,
                'taskable_id' => $meetings[$meetingIdx]->id,
                'parent_id' => null,
                'title' => $title,
                'description' => $description,
                'assigned_to' => $assignee->id,
                'assigned_by' => $assignedBy,
                'priority' => $priority,
                'status' => $status,
                'deadline' => $deadline,
                'completed_at' => $status === 'completed' ? $deadline->subDay() : null,
            ]);

            $createdTasks[] = $task;
        }

        // ─── Subtasks (for some tasks) ───
        $subtasksData = [
            // Subtasks for "Sediakan laporan sasaran Q2"
            [0, 'Kumpul data dari jabatan IT', 'low', 'completed'],
            [0, 'Kumpul data dari jabatan Pemasaran', 'low', 'completed'],
            [0, 'Compile dan format laporan', 'medium', 'completed'],
            // Subtasks for "Perbaiki bug kehadiran #142"
            [2, 'Reproduce bug', 'high', 'completed'],
            [2, 'Write failing test', 'high', 'completed'],
            [2, 'Fix calculation logic', 'high', 'completed'],
            // Subtasks for "Sediakan skrip UAT"
            [4, 'Tulis test case clock-in/clock-out', 'medium', 'in_progress'],
            [4, 'Tulis test case laporan kehadiran', 'medium', 'pending'],
            [4, 'Tulis test case cuti tahunan', 'medium', 'pending'],
            // Subtasks for "Pasang monitoring server"
            [9, 'Install Grafana', 'urgent', 'completed'],
            [9, 'Install Prometheus', 'urgent', 'in_progress'],
            [9, 'Configure alert rules', 'high', 'pending'],
        ];

        foreach ($subtasksData as [$parentIdx, $title, $priority, $status]) {
            $parentTask = $createdTasks[$parentIdx];
            Task::create([
                'taskable_type' => Meeting::class,
                'taskable_id' => $parentTask->taskable_id,
                'parent_id' => $parentTask->id,
                'title' => $title,
                'description' => null,
                'assigned_to' => $parentTask->assigned_to,
                'assigned_by' => $parentTask->assigned_by,
                'priority' => $priority,
                'status' => $status,
                'deadline' => $parentTask->deadline,
                'completed_at' => $status === 'completed' ? Carbon::now()->subDays(rand(1, 5)) : null,
            ]);
        }

        // ─── Task Comments ───
        $commentsData = [
            [2, 'Bug ini berlaku apabila overtime melebihi 8 jam.'],
            [2, 'Sudah diperbaiki. Sila semak PR #243.'],
            [3, 'Masih dalam proses. ETA esok.'],
            [6, 'Perlu kelulusan CFO sebelum proses.'],
            [6, 'CFO sudah luluskan. Boleh teruskan.'],
            [9, 'Grafana sudah berjalan. Seterusnya Prometheus.'],
            [7, 'Draf pertama sudah siap. Mohon semakan.'],
            [11, 'Template post-mortem ada di Google Drive.'],
        ];

        foreach ($commentsData as [$taskIdx, $body]) {
            TaskComment::create([
                'task_id' => $createdTasks[$taskIdx]->id,
                'employee_id' => $attendees->random()->id,
                'content' => $body,
            ]);
        }

        $this->command->info('MOM Seeder completed:');
        $this->command->info('  - 3 meeting series');
        $this->command->info('  - 10 meetings (4 completed, 3 scheduled, 1 draft, 1 in_progress, 1 cancelled)');
        $this->command->info('  - Agenda items, attendees, decisions');
        $this->command->info('  - 12 tasks with subtasks and comments');
    }
}
