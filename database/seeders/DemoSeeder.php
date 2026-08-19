<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DebtStatus;
use App\Enums\LedgerType;
use App\Enums\ReminderSchedule;
use App\Enums\ReminderType;
use App\Enums\TransactionType;
use App\Models\Contact;
use App\Models\Debt;
use App\Models\Reminder;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    /**
     * Seed the application with demo data.
     */
    public function run(): void
    {
        // Create demo user
        $user = User::create([
            'name' => 'Demo User',
            'email' => 'demo@creditbook.app',
            'password' => Hash::make('password'),
            'default_currency' => 'NGN',
            'email_verified_at' => now(),
        ]);

        // Create contacts
        $contacts = collect([
            ['full_name' => 'John Adeyemi', 'nickname' => 'John', 'phone_number' => '+2348012345678'],
            ['full_name' => 'Sarah Okonkwo', 'nickname' => 'Sarah', 'phone_number' => '+2348023456789'],
            ['full_name' => 'Michael Balogun', 'nickname' => 'Mike', 'phone_number' => '+2348034567890'],
            ['full_name' => 'Amina Mohammed', 'nickname' => 'Amina', 'phone_number' => '+2348045678901'],
            ['full_name' => 'David Okafor', 'nickname' => 'Dave', 'phone_number' => '+2348056789012'],
            ['full_name' => 'Chioma Nwosu', 'nickname' => 'Chi', 'phone_number' => '+2348067890123'],
        ])->map(function ($data) use ($user) {
            return Contact::create([
                'user_id' => $user->id,
                'full_name' => $data['full_name'],
                'nickname' => $data['nickname'],
                'phone_number' => $data['phone_number'],
                'is_favorite' => rand(0, 1) === 1,
                'usage_count' => rand(1, 15),
                'client_id' => Str::uuid()->toString(),
            ]);
        });

        // Create debts with transactions
        $debts = [
            // John owes me ₦20,000 — partially paid
            [
                'contact' => $contacts[0],
                'type' => TransactionType::THEY_OWE_ME,
                'amount' => 20000,
                'paid' => 5000,
                'note' => 'Borrowed for car repair',
                'category' => 'Personal',
                'due_date' => now()->addDays(14),
                'status' => DebtStatus::PARTIALLY_PAID,
            ],
            // I owe Sarah ₦50,000 — unpaid
            [
                'contact' => $contacts[1],
                'type' => TransactionType::I_OWE_THEM,
                'amount' => 50000,
                'paid' => 0,
                'note' => 'Emergency medical bills',
                'category' => 'Medical',
                'due_date' => now()->addDays(30),
                'status' => DebtStatus::UNPAID,
            ],
            // Mike owes me ₦100,000 — unpaid, overdue
            [
                'contact' => $contacts[2],
                'type' => TransactionType::THEY_OWE_ME,
                'amount' => 100000,
                'paid' => 0,
                'note' => 'Business investment loan',
                'category' => 'Business',
                'due_date' => now()->subDays(7),
                'status' => DebtStatus::OVERDUE,
            ],
            // Amina owes me ₦15,000 — fully paid
            [
                'contact' => $contacts[3],
                'type' => TransactionType::THEY_OWE_ME,
                'amount' => 15000,
                'paid' => 15000,
                'note' => 'Rent contribution',
                'category' => 'Rent',
                'due_date' => null,
                'status' => DebtStatus::PAID,
            ],
            // I owe Dave ₦30,000 — partially paid
            [
                'contact' => $contacts[4],
                'type' => TransactionType::I_OWE_THEM,
                'amount' => 30000,
                'paid' => 10000,
                'note' => 'School fees loan',
                'category' => 'Education',
                'due_date' => now()->addDays(60),
                'status' => DebtStatus::PARTIALLY_PAID,
            ],
            // Chioma owes me ₦8,000 — unpaid
            [
                'contact' => $contacts[5],
                'type' => TransactionType::THEY_OWE_ME,
                'amount' => 8000,
                'paid' => 0,
                'note' => 'Birthday party contribution',
                'category' => 'Personal',
                'due_date' => now()->addDays(5),
                'status' => DebtStatus::UNPAID,
            ],
        ];

        foreach ($debts as $debtData) {
            $clientId = Str::uuid()->toString();

            $debt = Debt::create([
                'user_id' => $user->id,
                'contact_id' => $debtData['contact']->id,
                'transaction_type' => $debtData['type'],
                'original_amount' => $debtData['amount'],
                'paid_amount' => $debtData['paid'],
                'currency' => 'NGN',
                'note' => $debtData['note'],
                'category' => $debtData['category'],
                'status' => $debtData['status'],
                'due_date' => $debtData['due_date'],
                'reminder_enabled' => $debtData['due_date'] !== null,
                'client_id' => $clientId,
            ]);

            // Initial transaction
            Transaction::create([
                'debt_id' => $debt->id,
                'user_id' => $user->id,
                'type' => LedgerType::INITIAL,
                'amount' => $debtData['amount'],
                'note' => 'Initial debt recorded',
                'client_id' => Str::uuid()->toString(),
            ]);

            // Payment transactions if any
            if ($debtData['paid'] > 0) {
                Transaction::create([
                    'debt_id' => $debt->id,
                    'user_id' => $user->id,
                    'type' => LedgerType::PAYMENT,
                    'amount' => $debtData['paid'],
                    'note' => 'Payment received',
                    'client_id' => Str::uuid()->toString(),
                ]);
            }

            // Create reminders for debts with due dates
            if ($debt->due_date && $debt->status !== DebtStatus::PAID) {
                Reminder::create([
                    'debt_id' => $debt->id,
                    'user_id' => $user->id,
                    'remind_at' => $debt->due_date->subDay(),
                    'type' => ReminderType::DUE_DATE,
                    'schedule' => ReminderSchedule::ONE_DAY_BEFORE,
                    'is_sent' => false,
                    'client_id' => Str::uuid()->toString(),
                ]);
            }
        }

        $this->command->info('Demo data seeded successfully!');
        $this->command->info('Login: demo@creditbook.app / password');
    }
}
