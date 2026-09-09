<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'super_admin', 'label' => 'Super administrateur'],
            ['name' => 'admin', 'label' => 'Administrateur'],
            ['name' => 'manager', 'label' => 'Manager'],
            ['name' => 'teacher', 'label' => 'Enseignant'],
            ['name' => 'student', 'label' => 'Étudiant'],
            ['name' => 'parent', 'label' => 'Parent'],
            ['name' => 'standard_user', 'label' => 'Utilisateur Standard'],
            ['name' => 'accountant', 'label' => 'Accountant'],
            ['name' => 'receptionist', 'label' => 'Receptionist'],
            ['name' => 'secretariat', 'label' => 'SECRETARIAT'],
            ['name' => 'hr_manager', 'label' => 'DRH'],
            ['name' => 'sales', 'label' => 'Commercial'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                ['label' => $role['label']]
            );
        }
    }
}
