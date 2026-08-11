<?php

namespace Database\Seeders;

use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoFormSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();

        if (! $admin) {
            $this->command->warn('No admin user found; skipping demo form seed.');

            return;
        }

        $category = FormCategory::firstOrCreate(
            ['slug' => 'pendaftaran'],
            ['name' => 'Pendaftaran', 'color' => '#4F46E5', 'is_active' => true],
        );

        $fields = [
            ['id' => 'fld_name', 'type' => 'short_text', 'label' => 'Nama Penuh', 'help' => null, 'required' => true, 'options' => [], 'settings' => []],
            ['id' => 'fld_email', 'type' => 'email', 'label' => 'Emel', 'help' => null, 'required' => true, 'options' => [], 'settings' => []],
            ['id' => 'fld_phone', 'type' => 'phone', 'label' => 'No. Telefon', 'help' => null, 'required' => false, 'options' => [], 'settings' => []],
            ['id' => 'fld_sesi', 'type' => 'radio', 'label' => 'Sesi Pilihan', 'help' => null, 'required' => true, 'options' => ['Pagi', 'Petang', 'Malam'], 'settings' => []],
            ['id' => 'fld_topik', 'type' => 'checkbox', 'label' => 'Topik Diminati', 'help' => 'Boleh pilih lebih dari satu', 'required' => false, 'options' => ['Fiqh', 'Tafsir', 'Sirah'], 'settings' => []],
            ['id' => 'fld_rating', 'type' => 'rating', 'label' => 'Penilaian Program', 'help' => null, 'required' => false, 'options' => [], 'settings' => ['max' => 5]],
            ['id' => 'fld_komen', 'type' => 'long_text', 'label' => 'Komen / Cadangan', 'help' => null, 'required' => false, 'options' => [], 'settings' => []],
        ];

        $form = Form::create([
            'user_id' => $admin->id,
            'form_category_id' => $category->id,
            'title' => 'Borang Pendaftaran Kelas Daie 2026',
            'description' => 'Sila isi borang pendaftaran untuk menyertai sesi Kelas Daie. Maklumat anda akan dirahsiakan.',
            'status' => Form::STATUS_PUBLISHED,
            'fields' => $fields,
            'settings' => [
                'confirmation_message' => 'Terima kasih! Pendaftaran anda telah diterima. Kami akan hubungi anda tidak lama lagi.',
                'allow_multiple' => true,
            ],
            'published_at' => now(),
        ]);

        $rows = [
            ['Ahmad Firdaus bin Ali', 'ahmad.firdaus@example.com', '012-3456789', 'Pagi', ['Fiqh', 'Tafsir'], 5, 'Program yang sangat bermanfaat, teruskan!'],
            ['Nurul Aina binti Hassan', 'nurul.aina@example.com', '019-8765432', 'Malam', ['Sirah'], 4, 'Harap ada rakaman untuk yang terlepas.'],
            ['Muhammad Haziq', 'haziq.m@example.com', '013-2223344', 'Petang', ['Fiqh'], 5, ''],
            ['Siti Zaleha binti Omar', 'siti.zaleha@example.com', '017-5556677', 'Pagi', ['Tafsir', 'Sirah'], 3, 'Waktu pagi agak awal untuk saya.'],
            ['Iqbal Rahman', 'iqbal.rahman@example.com', '011-90001122', 'Malam', ['Fiqh', 'Tafsir', 'Sirah'], 5, 'Ustaz terbaik, sangat jelas penyampaian.'],
            ['Faridah binti Yusof', 'faridah.y@example.com', '', 'Petang', ['Sirah'], 4, 'Alhamdulillah.'],
        ];

        foreach ($rows as $i => $r) {
            $submission = FormSubmission::create([
                'form_id' => $form->id,
                'submitted_by' => null,
                'data' => [
                    'fld_name' => $r[0],
                    'fld_email' => $r[1],
                    'fld_phone' => $r[2],
                    'fld_sesi' => $r[3],
                    'fld_topik' => $r[4],
                    'fld_rating' => $r[5],
                    'fld_komen' => $r[6],
                ],
                'ip_address' => '203.0.113.'.(10 + $i),
                'user_agent' => 'Mozilla/5.0 (demo seed)',
            ]);

            $submission->forceFill(['created_at' => now()->subDays(6 - $i)->subHours($i)])->save();
        }

        $form->update(['submissions_count' => count($rows)]);

        $this->command->info("Demo form created: id={$form->id} slug={$form->slug} submissions={$form->submissions_count}");
        $this->command->info("Submissions page: /forms/{$form->id}/submissions");
        $this->command->info("Public page: /form/{$form->slug}");
    }
}
