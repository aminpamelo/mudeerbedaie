<?php

namespace Database\Seeders;

use App\Models\LiveHostPlatformAccount;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveTimeSlot;
use App\Models\PlatformAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Seeds Live Host Desk session slots (LiveScheduleAssignment rows).
 *
 * Populates the weekly Session Calendar with a mix of assigned and
 * unassigned dated slots across TikTok/Test platform accounts and the
 * active time slots, for the current week (Sunday–Saturday).
 *
 * Respects the DB unique key
 * (platform_account_id, time_slot_id, day_of_week, is_template): each
 * (platform, timeSlot, weekday) combination appears at most once per
 * template flag. Pre-existing rows matching a planned tuple are skipped.
 */
class LiveScheduleAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Session Slots (LiveScheduleAssignment)...');

        $platformAccounts = PlatformAccount::query()
            ->whereIn('name', ['Test', 'TikTok Shop', 'Tiktok Shop Bedaie'])
            ->get()
            ->keyBy('name');

        if ($platformAccounts->isEmpty()) {
            $this->command->warn('No matching platform accounts found. Run PlatformSeeder / LiveHostSeeder first.');

            return;
        }

        $timeSlots = LiveTimeSlot::query()
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get()
            ->keyBy(fn (LiveTimeSlot $s) => substr((string) $s->start_time, 0, 5));

        if ($timeSlots->isEmpty()) {
            $this->command->warn('No active time slots found. Run LiveTimeSlotSeeder first.');

            return;
        }

        $hosts = User::query()
            ->where('role', 'live_host')
            ->get()
            ->keyBy('name');

        $sarah = $hosts->get('Sarah Chen');
        $ahmad = $hosts->get('Ahmad Rahman');
        $amin = $hosts->get('amin suhardi');

        $admin = User::query()->where('role', 'admin')->first();

        $weekStart = CarbonImmutable::now()->startOfWeek(CarbonImmutable::SUNDAY);

        $pivots = LiveHostPlatformAccount::query()->get();
        $pivotFor = fn (?int $userId, int $platformAccountId): ?LiveHostPlatformAccount => $userId === null
            ? null
            : $pivots->firstWhere(
                fn (LiveHostPlatformAccount $p) => $p->user_id === $userId
                    && $p->platform_account_id === $platformAccountId
            );

        $test = $platformAccounts->get('Test');
        $tiktokShop = $platformAccounts->get('TikTok Shop');
        $tiktokBedaie = $platformAccounts->get('Tiktok Shop Bedaie');

        $earlySlot = $timeSlots->get('06:30');
        $midSlot = $timeSlots->get('08:30');

        /**
         * Each (platform, time_slot, day_of_week) can only exist once for
         * dated slots (is_template=false) due to unique_template_slot. The
         * 5 rows already seeded by earlier work (Sun/Mon Test+06:30,
         * Sun/Mon TikTok Shop+06:30, Mon Bedaie+08:30) are not re-planned
         * below; this plan fills the remaining calendar surface.
         */
        $plan = [
            // [dayOffset, platformAccount, timeSlot, host, status, remarks]
            [0, $tiktokBedaie, $earlySlot, $ahmad, 'scheduled', null],
            [0, $tiktokShop,   $midSlot,   $sarah, 'confirmed', 'Weekend opener — TikTok Shop.'],

            [1, $tiktokShop,   $midSlot,   $sarah, 'confirmed', null],
            [1, $tiktokBedaie, $earlySlot, $amin,  'scheduled', 'Needs assignment.'],

            [2, $test,         $earlySlot, null,   'scheduled', null],
            [2, $tiktokShop,   $earlySlot, $ahmad, 'scheduled', null],
            [2, $tiktokBedaie, $midSlot,   $amin,  'scheduled', null],

            [3, $test,         $earlySlot, $amin,  'confirmed', null],
            [3, $tiktokShop,   $earlySlot, $sarah, 'scheduled', 'Midweek push — new stock.'],
            [3, $tiktokBedaie, $midSlot,   $ahmad, 'scheduled', null],

            [4, $test,         $earlySlot, null,   'scheduled', null],
            [4, $tiktokShop,   $earlySlot, $sarah, 'scheduled', null],
            [4, $tiktokBedaie, $midSlot,   $ahmad, 'confirmed', null],

            [5, $test,         $midSlot,   null,   'scheduled', null],
            [5, $tiktokShop,   $earlySlot, $ahmad, 'scheduled', 'Friday special.'],
            [5, $tiktokBedaie, $earlySlot, $amin,  'scheduled', null],

            [6, $tiktokShop,   $earlySlot, $sarah, 'confirmed', 'Weekend flash sale.'],
            [6, $tiktokBedaie, $midSlot,   $amin,  'scheduled', null],
        ];

        $templates = [
            // [dayOfWeek, platformAccount, timeSlot, host]
            [1, $tiktokShop,   $earlySlot, $sarah],
            [3, $tiktokBedaie, $midSlot,   $ahmad],
            [5, $test,         $earlySlot, null],
        ];

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($plan as [$dayOffset, $platformAccount, $timeSlot, $host, $status, $remarks]) {
            if (! $platformAccount || ! $timeSlot) {
                continue;
            }

            $scheduleDate = $weekStart->addDays($dayOffset);
            $dayOfWeek = (int) $scheduleDate->dayOfWeek;
            $hostId = $host?->id;
            $pivot = $pivotFor($hostId, $platformAccount->id);

            $exists = LiveScheduleAssignment::query()
                ->where('platform_account_id', $platformAccount->id)
                ->where('time_slot_id', $timeSlot->id)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_template', false)
                ->exists();

            if ($exists) {
                $skippedCount++;

                continue;
            }

            LiveScheduleAssignment::create([
                'platform_account_id' => $platformAccount->id,
                'live_host_platform_account_id' => $pivot?->id,
                'time_slot_id' => $timeSlot->id,
                'live_host_id' => $hostId,
                'day_of_week' => $dayOfWeek,
                'schedule_date' => $scheduleDate->toDateString(),
                'is_template' => false,
                'status' => $status,
                'remarks' => $remarks,
                'created_by' => $admin?->id,
            ]);

            $createdCount++;
        }

        foreach ($templates as [$dayOfWeek, $platformAccount, $timeSlot, $host]) {
            if (! $platformAccount || ! $timeSlot) {
                continue;
            }

            $hostId = $host?->id;
            $pivot = $pivotFor($hostId, $platformAccount->id);

            $exists = LiveScheduleAssignment::query()
                ->where('platform_account_id', $platformAccount->id)
                ->where('time_slot_id', $timeSlot->id)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_template', true)
                ->exists();

            if ($exists) {
                $skippedCount++;

                continue;
            }

            LiveScheduleAssignment::create([
                'platform_account_id' => $platformAccount->id,
                'live_host_platform_account_id' => $pivot?->id,
                'time_slot_id' => $timeSlot->id,
                'live_host_id' => $hostId,
                'day_of_week' => $dayOfWeek,
                'schedule_date' => null,
                'is_template' => true,
                'status' => 'scheduled',
                'remarks' => null,
                'created_by' => $admin?->id,
            ]);

            $createdCount++;
        }

        $this->command->info("  Created {$createdCount} session slots, skipped {$skippedCount} existing.");
    }
}
