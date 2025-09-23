<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RequestItem>
 */
class RequestItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_name' => $this->faker->words(3, true),
            'reason' => $this->faker->sentence(),
            'category' => $this->faker->randomElement(['IT Equipment', 'Office Furniture', 'Office Supplies']),
            'quantity' => $this->faker->numberBetween(1, 10),
            'estimated_cost' => $this->faker->numberBetween(100000, 10000000),
            'user_id' => User::factory(),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
        ];
    }
}
