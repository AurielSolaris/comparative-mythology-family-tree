<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Entity;
use App\Models\Religion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entity>
 */
class EntityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'religion_id' => Religion::factory(),
            'name' => fake()->unique()->firstName(),
            'gender' => fake()->randomElement(Gender::cases()),
            'region' => fake()->country(),
            'origin' => fake()->sentence(),
            'aliases' => [fake()->firstName(), fake()->firstName()],
        ];
    }
}
