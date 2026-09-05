<?php

namespace Database\Seeders;

use App\Models\Buyer;
use App\Models\Producer;
use App\Models\RegisteredProducer;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaultPassword = 'HarborAI123!';

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'contact_number' => '09170000001',
                'user_type' => 'Admin',
                'password_hash' => Hash::make($defaultPassword),
            ]
        );

        $buyer = User::updateOrCreate(
            ['email' => 'institutionalbuyer@gmail.com'],
            [
                'first_name' => 'Institutional',
                'last_name' => 'Buyer',
                'contact_number' => '09170000002',
                'user_type' => 'Buyer',
                'password_hash' => Hash::make($defaultPassword),
            ]
        );

        Buyer::updateOrCreate(
            ['user_id' => $buyer->user_id],
            [
                'organization_name' => 'Institutional Buyer',
                'buyer_type' => 'Institution',
                'verification_status' => 'Verified',
                'contact_person' => 'Institutional Buyer',
                'shipping_address' => null,
            ]
        );

        $producerUser = User::updateOrCreate(
            ['email' => 'producer@gmail.com'],
            [
                'first_name' => 'Producer',
                'last_name' => 'Account',
                'contact_number' => '09170000003',
                'user_type' => 'Farmer',
                'password_hash' => Hash::make($defaultPassword),
            ]
        );

        RegisteredProducer::updateOrCreate(
            ['rsbsa_number' => 'RSBSA-1001'],
            [
                'full_name' => 'Producer Account',
                'municipality' => 'Aparri',
                'barangay' => 'Barangay 1',
                'primary_livelihood' => 'Seafood',
                'producer_type' => 'Farmer',
                'status' => 'active',
            ]
        );

        Producer::updateOrCreate(
            ['user_id' => $producerUser->user_id],
            [
                'rsbsa_number' => 'RSBSA-1001',
                'location' => 'Aparri, Cagayan',
                'primary_product_type' => 'Seafood',
                'verification_status' => 'Verified',
                'producer_type' => 'Farmer',
            ]
        );

        $this->command->info('Seeded default accounts. Admin: admin@example.com / HarborAI123! | Buyer: institutionalbuyer@gmail.com / HarborAI123! | Producer: producer@gmail.com / HarborAI123!');
    }
}
