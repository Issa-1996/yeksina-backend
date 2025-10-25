<?php

namespace App\Console\Commands;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateTestDrivers extends Command
{
    protected $signature = 'drivers:create-test {count=3}';
    protected $description = 'Créer des drivers de test pour le matching';

    public function handle()
    {
        $count = $this->argument('count');
        $this->info("🚗 Création de {$count} drivers de test...");

        $testDrivers = [
            [
                'first_name' => 'Pape',
                'last_name' => 'Diop',
                'email' => 'pape.diop@test.com',
                'phone' => '771234567',
                'vehicle_type' => 'moto',
                'current_lat' => 14.7700,
                'current_lng' => -17.4700,
            ],
            [
                'first_name' => 'Moussa',
                'last_name' => 'Sarr',
                'email' => 'moussa.sarr@test.com',
                'phone' => '772345678',
                'vehicle_type' => 'voiture',
                'current_lat' => 14.7600,
                'current_lng' => -17.4600,
            ],
            [
                'first_name' => 'Aminata',
                'last_name' => 'Diallo',
                'email' => 'aminata.diallo@test.com',
                'phone' => '773456789',
                'vehicle_type' => 'moto',
                'current_lat' => 14.7500,
                'current_lng' => -17.4500,
            ]
        ];

        $createdCount = 0;

        for ($i = 0; $i < min($count, count($testDrivers)); $i++) {
            $driverData = $testDrivers[$i];

            // Vérifier si le driver existe déjà
            $existingDriver = Driver::where('phone', $driverData['phone'])->first();
            if ($existingDriver) {
                $this->line("⚠️  Driver existe déjà: {$driverData['first_name']} {$driverData['last_name']}");
                continue;
            }

            try {
                \DB::beginTransaction();

                // Créer le driver
                $driver = Driver::create([
                    'first_name' => $driverData['first_name'],
                    'last_name' => $driverData['last_name'],
                    'birth_date' => '1990-01-01',
                    'address' => 'Dakar, Senegal',
                    'phone' => $driverData['phone'],
                    'cni_photo_path' => 'test/cni.jpg',
                    'vehicle_type' => $driverData['vehicle_type'],
                    'license_plate' => 'DK-TEST-' . ($i + 1),
                    'is_online' => true,
                    'is_available' => true,
                    'is_approved' => true,
                    'current_lat' => $driverData['current_lat'],
                    'current_lng' => $driverData['current_lng'],
                    'last_location_update' => now(),
                    'current_balance' => 0,
                    'total_earnings' => 0,
                    'total_deliveries' => 0,
                    'average_rating' => 4.5 + ($i * 0.1), // Notes différentes: 4.5, 4.6, 4.7
                ]);

                // Créer l'utilisateur associé
                $user = User::create([
                    'email' => $driverData['email'],
                    'password' => Hash::make('password123'),
                    'userable_type' => Driver::class,
                    'userable_id' => $driver->id,
                    'role' => 'driver',
                ]);

                \DB::commit();

                $this->line("✅ Driver créé: {$driver->full_name} - Note: {$driver->average_rating} - Position: {$driver->current_lat}, {$driver->current_lng}");
                $createdCount++;
            } catch (\Exception $e) {
                \DB::rollBack();
                $this->error("❌ Erreur création driver {$driverData['first_name']}: " . $e->getMessage());
            }
        }

        $this->info("🎯 {$createdCount} drivers de test créés avec succès!");
        $this->line("📱 Identifiants de test:");
        $this->line("   Email: [pape.diop@test.com, moussa.sarr@test.com, aminata.diallo@test.com]");
        $this->line("   Mot de passe: password123");

        return 0;
    }
}
