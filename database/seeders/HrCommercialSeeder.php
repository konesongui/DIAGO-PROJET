<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Entreprise;
use App\Models\Lead;
use Illuminate\Database\Seeder;

class HrCommercialSeeder extends Seeder
{
    public function run(): void
    {
        // Rattache la démo à l'entreprise de démonstration plutôt qu'à un
        // identifiant codé en dur, qui n'existe pas forcément.
        $entrepriseId = Entreprise::where('slug', 'diagoma-demo')->value('id')
            ?? Entreprise::orderBy('id')->value('id');

        if (! $entrepriseId) {
            $this->command?->warn('Aucune entreprise trouvée, HrCommercialSeeder ignoré.');

            return;
        }

        $employees = [
            ['full_name' => 'Mariam Koné', 'email' => 'mariam.kone@diagoma.local', 'position' => 'Directrice RH', 'department' => 'RH', 'status' => 'active', 'monthly_salary' => 420000, 'hire_date' => '2024-01-10'],
            ['full_name' => 'Jean N’Dri', 'email' => 'jean.ndri@diagoma.local', 'position' => 'Ingénieur financier', 'department' => 'Finance', 'status' => 'active', 'monthly_salary' => 390000, 'hire_date' => '2023-11-18'],
            ['full_name' => 'Awa Diop', 'email' => 'awa.diop@diagoma.local', 'position' => 'Chargée commerciale', 'department' => 'Commercial', 'status' => 'active', 'monthly_salary' => 360000, 'hire_date' => '2025-02-12'],
            ['full_name' => 'Ibrahim Touré', 'email' => 'ibrahim.toure@diagoma.local', 'position' => 'Support client', 'department' => 'Support', 'status' => 'on_leave', 'monthly_salary' => 300000, 'hire_date' => '2024-04-20'],
        ];

        foreach ($employees as $employee) {
            Employee::updateOrCreate(['email' => $employee['email']], $employee + ['entreprise_id' => $entrepriseId]);
        }

        $leads = [
            ['company_name' => 'Société Immobilière Kora', 'contact_name' => 'Yves Koffi', 'email' => 'yves@kora.ci', 'phone' => '+225 01234567', 'status' => 'qualified', 'value' => 1450000, 'source' => 'site', 'next_step_at' => now()->addDays(2)],
            ['company_name' => 'Médicalis', 'contact_name' => 'Sophie Nguessan', 'email' => 'sophie@medicalis.ci', 'phone' => '+225 01478596', 'status' => 'proposal', 'value' => 980000, 'source' => 'réseaux', 'next_step_at' => now()->addDays(4)],
            ['company_name' => 'Logistics Plus', 'contact_name' => 'Kouassi Dodo', 'email' => 'kdodo@logisticsplus.ci', 'phone' => '+225 07654321', 'status' => 'new', 'value' => 520000, 'source' => 'campagne', 'next_step_at' => now()->addDays(6)],
        ];

        foreach ($leads as $lead) {
            Lead::updateOrCreate(['email' => $lead['email']], $lead + ['entreprise_id' => $entrepriseId]);
        }
    }
}
