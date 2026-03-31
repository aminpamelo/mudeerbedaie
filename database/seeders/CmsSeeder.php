<?php

namespace Database\Seeders;

use App\Models\AdCampaign;
use App\Models\AdStat;
use App\Models\Content;
use App\Models\ContentStage;
use App\Models\ContentStageAssignee;
use App\Models\ContentStat;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class CmsSeeder extends Seeder
{
    /**
     * Seed the CMS module with realistic content pipeline data.
     */
    public function run(): void
    {
        $employees = Employee::all();

        if ($employees->isEmpty()) {
            $this->command->warn('No employees found. Run HrSeeder first.');

            return;
        }

        $stages = ['idea', 'shooting', 'editing', 'posting'];

        // ─── 1. Contents at various pipeline stages ─────────────────────────

        $contentData = [
            // Ideas
            ['title' => 'Cara Mudah Buat Kek Viral', 'stage' => 'idea', 'priority' => 'medium'],
            ['title' => 'Tips Jimat Belanja Harian', 'stage' => 'idea', 'priority' => 'low'],
            ['title' => 'Review Produk Baru 2026', 'stage' => 'idea', 'priority' => 'high'],

            // Shooting
            ['title' => 'Tutorial Makeup Natural', 'stage' => 'shooting', 'priority' => 'high'],
            ['title' => 'Behind The Scenes Warehouse', 'stage' => 'shooting', 'priority' => 'medium'],

            // Editing
            ['title' => 'Unboxing Produk Best Seller', 'stage' => 'editing', 'priority' => 'urgent'],
            ['title' => 'Day In My Life Sebagai CEO', 'stage' => 'editing', 'priority' => 'medium'],

            // Posting
            ['title' => 'Resepi Nasi Ayam Special', 'stage' => 'posting', 'priority' => 'high'],

            // Posted (with TikTok data)
            ['title' => 'Cara Pack Order 1000 Sehari', 'stage' => 'posted', 'priority' => 'medium', 'posted' => true, 'viral' => true],
            ['title' => 'Motivasi Pagi Isnin', 'stage' => 'posted', 'priority' => 'low', 'posted' => true],
            ['title' => 'Skincare Routine Murah', 'stage' => 'posted', 'priority' => 'high', 'posted' => true, 'viral' => true],
            ['title' => 'Q&A Session Dengan Followers', 'stage' => 'posted', 'priority' => 'medium', 'posted' => true],
            ['title' => 'Promosi Raya Sale 2026', 'stage' => 'posted', 'priority' => 'urgent', 'posted' => true, 'viral' => true],
            ['title' => 'Kolaborasi Dengan Influencer', 'stage' => 'posted', 'priority' => 'high', 'posted' => true],
            ['title' => 'Packaging Baru Reveal', 'stage' => 'posted', 'priority' => 'medium', 'posted' => true],
        ];

        foreach ($contentData as $data) {
            $isPosted = $data['posted'] ?? false;
            $isViral = $data['viral'] ?? false;
            $creator = $employees->random();

            $content = Content::create([
                'title' => $data['title'],
                'description' => fake()->paragraph(2),
                'stage' => $data['stage'],
                'due_date' => fake()->dateTimeBetween('-10 days', '+30 days'),
                'priority' => $data['priority'],
                'created_by' => $creator->id,
                'tiktok_url' => $isPosted ? 'https://www.tiktok.com/@mudeerbedaie/video/'.fake()->numerify('############') : null,
                'tiktok_post_id' => $isPosted ? fake()->numerify('############') : null,
                'posted_at' => $isPosted ? fake()->dateTimeBetween('-30 days', '-1 day') : null,
                'is_flagged_for_ads' => $isViral,
                'is_marked_for_ads' => $isViral,
                'marked_by' => $isViral ? $employees->random()->id : null,
                'marked_at' => $isViral ? fake()->dateTimeBetween('-15 days', 'now') : null,
            ]);

            // ─── Create stages with assignees ───────────────────────────

            $stageIndex = array_search($data['stage'], [...$stages, 'posted']);
            $completedUpTo = $data['stage'] === 'posted' ? 4 : $stageIndex;

            foreach ($stages as $i => $stage) {
                $status = $i < $completedUpTo ? 'completed' : ($i === $completedUpTo ? 'in_progress' : 'pending');

                $contentStage = ContentStage::create([
                    'content_id' => $content->id,
                    'stage' => $stage,
                    'status' => $data['stage'] === 'posted' ? 'completed' : $status,
                    'due_date' => fake()->dateTimeBetween('-5 days', '+20 days'),
                    'started_at' => $status !== 'pending' ? fake()->dateTimeBetween('-20 days', '-1 day') : null,
                    'completed_at' => $status === 'completed' || $data['stage'] === 'posted' ? fake()->dateTimeBetween('-10 days', 'now') : null,
                    'notes' => fake()->optional(0.4)->sentence(),
                ]);

                // Assign 1-3 employees per stage
                $assigneeCount = fake()->numberBetween(1, 3);
                $assigneeEmployees = $employees->random($assigneeCount);

                $roles = [
                    'idea' => ['Lead', 'Researcher', 'Copywriter'],
                    'shooting' => ['Videographer', 'Talent', 'Assistant'],
                    'editing' => ['Editor', 'Motion Graphics', 'Reviewer'],
                    'posting' => ['Social Media Manager', 'Scheduler', 'Copywriter'],
                ];

                foreach ($assigneeEmployees as $j => $emp) {
                    ContentStageAssignee::create([
                        'content_stage_id' => $contentStage->id,
                        'employee_id' => $emp->id,
                        'role' => $roles[$stage][$j] ?? null,
                    ]);
                }
            }

            // ─── TikTok stats for posted content ────────────────────────

            if ($isPosted) {
                $baseViews = $isViral ? fake()->numberBetween(15000, 500000) : fake()->numberBetween(200, 9000);

                // Create 3-5 stat snapshots over time
                $snapshots = fake()->numberBetween(3, 5);
                for ($s = 0; $s < $snapshots; $s++) {
                    $multiplier = ($s + 1) / $snapshots;
                    ContentStat::create([
                        'content_id' => $content->id,
                        'views' => (int) ($baseViews * $multiplier),
                        'likes' => (int) ($baseViews * $multiplier * fake()->randomFloat(2, 0.03, 0.12)),
                        'comments' => (int) ($baseViews * $multiplier * fake()->randomFloat(3, 0.002, 0.02)),
                        'shares' => (int) ($baseViews * $multiplier * fake()->randomFloat(3, 0.001, 0.01)),
                        'fetched_at' => fake()->dateTimeBetween('-'.(($snapshots - $s) * 5).' days', '-'.(($snapshots - $s - 1) * 5).' days'),
                    ]);
                }
            }

            // ─── Ad campaigns for viral content ─────────────────────────

            if ($isViral) {
                $campaign = AdCampaign::create([
                    'content_id' => $content->id,
                    'platform' => fake()->randomElement(['facebook', 'tiktok']),
                    'status' => fake()->randomElement(['running', 'completed']),
                    'budget' => fake()->randomFloat(2, 200, 5000),
                    'start_date' => fake()->dateTimeBetween('-20 days', '-5 days'),
                    'end_date' => fake()->dateTimeBetween('-4 days', '+15 days'),
                    'notes' => fake()->optional(0.5)->sentence(),
                    'assigned_by' => $employees->random()->id,
                ]);

                // Ad stats snapshots
                $adSnapshots = fake()->numberBetween(2, 4);
                $totalBudget = $campaign->budget;
                for ($a = 0; $a < $adSnapshots; $a++) {
                    $spendFraction = ($a + 1) / $adSnapshots;
                    $impressions = fake()->numberBetween(5000, 80000) * $spendFraction;

                    AdStat::create([
                        'ad_campaign_id' => $campaign->id,
                        'impressions' => (int) $impressions,
                        'clicks' => (int) ($impressions * fake()->randomFloat(3, 0.01, 0.05)),
                        'spend' => round($totalBudget * $spendFraction, 2),
                        'conversions' => fake()->numberBetween(0, 50),
                        'fetched_at' => fake()->dateTimeBetween('-'.(($adSnapshots - $a) * 4).' days', '-'.(($adSnapshots - $a - 1) * 4).' days'),
                    ]);
                }
            }
        }

        $this->command->info('CMS seeded: '.count($contentData).' contents with stages, assignees, stats & ad campaigns.');
    }
}
