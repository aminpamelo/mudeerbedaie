<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class AffiliateTaskTemplateSeeder extends Seeder
{
    /**
     * Seed task templates for the Affiliate department.
     *
     * Covers 3 workflows:
     * A. Content Staff TikTok Reporting (6 templates)
     * B. New Recruit Affiliate (7 templates)
     * C. KPI Content Creator (3 templates)
     */
    public function run(): void
    {
        $creator = User::where('role', 'admin')->first()
            ?? User::firstOrFail();

        // Each workflow maps to its own sub-department
        $contentStaff = Department::where('slug', 'content-staff')->firstOrFail();
        $recruitAffiliate = Department::where('slug', 'recruit-affiliate')->firstOrFail();
        $kpiContentCreator = Department::where('slug', 'kpi-content-creator')->firstOrFail();

        // Clean up old templates that were on parent affiliate department
        $affiliate = Department::where('slug', 'affiliate')->first();
        if ($affiliate) {
            TaskTemplate::where('department_id', $affiliate->id)->delete();
        }

        $this->seedContentStaffTemplates($contentStaff, $creator);
        $this->seedRecruitAffiliateTemplates($recruitAffiliate, $creator);
        $this->seedKpiContentCreatorTemplates($kpiContentCreator, $creator);

        $this->command->info('Created 16 Affiliate sub-department task templates (6 Content Staff, 7 Recruit, 3 KPI).');
    }

    /**
     * Workflow A: Content Staff TikTok Reporting
     * One template per TikTok account (6 accounts).
     */
    private function seedContentStaffTemplates(Department $department, User $creator): void
    {
        $accounts = [
            'addaie.fin',
            'theaaaz.bedaie',
            'zearose.daie',
            'daienation',
            'amazah.daie',
            'addaie.atiqa',
        ];

        foreach ($accounts as $account) {
            TaskTemplate::updateOrCreate(
                [
                    'department_id' => $department->id,
                    'name' => "Laporan Harian TikTok - {$account}",
                ],
                [
                    'created_by' => $creator->id,
                    'description' => "Template untuk tugasan posting harian TikTok akaun {$account}",
                    'task_type' => 'kpi',
                    'priority' => 'medium',
                    'estimated_hours' => 1.00,
                    'template_data' => [
                        'title' => "Post TikTok Harian - {$account} - [TARIKH]",
                        'description' => implode("\n", [
                            "Sila post video TikTok untuk akaun {$account}.",
                            '',
                            'Sila isi maklumat berikut dalam komen selepas selesai:',
                            '- Nama Buku: ',
                            '- Link TikTok: ',
                            '- Video ID: ',
                            '- Tarikh Post: ',
                        ]),
                        'workflow' => 'content_staff_reporting',
                        'tiktok_account' => $account,
                        'metadata_schema' => [
                            'tiktok_account' => $account,
                            'book_name' => '',
                            'tiktok_link' => '',
                            'video_id' => '',
                            'post_date' => '',
                            'editor_review' => '',
                            'editor_comments' => '',
                        ],
                    ],
                ]
            );
        }
    }

    /**
     * Workflow B: New Recruit Affiliate
     * One template per affiliate category (7 categories).
     */
    private function seedRecruitAffiliateTemplates(Department $department, User $creator): void
    {
        $categories = [
            ['name' => 'AFF Ustaz Amar', 'slug' => 'aff_ustaz_amar', 'priority' => 'high', 'hours' => 2.00],
            ['name' => 'AFF Ilmu Agama', 'slug' => 'aff_ilmu_agama', 'priority' => 'high', 'hours' => 2.00],
            ['name' => 'AFF Buku Kanak-Kanak', 'slug' => 'aff_buku_kanak_kanak', 'priority' => 'medium', 'hours' => 2.00],
            ['name' => 'AFF Indonesia', 'slug' => 'aff_indonesia', 'priority' => 'medium', 'hours' => 2.00],
            ['name' => 'AFF Shopee', 'slug' => 'aff_shopee', 'priority' => 'medium', 'hours' => 2.00],
            ['name' => 'Data Affiliate VIP', 'slug' => 'data_affiliate_vip', 'priority' => 'high', 'hours' => 2.00],
            ['name' => 'Influencer', 'slug' => 'influencer', 'priority' => 'urgent', 'hours' => 3.00],
        ];

        foreach ($categories as $cat) {
            TaskTemplate::updateOrCreate(
                [
                    'department_id' => $department->id,
                    'name' => "Recruit Affiliate - {$cat['name']}",
                ],
                [
                    'created_by' => $creator->id,
                    'description' => "Template untuk proses recruit affiliate baru kategori {$cat['name']}",
                    'task_type' => 'adhoc',
                    'priority' => $cat['priority'],
                    'estimated_hours' => $cat['hours'],
                    'template_data' => [
                        'title' => "Recruit Affiliate Baru - {$cat['name']} - [NAMA TIKTOK]",
                        'description' => implode("\n", [
                            "Proses recruit affiliate baru kategori {$cat['name']}.",
                            '',
                            'Pipeline:',
                            '1. Jemputan dihantar',
                            '2. Hubungi calon',
                            '3. Hantar sample percuma',
                            '4. Tunggu kelulusan sample',
                            '5. Masuk group',
                            '6. Pantau jualan',
                            '',
                            'Sila update status dalam komen.',
                        ]),
                        'workflow' => 'new_recruit_affiliate',
                        'affiliate_category' => $cat['slug'],
                        'metadata_schema' => [
                            'affiliate_category' => $cat['slug'],
                            'tiktok_name' => '',
                            'contact_number' => '',
                            'result_status' => '',
                            'free_sample_details' => '',
                            'group_status' => '',
                            'sales_amount' => 0,
                        ],
                    ],
                ]
            );
        }
    }

    /**
     * Workflow C: KPI Content Creator
     * 3 reusable templates for monthly KPI, video recording, and KPI review.
     */
    private function seedKpiContentCreatorTemplates(Department $department, User $creator): void
    {
        $templates = [
            [
                'name' => 'KPI Bulanan Content Creator',
                'description' => 'Template untuk tugasan KPI bulanan content creator (target 100 video)',
                'priority' => 'high',
                'hours' => 0.50,
                'template_data' => [
                    'title' => 'KPI Bulanan - [NAMA USTAZ] - [BULAN/TAHUN]',
                    'description' => implode("\n", [
                        'Target KPI bulanan: 100 video',
                        '',
                        'Jenis video:',
                        '- Tunjuk Buku',
                        '- Tunjuk Muka',
                        '- Podcast',
                        '- Tarik Masuk Live',
                        '- Ustaz Famous',
                        '',
                        'Sila kemaskini progress dalam komen secara berkala.',
                    ]),
                    'workflow' => 'kpi_content_creator',
                    'metadata_schema' => [
                        'creator_name' => '',
                        'month' => '',
                        'year' => '',
                        'target_videos' => 100,
                        'completed_videos' => 0,
                        'video_breakdown' => [
                            'tunjuk_buku' => 0,
                            'tunjuk_muka' => 0,
                            'podcast' => 0,
                            'tarik_masuk_live' => 0,
                            'ustaz_famous' => 0,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Rakam Video Content Creator',
                'description' => 'Template untuk tugasan rakam dan post video oleh content creator',
                'priority' => 'medium',
                'hours' => 1.00,
                'template_data' => [
                    'title' => 'Rakam Video - [NAMA USTAZ] - [JENIS VIDEO]',
                    'description' => implode("\n", [
                        'Sila rakam dan post video.',
                        '',
                        'Maklumat yang perlu diisi dalam komen:',
                        '- Jenis Video: ',
                        '- Link Raw Video: ',
                        '- Link TikTok: ',
                        '- Tarikh Post: ',
                        '- Video ID: ',
                        '- Akaun TikTok: ',
                    ]),
                    'workflow' => 'kpi_content_creator_video',
                    'metadata_schema' => [
                        'creator_name' => '',
                        'video_type' => '',
                        'raw_video_link' => '',
                        'tiktok_link' => '',
                        'post_date' => '',
                        'video_id' => '',
                        'tiktok_account' => '',
                        'sales_amount' => 0,
                    ],
                ],
            ],
            [
                'name' => 'Semakan KPI Content Creator',
                'description' => 'Template untuk semakan/review KPI content creator bulanan',
                'priority' => 'high',
                'hours' => 2.00,
                'template_data' => [
                    'title' => 'Semakan KPI - [NAMA USTAZ] - [BULAN/TAHUN]',
                    'description' => implode("\n", [
                        'Review pencapaian KPI content creator untuk bulan ini.',
                        '',
                        'Semak:',
                        '- Jumlah video yang telah dipost',
                        '- Pecahan mengikut jenis video',
                        '- Jualan yang terhasil',
                        '- Cadangan penambahbaikan',
                    ]),
                    'workflow' => 'kpi_content_creator_review',
                    'metadata_schema' => [
                        'creator_name' => '',
                        'month' => '',
                        'year' => '',
                        'total_videos_posted' => 0,
                        'total_sales' => 0,
                        'review_notes' => '',
                    ],
                ],
            ],
        ];

        foreach ($templates as $tpl) {
            TaskTemplate::updateOrCreate(
                [
                    'department_id' => $department->id,
                    'name' => $tpl['name'],
                ],
                [
                    'created_by' => $creator->id,
                    'description' => $tpl['description'],
                    'task_type' => 'kpi',
                    'priority' => $tpl['priority'],
                    'estimated_hours' => $tpl['hours'],
                    'template_data' => $tpl['template_data'],
                ]
            );
        }
    }
}
