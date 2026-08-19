<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'full_name' => fake()->name(),
            'nickname' => fake()->optional(0.5)->firstName(),
            'phone_number' => fake()->optional(0.7)->phoneNumber(),
            'profile_picture' => null,
            'note' => fake()->optional(0.3)->sentence(),
            'is_favorite' => fake()->boolean(20),
            'usage_count' => fake()->numberBetween(0, 20),
            'client_id' => Str::uuid()->toString(),
        ];
    }

    public function favorite(): static
    {
        return $this->state(fn () => [
            'is_favorite' => true,
        ]);
    }
}
