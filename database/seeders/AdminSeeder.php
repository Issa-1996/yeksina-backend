<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run()
    {
        User::create([
            'email' => 'admin@yeksina.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            // 'userable_type' => null,
            'userable_id' => null,
        ]);

        // Ou créer plusieurs admins
        $admins = [
            [
                'email' => 'superadmin@yeksina.com',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                // 'userable_type' => null,
                'userable_id' => null,
            ]
        ];

        foreach ($admins as $admin) {
            User::create($admin);
        }
    }
}
