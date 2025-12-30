<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
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
            'google_id' => fake()->unique()->numerify('####################'),
            'picture' => 'https://via.placeholder.com/150',
            'provider' => 'google',
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user is from a specific provider.
     */
    public function provider(string $provider): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => $provider,
        ]);
    }
}
