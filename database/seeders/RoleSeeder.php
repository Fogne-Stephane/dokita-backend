<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Créer les 3 rôles de DOKITA
        Role::firstOrCreate(['name' => 'patient',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'doctor',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',    'guard_name' => 'web']);

        $this->command->info('✅ Rôles créés : patient, doctor, admin');
    }
}