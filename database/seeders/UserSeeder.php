<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Administrateur
        User::create([
            'name' => 'Administrateur MMS',
            'email' => 'admin@crem.fr',
            'password' => Hash::make('password'),
            'role' => UserRole::ADMINISTRATEUR,
            'admin_access' => true,
            'email_verified_at' => now(),
        ]);

        // Documentaliste
        User::create([
            'name' => 'Marie Dupont',
            'email' => 'marie.dupont@crem.fr',
            'password' => Hash::make('password'),
            'role' => UserRole::DOCUMENTALISTE,
            'admin_access' => true,
            'email_verified_at' => now(),
        ]);

        // Chercheur
        User::create([
            'name' => 'Jean Martin',
            'email' => 'jean.martin@crem.fr',
            'password' => Hash::make('password'),
            'role' => UserRole::CHERCHEUR,
            'admin_access' => true,
            'email_verified_at' => now(),
        ]);

        // Autres chercheurs pour les tests
        User::factory(5)->create([
            'role' => UserRole::CHERCHEUR,
            'admin_access' => false,
        ]);
    }
}
