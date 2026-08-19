<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DebtStatus;
use App\Enums\TransactionType;
use App\Models\Contact;
use App\Models\Debt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DebtFactory extends Factory
{
    protected $model = Debt::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 1000, 500000);

        return [
            'user_id' => User::factory(),
            'contact_id' => Contact::factory(),
            'transaction_type' => fake()->randomElement(TransactionType::cases()),
            'original_amount' => $amount,
            'paid_amount' => 0,
            'currency' => 'NGN',
            'note' => fake()->optional(0.5)->sentence(),
            'category' => fake()->optional(0.4)->randomElement(['Personal', 'Business', 'Medical', 'Education', 'Rent']),
            'status' => DebtStatus::UNPAID,
            'due_date' => fake()->optional(0.6)->dateTimeBetween('now', '+3 months'),
            'reminder_enabled' => fake()->boolean(30),
            'client_id' => Str::uuid()->toString(),
        ];
    }

    public function theyOweMe(): static
    {
        return $this->state(fn () => [
            'transaction_type' => TransactionType::THEY_OWE_ME,
        ]);
    }

    public function iOweThem(): static
    {
        return $this->state(fn () => [
            'transaction_type' => TransactionType::I_OWE_THEM,
        ]);
    }

    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'paid_amount' => $attributes['original_amount'],
                'status' => DebtStatus::PAID,
            ];
        });
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'due_date' => now()->subDays(7),
            'status' => DebtStatus::OVERDUE,
        ]);
    }
}
