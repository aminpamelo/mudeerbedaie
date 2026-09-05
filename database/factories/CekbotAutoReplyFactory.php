<?php

namespace Database\Factories;

use App\Models\CekbotAutoReply;
use App\Models\CekbotSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CekbotAutoReply>
 */
class CekbotAutoReplyFactory extends Factory
{
    protected $model = CekbotAutoReply::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cekbot_session_id' => CekbotSession::factory(),
            'name' => $this->faker->words(2, true),
            'match_type' => 'contains',
            'keywords' => ['harga', 'price'],
            'reply_body' => 'Harga produk kami bermula RM99. Boleh saya bantu?',
            'is_active' => true,
            'priority' => 100,
        ];
    }
}
