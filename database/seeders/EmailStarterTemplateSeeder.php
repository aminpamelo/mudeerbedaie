<?php

namespace Database\Seeders;

use App\Models\EmailStarterTemplate;
use Illuminate\Database\Seeder;

class EmailStarterTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Templat Kosong',
                'slug' => 'blank',
                'category' => 'blank',
                'description' => 'Mula dengan kanvas kosong dan bina reka bentuk anda dari awal.',
                'sort_order' => 1,
                'design_json' => [],
                'html_content' => '',
            ],
            [
                'name' => 'Peringatan Sesi',
                'slug' => 'session-reminder',
                'category' => 'reminder',
                'description' => 'Templat peringatan untuk sesi yang akan datang dengan maklumat sesi lengkap.',
                'sort_order' => 2,
                'design_json' => $this->getSessionReminderDesign(),
                'html_content' => $this->getSessionReminderHtml(),
            ],
            [
                'name' => 'Selamat Datang',
                'slug' => 'welcome',
                'category' => 'welcome',
                'description' => 'E-mel selamat datang untuk pelajar baru dengan pengenalan kursus.',
                'sort_order' => 3,
                'design_json' => $this->getWelcomeDesign(),
                'html_content' => $this->getWelcomeHtml(),
            ],
            [
                'name' => 'Susulan Sesi',
                'slug' => 'followup',
                'category' => 'followup',
                'description' => 'E-mel susulan selepas sesi dengan statistik kehadiran.',
                'sort_order' => 4,
                'design_json' => $this->getFollowupDesign(),
                'html_content' => $this->getFollowupHtml(),
            ],
            [
                'name' => 'Pengumuman',
                'slug' => 'announcement',
                'category' => 'marketing',
                'description' => 'Templat pengumuman dengan header menarik dan butang CTA.',
                'sort_order' => 5,
                'design_json' => $this->getAnnouncementDesign(),
                'html_content' => $this->getAnnouncementHtml(),
            ],
            [
                'name' => 'Notis Ringkas',
                'slug' => 'simple-notice',
                'category' => 'reminder',
                'description' => 'Templat ringkas untuk pemberitahuan mudah.',
                'sort_order' => 6,
                'design_json' => $this->getSimpleNoticeDesign(),
                'html_content' => $this->getSimpleNoticeHtml(),
            ],
        ];

        foreach ($templates as $template) {
            EmailStarterTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }

    private function getSessionReminderDesign(): array
    {
        return [
            'assets' => [],
            'styles' => [],
            'pages' => [
                [
                    'frames' => [
                        [
                            'component' => [
                                'type' => 'wrapper',
                                'stylable' => ['background', 'background-color', 'background-image', 'background-repeat', 'background-attachment', 'background-position', 'background-size'],
                                'components' => [
                                    [
                                        'tagName' => 'table',
                                        'attributes' => ['width' => '100%'],
                                        'style' => ['background-color' => '#f8fafc', 'padding' => '24px'],
                                        'content' => '',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getSessionReminderHtml(): string
    {
        return <<<'HTML'
<table width="100%" style="background-color: #f8fafc; padding: 24px;">
    <tr>
        <td align="center">
            <h1 style="color: #1e293b; font-size: 24px; margin: 0; font-weight: 600;">{{class_name}}</h1>
            <p style="color: #64748b; font-size: 14px; margin-top: 8px; margin-bottom: 0;">{{course_name}}</p>
        </td>
    </tr>
</table>
<table width="100%" style="padding: 24px;">
    <tr>
        <td>
            <p style="font-size: 16px; color: #1f2937; margin: 0 0 16px 0; line-height: 1.6;">
                Salam sejahtera <strong>{{student_name}}</strong>,
            </p>
            <p style="font-size: 14px; color: #374151; margin: 0 0 20px 0; line-height: 1.6;">
                Ini adalah peringatan untuk sesi anda yang akan datang. Sila ambil maklum butiran di bawah:
            </p>
        </td>
    </tr>
</table>
<table width="100%" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; margin: 0 24px 24px 24px; max-width: calc(100% - 48px);">
    <tr>
        <td style="padding: 20px;">
            <p style="margin: 0 0 12px 0; color: #374151; font-size: 14px; line-height: 1.5;">
                <strong style="color: #1f2937;">📅 Tarikh:</strong> {{session_date}}
            </p>
            <p style="margin: 0 0 12px 0; color: #374151; font-size: 14px; line-height: 1.5;">
                <strong style="color: #1f2937;">🕐 Masa:</strong> {{session_time}}
            </p>
            <p style="margin: 0 0 12px 0; color: #374151; font-size: 14px; line-height: 1.5;">
                <strong style="color: #1f2937;">📍 Lokasi:</strong> {{location}}
            </p>
            <p style="margin: 0; color: #374151; font-size: 14px; line-height: 1.5;">
                <strong style="color: #1f2937;">⏱️ Tempoh:</strong> {{duration}}
            </p>
        </td>
    </tr>
</table>
<table width="100%" style="padding: 0 24px 24px 24px;">
    <tr>
        <td align="center">
            <a href="{{meeting_url}}" style="display: inline-block; padding: 14px 28px; background-color: #3b82f6; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">
                Sertai Sesi Sekarang
            </a>
        </td>
    </tr>
</table>
<table width="100%" style="background-color: #f1f5f9; padding: 24px;">
    <tr>
        <td align="center">
            <p style="color: #64748b; font-size: 12px; margin: 0; line-height: 1.6;">
                Terima kasih kerana menggunakan perkhidmatan kami.
            </p>
        </td>
    </tr>
</table>
HTML;
    }

    private function getWelcomeDesign(): array
    {
        return ['assets' => [], 'styles' => [], 'pages' => []];
    }

    private function getWelcomeHtml(): string
    {
        return <<<'HTML'
<table width="100%" style="background-color: #3b82f6; padding: 32px;">
    <tr>
        <td align="center">
            <h1 style="color: #ffffff; font-size: 28px; margin: 0; font-weight: 600;">Selamat Datang!</h1>
            <p style="color: #bfdbfe; font-size: 16px; margin-top: 8px; margin-bottom: 0;">Kami gembira anda menyertai kami</p>
        </td>
    </tr>
</table>
<table width="100%" style="padding: 32px;">
    <tr>
        <td>
            <p style="font-size: 16px; color: #1f2937; margin: 0 0 16px 0; line-height: 1.6;">
                Salam sejahtera <strong>{{student_name}}</strong>,
            </p>
            <p style="font-size: 14px; color: #374151; margin: 0 0 20px 0; line-height: 1.6;">
                Selamat datang ke <strong>{{course_name}}</strong>! Kami sangat teruja untuk memulakan perjalanan pembelajaran bersama anda.
            </p>
            <p style="font-size: 14px; color: #374151; margin: 0 0 20px 0; line-height: 1.6;">
                Kelas anda: <strong>{{class_name}}</strong>
            </p>
            <p style="font-size: 14px; color: #374151; margin: 0 0 24px 0; line-height: 1.6;">
                Guru anda ialah <strong>{{teacher_name}}</strong> yang akan membimbing anda sepanjang kursus ini.
            </p>
        </td>
    </tr>
</table>
<table width="100%" style="padding: 0 32px 32px 32px;">
    <tr>
        <td align="center">
            <a href="{{whatsapp_link}}" style="display: inline-block; margin: 0 6px; padding: 12px 20px; background-color: #25D366; color: white; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 500;">
                💬 Sertai WhatsApp Group
            </a>
        </td>
    </tr>
</table>
<table width="100%" style="background-color: #f1f5f9; padding: 24px;">
    <tr>
        <td align="center">
            <p style="color: #64748b; font-size: 12px; margin: 0; line-height: 1.6;">
                Jika anda mempunyai sebarang soalan, sila hubungi kami.
            </p>
        </td>
    </tr>
</table>
HTML;
    }

    private function getFollowupDesign(): array
    {
        return ['assets' => [], 'styles' => [], 'pages' => []];
    }

    private function getFollowupHtml(): string
    {
        return <<<'HTML'
<table width="100%" style="background-color: #f8fafc; padding: 24px;">
    <tr>
        <td align="center">
            <h1 style="color: #1e293b; font-size: 24px; margin: 0; font-weight: 600;">Terima Kasih!</h1>
            <p style="color: #64748b; font-size: 14px; margin-top: 8px; margin-bottom: 0;">Ringkasan sesi anda</p>
        </td>
    </tr>
</table>
<table width="100%" style="padding: 24px;">
    <tr>
        <td>
            <p style="font-size: 16px; color: #1f2937; margin: 0 0 16px 0; line-height: 1.6;">
                Salam sejahtera <strong>{{student_name}}</strong>,
            </p>
            <p style="font-size: 14px; color: #374151; margin: 0 0 20px 0; line-height: 1.6;">
                Terima kasih kerana menghadiri sesi <strong>{{class_name}}</strong> pada {{session_date}}.
            </p>
        </td>
    </tr>
</table>
<table width="100%" style="background-color: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; margin: 0 24px 24px 24px; max-width: calc(100% - 48px);">
    <tr>
        <td align="center" style="padding: 20px; width: 50%; border-right: 1px solid #86efac;">
            <p style="margin: 0; color: #166534; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                Kadar Kehadiran
            </p>
            <p style="margin: 8px 0 0 0; color: #15803d; font-size: 28px; font-weight: bold;">
                {{attendance_rate}}%
            </p>
        </td>
        <td align="center" style="padding: 20px; width: 50%;">
            <p style="margin: 0; color: #166534; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                Sesi Tinggal
            </p>
            <p style="margin: 8px 0 0 0; color: #15803d; font-size: 28px; font-weight: bold;">
                {{remaining_sessions}}/{{total_sessions}}
            </p>
        </td>
    </tr>
</table>
<table width="100%" style="padding: 0 24px 24px 24px;">
    <tr>
        <td>
            <p style="font-size: 14px; color: #374151; margin: 0; line-height: 1.6;">
                Teruskan usaha anda! Sesi seterusnya akan diadakan mengikut jadual. Kami nantikan kehadiran anda.
            </p>
        </td>
    </tr>
</table>
<table width="100%" style="background-color: #f1f5f9; padding: 24px;">
    <tr>
        <td align="center">
            <p style="color: #64748b; font-size: 12px; margin: 0; line-height: 1.6;">
                Terima kasih kerana menggunakan perkhidmatan kami.
            </p>
        </td>
    </tr>
</table>
HTML;
    }

    private function getAnnouncementDesign(): array
    {
        return ['assets' => [], 'styles' => [], 'pages' => []];
    }

    private function getAnnouncementHtml(): string
    {
        return <<<'HTML'
<table width="100%" style="background-color: #7c3aed; padding: 32px;">
    <tr>
        <td align="center">
            <p style="color: #e9d5ff; font-size: 12px; text-transform: uppercase; letter-spacing: 2px; margin: 0 0 8px 0;">Pengumuman Penting</p>
            <h1 style="color: #ffffff; font-size: 28px; margin: 0; font-weight: 700;">{{class_name}}</h1>
        </td>
    </tr>
</table>
<table width="100%" style="padding: 32px;">
    <tr>
        <td>
            <p style="font-size: 16px; color: #1f2937; margin: 0 0 16px 0; line-height: 1.6;">
                Salam sejahtera <strong>{{student_name}}</strong>,
            </p>
            <p style="font-size: 14px; color: #374151; margin: 0 0 20px 0; line-height: 1.6;">
                Kami ingin memaklumkan anda tentang pengumuman penting berkaitan kursus <strong>{{course_name}}</strong>.
            </p>
        </td>
    </tr>
</table>
<table width="100%" style="background-color: #fef3c7; border-left: 4px solid #f59e0b; margin: 0 24px 24px 24px; max-width: calc(100% - 48px);">
    <tr>
        <td style="padding: 16px 20px;">
            <p style="margin: 0; color: #92400e; font-weight: 600; font-size: 14px;">
                ⚠️ Sila ambil perhatian
            </p>
            <p style="margin: 8px 0 0 0; color: #a16207; font-size: 13px; line-height: 1.5;">
                Pastikan anda mengikuti perkembangan terkini dan hadir pada masa yang ditetapkan.
            </p>
        </td>
    </tr>
</table>
<table width="100%" style="padding: 0 32px 32px 32px;">
    <tr>
        <td align="center">
            <a href="{{meeting_url}}" style="display: inline-block; padding: 14px 32px; background-color: #7c3aed; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">
                Maklumat Lanjut
            </a>
        </td>
    </tr>
</table>
<table width="100%" style="background-color: #f1f5f9; padding: 24px;">
    <tr>
        <td align="center">
            <p style="color: #64748b; font-size: 12px; margin: 0; line-height: 1.6;">
                Jika anda mempunyai sebarang soalan, sila hubungi kami.
            </p>
        </td>
    </tr>
</table>
HTML;
    }

    private function getSimpleNoticeDesign(): array
    {
        return ['assets' => [], 'styles' => [], 'pages' => []];
    }

    private function getSimpleNoticeHtml(): string
    {
        return <<<'HTML'
<table width="100%" style="padding: 32px;">
    <tr>
        <td>
            <p style="font-size: 16px; color: #1f2937; margin: 0 0 16px 0; line-height: 1.6;">
                Salam sejahtera <strong>{{student_name}}</strong>,
            </p>
            <p style="font-size: 14px; color: #374151; margin: 0 0 20px 0; line-height: 1.6;">
                Ini adalah notis mengenai kelas <strong>{{class_name}}</strong> anda.
            </p>
            <p style="font-size: 14px; color: #374151; margin: 0 0 20px 0; line-height: 1.6;">
                Sila pastikan anda bersedia untuk sesi pada <strong>{{session_datetime}}</strong>.
            </p>
            <p style="font-size: 14px; color: #374151; margin: 0; line-height: 1.6;">
                Terima kasih.
            </p>
        </td>
    </tr>
</table>
<hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 0 24px;">
<table width="100%" style="padding: 24px;">
    <tr>
        <td align="center">
            <p style="color: #64748b; font-size: 12px; margin: 0; line-height: 1.6;">
                E-mel ini dihantar secara automatik.
            </p>
        </td>
    </tr>
</table>
HTML;
    }
}
