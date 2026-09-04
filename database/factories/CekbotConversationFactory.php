<?php

namespace Database\Factories;

use App\Models\CekbotConversation;
use App\Models\CekbotSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CekbotConversation>
 */
class CekbotConversationFactory extends Factory
{
    protected $model = CekbotConversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cekbot_session_id' => CekbotSession::factory(),
            'chat_id' => '60'.$this->faker->numerify('#########').'@c.us',
            'name' => $this->faker->name(),
            'is_group' => false,
            'unread_count' => 0,
            'last_message_at' => now(),
            'last_message_preview' => $this->faker->sentence(),
        ];
    }
}
