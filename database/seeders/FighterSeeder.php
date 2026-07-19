<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Fighter\FighterProvisioner;
use Illuminate\Database\Seeder;

class FighterSeeder extends Seeder
{
    /**
     * Seed a demo Fighter user, provisioned exactly like the admin create flow
     * (dedicated "Fighter: {name}" sales-source segment linked on the user).
     */
    public function run(): void
    {
        $provisioner = app(FighterProvisioner::class);

        $fighters = [
            ['name' => 'Farid Fighter', 'email' => 'fighter@example.com', 'phone' => '60111000001'],
        ];

        foreach ($fighters as $data) {
            $fighter = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => 'password',
                    'role' => 'fighter',
                    'status' => 'active',
                ]
            );

            if ($fighter->role !== 'fighter') {
                $fighter->forceFill(['role' => 'fighter'])->save();
            }

            $source = $provisioner->ensureSalesSource($fighter);

            $this->command->info("Created/Updated fighter: {$fighter->name} ({$fighter->email}) → segment \"{$source->name}\"");
        }
    }
}
