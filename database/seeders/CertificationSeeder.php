<?php

namespace Database\Seeders;

use App\Models\Certification;
use Illuminate\Database\Seeder;

class CertificationSeeder extends Seeder
{
    public function run(): void
    {
        $certifications = [
            ['name' => 'First Aid & CPR', 'issuing_body' => 'Red Crescent Malaysia', 'validity_months' => 24],
            ['name' => 'Fire Safety Training', 'issuing_body' => 'BOMBA', 'validity_months' => 12],
            ['name' => 'Occupational Safety & Health', 'issuing_body' => 'DOSH', 'validity_months' => 36],
            ['name' => 'ISO 9001 Internal Auditor', 'issuing_body' => 'SIRIM', 'validity_months' => 36],
            ['name' => 'Food Handler Certificate', 'issuing_body' => 'MOH', 'validity_months' => 24],
        ];

        foreach ($certifications as $cert) {
            Certification::updateOrCreate(
                ['name' => $cert['name']],
                array_merge($cert, ['is_active' => true])
            );
        }
    }
}
