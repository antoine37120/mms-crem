<?php

namespace Database\Factories;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => fake()->randomElement(UserRole::cases()),
            'admin_access' => fake()->boolean(30), // 30% de chance d'avoir l'accès admin
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Administrateur
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::ADMINISTRATEUR,
            'admin_access' => true,
        ]);
    }

    /**
     * Documentaliste
     */
    public function documentaliste(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::DOCUMENTALISTE,
            'admin_access' => true,
        ]);
    }

    /**
     * Chercheur
     */
    public function chercheur(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::CHERCHEUR,
            'admin_access' => fake()->boolean(20), // 20% des chercheurs ont accès admin
        ]);
    }
}
