<?php

namespace Database\Factories;

use App\Models\ClassModel;
use App\Models\ClassNotificationSetting;
use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassNotificationSetting>
 */
class ClassNotificationSettingFactory extends Factory
{
    protected $model = ClassNotificationSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => ClassModel::factory(),
            'notification_type' => $this->faker->randomElement([
                'session_reminder_24h',
                'session_reminder_3h',
                'session_reminder_1h',
                'enrollment_welcome',
            ]),
            'is_enabled' => true,
            'template_id' => NotificationTemplate::factory(),
            'send_to_students' => true,
            'send_to_teacher' => true,
        ];
    }
}
