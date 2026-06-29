<?php

namespace Database\Factories;

use App\Models\AiSalesPage;
use App\Models\AiSalesPageVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiSalesPageVersion>
 */
class AiSalesPageVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_sales_page_id' => AiSalesPage::factory(),
            'version' => 1,
            'label' => null,
            'html' => '<!DOCTYPE html><html><body><h1>'.$this->faker->catchPhrase().'</h1></body></html>',
            'custom_css' => null,
            'custom_js' => null,
            'generated_by' => 'ai',
            'prompt_snapshot' => $this->faker->paragraph(),
            'model' => 'gpt-4o',
        ];
    }
}
