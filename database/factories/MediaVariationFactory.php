<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MediaVariation>
 */
class MediaVariationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => \App\Models\Item::factory(),
            'is_streaming' => true,
            'disk' => 'local',
            'status' => \App\Enums\MediaVariationStatus::READY->value,
            'type' => 'video',
            'profile_name' => 'hls_standard',
            'file_path' => 'dummy/path',
            'mime_type' => 'video/mp2t',
        ];
    }
}
