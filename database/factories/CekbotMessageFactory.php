<?php

namespace Database\Factories;

use App\Models\CekbotConversation;
use App\Models\CekbotMessage;
use App\Models\CekbotSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CekbotMessage>
 */
class CekbotMessageFactory extends Factory
{
    protected $model = CekbotMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cekbot_conversation_id' => CekbotConversation::factory(),
            'cekbot_session_id' => CekbotSession::factory(),
            'waha_message_id' => 'false_'.$this->faker->uuid(),
            'direction' => CekbotMessage::DIRECTION_IN,
            'from_me' => false,
            'type' => 'text',
            'body' => $this->faker->sentence(),
            'sent_at' => now(),
        ];
    }

    public function outbound(): static
    {
        return $this->state(fn () => [
            'direction' => CekbotMessage::DIRECTION_OUT,
            'from_me' => true,
        ]);
    }
}
