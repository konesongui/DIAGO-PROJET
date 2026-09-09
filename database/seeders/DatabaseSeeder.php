<?php

namespace Database\Seeders;

use App\Models\Entreprise;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(AccountingSeeder::class);
        $this->call(HrCommercialSeeder::class);

        $entreprise = Entreprise::firstOrCreate(
            ['slug' => 'diagoma-demo'],
            [
                'name' => 'Diagoma Demo',
                'database_name' => 'diagoma_demo',
                'is_active' => true,
                'settings' => ['locale' => 'fr'],
                'created_by' => null,
            ]
        );

        $adminRole = Role::where('name', 'admin')->first();
        $superAdminRole = Role::where('name', 'super_admin')->first();

        User::firstOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'superadmin@diagoma.local')],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Super Administrateur'),
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
                'entreprise_id' => null,
                'role_id' => $superAdminRole?->id,
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@diagoma.local'],
            [
                'name' => 'Admin Diagoma',
                'password' => Hash::make('password'),
                'entreprise_id' => $entreprise->id,
                'role_id' => $adminRole?->id,
            ]
        );
    }
}
