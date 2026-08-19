<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'phone_number' => '+13001234567',
            'email' => 'test@example.com',
            'login_pin_hash' => \Illuminate\Support\Facades\Hash::make('123456'),
            'transaction_pin_hash' => \Illuminate\Support\Facades\Hash::make('1234'),
        ]);
    }
}
