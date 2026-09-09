<?php

namespace Database\Seeders;

use App\Models\AccountEntry;
use Illuminate\Database\Seeder;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            ['reference' => 'EV-2026-001', 'label' => 'Vente de services', 'account_code' => '701000', 'type' => 'credit', 'amount' => 325000, 'currency' => 'XOF', 'posted_at' => '2026-09-01', 'status' => 'validated'],
            ['reference' => 'DP-2026-002', 'label' => 'Paiement fournisseur', 'account_code' => '401000', 'type' => 'debit', 'amount' => 185000, 'currency' => 'XOF', 'posted_at' => '2026-09-02', 'status' => 'validated'],
            ['reference' => 'EV-2026-003', 'label' => 'Facture client A', 'account_code' => '411000', 'type' => 'credit', 'amount' => 420000, 'currency' => 'XOF', 'posted_at' => '2026-09-03', 'status' => 'pending'],
            ['reference' => 'DP-2026-004', 'label' => 'Salaire personnel', 'account_code' => '646000', 'type' => 'debit', 'amount' => 95000, 'currency' => 'XOF', 'posted_at' => '2026-09-03', 'status' => 'validated'],
        ];

        foreach ($entries as $entry) {
            AccountEntry::updateOrCreate(['reference' => $entry['reference']], $entry);
        }
    }
}
