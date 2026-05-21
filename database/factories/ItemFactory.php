<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->word(),
            'code_prefix' => fake()->unique()->word(),
            'file_name' => 'test.wav',
            'file_path' => 'items/2026/05/21/CODE123.wav',
            'itemable_type' => 'App\Models\Collection',
            'itemable_id' => 1,
        ];
    }
}
