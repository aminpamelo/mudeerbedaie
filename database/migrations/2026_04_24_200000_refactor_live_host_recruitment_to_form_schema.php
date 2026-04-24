<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add nullable JSON columns
        Schema::table('live_host_recruitment_campaigns', function (Blueprint $table) {
            $table->json('form_schema')->nullable()->after('description');
        });

        Schema::table('live_host_applicants', function (Blueprint $table) {
            $table->json('form_data')->nullable()->after('platforms');
            $table->json('form_schema_snapshot')->nullable()->after('form_data');
        });

        // 2. Backfill campaigns
        $defaultSchema = self::defaultSchema();
        DB::table('live_host_recruitment_campaigns')
            ->whereNull('form_schema')
            ->update(['form_schema' => json_encode($defaultSchema)]);

        // 3. Backfill applicants
        DB::table('live_host_applicants')->orderBy('id')->chunkById(200, function ($rows) use ($defaultSchema) {
            foreach ($rows as $row) {
                $formData = [
                    'f_name' => $row->full_name,
                    'f_email' => $row->email,
                    'f_phone' => $row->phone,
                    'f_ic_number' => $row->ic_number,
                    'f_location' => $row->location,
                    'f_platforms' => $row->platforms ? (json_decode($row->platforms, true) ?: []) : [],
                    'f_experience' => $row->experience_summary,
                    'f_motivation' => $row->motivation,
                    'f_resume' => $row->resume_path,
                ];

                DB::table('live_host_applicants')->where('id', $row->id)->update([
                    'form_data' => json_encode($formData),
                    'form_schema_snapshot' => json_encode($defaultSchema),
                ]);
            }
        });

        // 4. Enforce NOT NULL (SQLite-safe: change() on json works)
        Schema::table('live_host_recruitment_campaigns', function (Blueprint $table) {
            $table->json('form_schema')->nullable(false)->change();
        });
        Schema::table('live_host_applicants', function (Blueprint $table) {
            $table->json('form_data')->nullable(false)->change();
            $table->json('form_schema_snapshot')->nullable(false)->change();
        });

        // 5. Drop old columns (8 of 9 — email stays)
        Schema::table('live_host_applicants', function (Blueprint $table) {
            $table->dropColumn([
                'full_name', 'phone', 'ic_number', 'location',
                'platforms', 'experience_summary', 'motivation', 'resume_path',
            ]);
        });
    }

    public function down(): void
    {
        // Restore the dropped columns (as nullable) so rollback is possible
        Schema::table('live_host_applicants', function (Blueprint $table) {
            $table->string('full_name')->nullable()->after('applicant_number');
            $table->string('phone')->nullable()->after('email');
            $table->string('ic_number')->nullable()->after('phone');
            $table->string('location')->nullable()->after('ic_number');
            $table->json('platforms')->nullable()->after('location');
            $table->text('experience_summary')->nullable()->after('platforms');
            $table->text('motivation')->nullable()->after('experience_summary');
            $table->string('resume_path')->nullable()->after('motivation');
        });

        // Restore data from form_data
        DB::table('live_host_applicants')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $data = $row->form_data ? (json_decode($row->form_data, true) ?: []) : [];
                DB::table('live_host_applicants')->where('id', $row->id)->update([
                    'full_name' => $data['f_name'] ?? null,
                    'phone' => $data['f_phone'] ?? null,
                    'ic_number' => $data['f_ic_number'] ?? null,
                    'location' => $data['f_location'] ?? null,
                    'platforms' => isset($data['f_platforms']) ? json_encode($data['f_platforms']) : null,
                    'experience_summary' => $data['f_experience'] ?? null,
                    'motivation' => $data['f_motivation'] ?? null,
                    'resume_path' => $data['f_resume'] ?? null,
                ]);
            }
        });

        Schema::table('live_host_recruitment_campaigns', function (Blueprint $table) {
            $table->dropColumn('form_schema');
        });
        Schema::table('live_host_applicants', function (Blueprint $table) {
            $table->dropColumn(['form_data', 'form_schema_snapshot']);
        });
    }

    private static function defaultSchema(): array
    {
        return [
            'version' => 1,
            'pages' => [
                [
                    'id' => 'page-1',
                    'title' => 'About you',
                    'fields' => [
                        ['id' => 'f_name', 'type' => 'text', 'label' => 'Full name', 'required' => true, 'role' => 'name'],
                        ['id' => 'f_email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'role' => 'email'],
                        ['id' => 'f_phone', 'type' => 'phone', 'label' => 'Phone', 'required' => true, 'role' => 'phone'],
                        ['id' => 'f_ic_number', 'type' => 'text', 'label' => 'IC number', 'required' => false],
                        ['id' => 'f_location', 'type' => 'text', 'label' => 'Location', 'required' => false],
                    ],
                ],
                [
                    'id' => 'page-2',
                    'title' => 'Platforms',
                    'fields' => [
                        [
                            'id' => 'f_platforms',
                            'type' => 'checkbox_group',
                            'label' => 'Platforms you can live on',
                            'required' => true,
                            'options' => [
                                ['value' => 'tiktok', 'label' => 'TikTok'],
                                ['value' => 'shopee', 'label' => 'Shopee'],
                                ['value' => 'facebook', 'label' => 'Facebook'],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'page-3',
                    'title' => 'Your story',
                    'fields' => [
                        ['id' => 'f_experience', 'type' => 'textarea', 'label' => 'Experience', 'required' => false, 'rows' => 4],
                        ['id' => 'f_motivation', 'type' => 'textarea', 'label' => 'Why do you want to join?', 'required' => false, 'rows' => 4],
                        ['id' => 'f_resume', 'type' => 'file', 'label' => 'Resume', 'required' => false, 'role' => 'resume', 'accept' => ['pdf', 'doc', 'docx'], 'max_size_kb' => 5120],
                    ],
                ],
            ],
        ];
    }
};
