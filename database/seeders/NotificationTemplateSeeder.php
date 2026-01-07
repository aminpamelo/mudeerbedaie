<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // Session Reminder Templates (Malay)
            [
                'name' => 'Peringatan Sesi (24 Jam)',
                'slug' => 'session_reminder_24h_ms',
                'type' => 'session_reminder',
                'channel' => 'email',
                'language' => 'ms',
                'subject' => 'Peringatan: Kelas {{class_name}} esok!',
                'content' => <<<'MALAY'
Assalamualaikum {{student_name}},

Ini adalah peringatan bahawa anda mempunyai kelas pada:

**Kelas:** {{class_name}}
**Kursus:** {{course_name}}
**Tarikh:** {{session_date}}
**Masa:** {{session_time}}
**Tempoh:** {{duration}}
**Tempat:** {{location}}

**Pautan Mesyuarat:** {{meeting_url}}

**Pautan WhatsApp Kumpulan:** {{whatsapp_link}}

Sila pastikan anda bersedia untuk kelas ini. Jika anda tidak dapat hadir, sila maklumkan kepada kami terlebih dahulu.

Terima kasih,
{{teacher_name}}
MALAY,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Peringatan Sesi (3 Jam)',
                'slug' => 'session_reminder_3h_ms',
                'type' => 'session_reminder',
                'channel' => 'email',
                'language' => 'ms',
                'subject' => 'Peringatan: Kelas {{class_name}} dalam 3 jam!',
                'content' => <<<'MALAY'
Assalamualaikum {{student_name}},

Kelas anda akan bermula dalam **3 jam**.

**Kelas:** {{class_name}}
**Masa:** {{session_time}}
**Tempat:** {{location}}

**Pautan Mesyuarat:** {{meeting_url}}

Sila bersedia untuk sesi ini.

{{teacher_name}}
MALAY,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Peringatan Sesi (1 Jam)',
                'slug' => 'session_reminder_1h_ms',
                'type' => 'session_reminder',
                'channel' => 'email',
                'language' => 'ms',
                'subject' => 'Peringatan: Kelas {{class_name}} dalam 1 jam!',
                'content' => <<<'MALAY'
Assalamualaikum {{student_name}},

Kelas anda akan bermula dalam **1 jam**.

**Kelas:** {{class_name}}
**Masa:** {{session_time}}
**Tempat:** {{location}}

**Pautan Mesyuarat:** {{meeting_url}}

Jumpa anda nanti!

{{teacher_name}}
MALAY,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Peringatan Sesi (30 Minit)',
                'slug' => 'session_reminder_30m_ms',
                'type' => 'session_reminder',
                'channel' => 'email',
                'language' => 'ms',
                'subject' => 'Peringatan: Kelas {{class_name}} dalam 30 minit!',
                'content' => <<<'MALAY'
Assalamualaikum {{student_name}},

Kelas anda akan bermula dalam **30 minit**.

**Kelas:** {{class_name}}
**Masa:** {{session_time}}

**Pautan Mesyuarat:** {{meeting_url}}

Sila sertai sekarang!

{{teacher_name}}
MALAY,
                'is_system' => true,
                'is_active' => true,
            ],

            // Session Follow-up Templates (Malay)
            [
                'name' => 'Susulan Selepas Sesi',
                'slug' => 'session_followup_immediate_ms',
                'type' => 'session_followup',
                'channel' => 'email',
                'language' => 'ms',
                'subject' => 'Terima Kasih Menghadiri {{class_name}}',
                'content' => <<<'MALAY'
Assalamualaikum {{student_name}},

Terima kasih kerana menghadiri kelas {{class_name}} pada {{session_date}}.

**Ringkasan:**
- Baki sesi anda: {{remaining_sessions}} daripada {{total_sessions}}
- Kadar kehadiran anda: {{attendance_rate}}

Jika anda mempunyai sebarang soalan mengenai sesi hari ini, sila hubungi kami.

Salam hormat,
{{teacher_name}}
MALAY,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Susulan Selepas Sesi (24 Jam)',
                'slug' => 'session_followup_24h_ms',
                'type' => 'session_followup',
                'channel' => 'email',
                'language' => 'ms',
                'subject' => 'Ulasan Kelas {{class_name}} - {{session_date}}',
                'content' => <<<'MALAY'
Assalamualaikum {{student_name}},

Kami harap anda menikmati kelas {{class_name}} semalam.

**Statistik Anda:**
- Sesi yang telah dihadiri: {{total_sessions}} - {{remaining_sessions}} sesi
- Kadar kehadiran: {{attendance_rate}}

Teruskan usaha yang baik! Kami nantikan kehadiran anda di sesi akan datang.

Salam hormat,
{{teacher_name}}
MALAY,
                'is_system' => true,
                'is_active' => true,
            ],

            // Enrollment Welcome Template (Malay)
            [
                'name' => 'Selamat Datang ke Kelas',
                'slug' => 'enrollment_welcome_ms',
                'type' => 'enrollment_welcome',
                'channel' => 'email',
                'language' => 'ms',
                'subject' => 'Selamat Datang ke {{class_name}}!',
                'content' => <<<'MALAY'
Assalamualaikum {{student_name}},

Selamat datang ke kelas **{{class_name}}**!

**Maklumat Kelas:**
- Kursus: {{course_name}}
- Guru: {{teacher_name}}
- Jumlah Sesi: {{total_sessions}}
- Lokasi: {{location}}

**Pautan Penting:**
- Pautan Mesyuarat: {{meeting_url}}
- Kumpulan WhatsApp: {{whatsapp_link}}

Kami sangat gembira menerima anda sebagai pelajar. Jika anda mempunyai sebarang soalan, sila hubungi kami.

Selamat belajar!
{{teacher_name}}
MALAY,
                'is_system' => true,
                'is_active' => true,
            ],

            // Class Completed Template (Malay)
            [
                'name' => 'Tahniah Kelas Selesai',
                'slug' => 'class_completed_ms',
                'type' => 'class_completed',
                'channel' => 'email',
                'language' => 'ms',
                'subject' => 'Tahniah! Anda Telah Menamatkan {{class_name}}',
                'content' => <<<'MALAY'
Assalamualaikum {{student_name}},

Tahniah! Anda telah berjaya menamatkan kelas **{{class_name}}**.

**Pencapaian Anda:**
- Jumlah Sesi: {{total_sessions}}
- Kadar Kehadiran: {{attendance_rate}}

Terima kasih kerana menyertai kelas ini bersama kami. Kami berharap ilmu yang diperoleh akan bermanfaat untuk anda.

Salam hormat,
{{teacher_name}}
MALAY,
                'is_system' => true,
                'is_active' => true,
            ],

            // English Templates
            [
                'name' => 'Session Reminder (24 Hours)',
                'slug' => 'session_reminder_24h_en',
                'type' => 'session_reminder',
                'channel' => 'email',
                'language' => 'en',
                'subject' => 'Reminder: {{class_name}} class tomorrow!',
                'content' => <<<'ENGLISH'
Dear {{student_name}},

This is a reminder that you have an upcoming class:

**Class:** {{class_name}}
**Course:** {{course_name}}
**Date:** {{session_date}}
**Time:** {{session_time}}
**Duration:** {{duration}}
**Location:** {{location}}

**Meeting Link:** {{meeting_url}}

**WhatsApp Group:** {{whatsapp_link}}

Please make sure you are prepared for this class. If you cannot attend, please inform us in advance.

Thank you,
{{teacher_name}}
ENGLISH,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Session Reminder (1 Hour)',
                'slug' => 'session_reminder_1h_en',
                'type' => 'session_reminder',
                'channel' => 'email',
                'language' => 'en',
                'subject' => 'Reminder: {{class_name}} class in 1 hour!',
                'content' => <<<'ENGLISH'
Dear {{student_name}},

Your class starts in **1 hour**.

**Class:** {{class_name}}
**Time:** {{session_time}}
**Location:** {{location}}

**Meeting Link:** {{meeting_url}}

See you soon!

{{teacher_name}}
ENGLISH,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Session Follow-up',
                'slug' => 'session_followup_immediate_en',
                'type' => 'session_followup',
                'channel' => 'email',
                'language' => 'en',
                'subject' => 'Thank You for Attending {{class_name}}',
                'content' => <<<'ENGLISH'
Dear {{student_name}},

Thank you for attending {{class_name}} on {{session_date}}.

**Summary:**
- Remaining sessions: {{remaining_sessions}} of {{total_sessions}}
- Your attendance rate: {{attendance_rate}}

If you have any questions about today's session, please don't hesitate to contact us.

Best regards,
{{teacher_name}}
ENGLISH,
                'is_system' => true,
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }
}
