<?php

namespace Database\Seeders;

use App\Models\Agent;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $agents = [
            [
                'agent_code' => 'AGT0001',
                'name' => 'Kedai Buku Al-Hidayah',
                'type' => 'agent',
                'company_name' => 'Al-Hidayah Enterprise',
                'registration_number' => 'SA0123456-A',
                'contact_person' => 'Ahmad bin Abdullah',
                'email' => 'alhidayah@example.com',
                'phone' => '012-3456789',
                'address' => [
                    'street' => 'No. 15, Jalan Masjid India',
                    'city' => 'Kuala Lumpur',
                    'state' => 'Wilayah Persekutuan',
                    'postal_code' => '50100',
                    'country' => 'Malaysia',
                ],
                'payment_terms' => 'Net 30 days',
                'bank_details' => [
                    'bank_name' => 'Maybank',
                    'account_number' => '1234567890',
                    'account_name' => 'Al-Hidayah Enterprise',
                ],
                'is_active' => true,
                'notes' => 'Main distributor for religious books in KL area',
            ],
            [
                'agent_code' => 'AGT0002',
                'name' => 'Pustaka Ilmu Sdn Bhd',
                'type' => 'company',
                'company_name' => 'Pustaka Ilmu Sdn Bhd',
                'registration_number' => '201901012345 (1234567-K)',
                'contact_person' => 'Siti Aminah binti Hassan',
                'email' => 'pustakailmu@example.com',
                'phone' => '03-78901234',
                'address' => [
                    'street' => 'Lot 5, Jalan SS15/4',
                    'city' => 'Subang Jaya',
                    'state' => 'Selangor',
                    'postal_code' => '47500',
                    'country' => 'Malaysia',
                ],
                'payment_terms' => 'Net 14 days',
                'bank_details' => [
                    'bank_name' => 'CIMB Bank',
                    'account_number' => '8012345678',
                    'account_name' => 'Pustaka Ilmu Sdn Bhd',
                ],
                'is_active' => true,
                'notes' => 'Educational books specialist',
            ],
            [
                'agent_code' => 'AGT0003',
                'name' => 'Kedai Buku Nur Iman',
                'type' => 'agent',
                'company_name' => null,
                'registration_number' => null,
                'contact_person' => 'Mohd Faiz bin Ismail',
                'email' => 'nuriman.books@example.com',
                'phone' => '019-8765432',
                'address' => [
                    'street' => 'No. 8, Jalan Sultan Ismail',
                    'city' => 'Kota Bharu',
                    'state' => 'Kelantan',
                    'postal_code' => '15000',
                    'country' => 'Malaysia',
                ],
                'payment_terms' => 'COD',
                'bank_details' => [
                    'bank_name' => 'Bank Islam',
                    'account_number' => '14012345678',
                    'account_name' => 'Mohd Faiz bin Ismail',
                ],
                'is_active' => true,
                'notes' => 'East coast region distributor',
            ],
            [
                'agent_code' => 'AGT0004',
                'name' => 'Perpustakaan Mini Johor',
                'type' => 'agent',
                'company_name' => 'PMJ Enterprise',
                'registration_number' => 'JH0456789-D',
                'contact_person' => 'Lim Wei Ming',
                'email' => 'pmj.johor@example.com',
                'phone' => '07-2345678',
                'address' => [
                    'street' => 'No. 22, Jalan Dhoby',
                    'city' => 'Johor Bahru',
                    'state' => 'Johor',
                    'postal_code' => '80000',
                    'country' => 'Malaysia',
                ],
                'payment_terms' => 'Net 7 days',
                'bank_details' => [
                    'bank_name' => 'Public Bank',
                    'account_number' => '3123456789',
                    'account_name' => 'PMJ Enterprise',
                ],
                'is_active' => true,
                'notes' => 'Southern region main agent',
            ],
            [
                'agent_code' => 'AGT0005',
                'name' => 'Buku Berkualiti Trading',
                'type' => 'company',
                'company_name' => 'Buku Berkualiti Trading Sdn Bhd',
                'registration_number' => '200801023456 (823456-W)',
                'contact_person' => 'Tan Mei Ling',
                'email' => 'bbt.trading@example.com',
                'phone' => '04-2612345',
                'address' => [
                    'street' => '168, Lebuh Chulia',
                    'city' => 'Georgetown',
                    'state' => 'Pulau Pinang',
                    'postal_code' => '10200',
                    'country' => 'Malaysia',
                ],
                'payment_terms' => 'Net 30 days',
                'bank_details' => [
                    'bank_name' => 'Hong Leong Bank',
                    'account_number' => '23412345678',
                    'account_name' => 'Buku Berkualiti Trading Sdn Bhd',
                ],
                'is_active' => true,
                'notes' => 'Northern region wholesaler - large volume orders',
            ],
        ];

        foreach ($agents as $agentData) {
            Agent::updateOrCreate(
                ['agent_code' => $agentData['agent_code']],
                $agentData
            );
        }

        $this->command->info('Created ' . count($agents) . ' agents (kedai buku)');
    }
}
