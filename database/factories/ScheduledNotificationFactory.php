<?php

namespace Database\Factories;

use App\Models\ClassModel;
use App\Models\ClassNotificationSetting;
use App\Models\ScheduledNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScheduledNotification>
 */
class ScheduledNotificationFactory extends Factory
{
    protected $model = ScheduledNotification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => ClassModel::factory(),
            'session_id' => null,
            'class_notification_setting_id' => ClassNotificationSetting::factory(),
            'status' => 'pending',
            'scheduled_at' => now()->addHour(),
            'total_recipients' => 0,
            'total_sent' => 0,
            'total_failed' => 0,
        ];
    }
}
