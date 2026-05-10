<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ItemType>
 */
class ItemTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'suffix' => $this->faker->unique()->lexify('???'),
            'description' => $this->faker->sentence(),
            'requires_language' => false,
            'is_active' => true,
            'created_by' => \App\Models\User::factory(),
            'allowed_extensions' => null,
        ];
    }
}
