<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\CommercialClient;
use App\Models\CommercialInvoice;
use App\Models\CommercialSupplier;
use App\Models\CustomInvoice;
use App\Models\Employee;
use App\Models\Entreprise;
use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Recherche globale de la barre du haut.
 *
 * Chaque groupe n'est interrogé que si l'utilisateur a le droit de consulter
 * le module concerné et si la rubrique est activée pour son entreprise ; les
 * résultats restent limités à l'entreprise de l'utilisateur.
 */
class GlobalSearchService
{
    public const MIN_LENGTH = 2;

    private const PER_GROUP = 5;

    /**
     * @return array<int, array{key: string, label: string, icon: string, color: string, items: array<int, array{title: string, subtitle: string, url: string}>}>
     */
    public function search(User $user, string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < self::MIN_LENGTH) {
            return [];
        }

        $pattern = '%' . addcslashes($term, '%_\\') . '%';

        if ($user->hasRole('super_admin')) {
            return $this->groups([
                ['entreprises', 'Entreprises', 'bi-buildings', 'indigo', fn () => $this->entreprises($pattern)],
            ]);
        }

        $tenant = $user->entreprise_id;
        $rubriques = array_merge(
            ['commercial' => true, 'comptabilite' => true, 'rh' => true],
            data_get($user->entreprise?->settings ?? [], 'enabled_rubriques', [])
        );
        $can = fn (string $rubrique, string $permission) => ! empty($rubriques[$rubrique]) && $user->hasPermission($permission);

        return $this->groups([
            $can('rh', 'hr') ? ['employees', 'Employés', 'bi-person', 'indigo', fn () => $this->employees($tenant, $pattern)] : null,
            $can('commercial', 'commercial') ? ['clients', 'Clients', 'bi-people', 'blue', fn () => $this->clients($tenant, $pattern)] : null,
            $can('commercial', 'commercial') ? ['suppliers', 'Fournisseurs', 'bi-truck', 'teal', fn () => $this->suppliers($tenant, $pattern)] : null,
            $can('commercial', 'commercial') ? ['sales', 'Factures clients', 'bi-receipt', 'green', fn () => $this->salesInvoices($tenant, $pattern)] : null,
            $can('comptabilite', 'accounting') ? ['purchases', 'Factures fournisseurs', 'bi-file-earmark-text', 'orange', fn () => $this->supplierInvoices($tenant, $pattern)] : null,
            $can('comptabilite', 'accounting') ? ['treasury', 'Caisses et banques', 'bi-bank', 'purple', fn () => $this->treasuryAccounts($tenant, $pattern)] : null,
        ]);
    }

    /** Exécute les groupes autorisés et ne garde que ceux qui ont des résultats. */
    private function groups(array $definitions): array
    {
        return collect($definitions)
            ->filter()
            ->map(fn (array $group) => [
                'key' => $group[0],
                'label' => $group[1],
                'icon' => $group[2],
                'color' => $group[3],
                'items' => $group[4]()->take(self::PER_GROUP)->values()->all(),
            ])
            ->filter(fn (array $group) => $group['items'] !== [])
            ->values()
            ->all();
    }

    /** Condition « un des champs contient le terme », insensible à la casse. */
    private function matching(Builder $query, array $columns, string $pattern): Builder
    {
        return $query->where(function (Builder $where) use ($columns, $pattern) {
            foreach ($columns as $column) {
                $where->orWhere($column, 'ilike', $pattern);
            }
        });
    }

    private function entreprises(string $pattern): Collection
    {
        return $this->matching(Entreprise::query(), ['name', 'slug'], $pattern)
            ->orderBy('name')->limit(self::PER_GROUP)->get()
            ->map(fn (Entreprise $entreprise) => [
                'title' => $entreprise->name,
                'subtitle' => $entreprise->is_active ? 'Compte actif' : 'Compte désactivé',
                'url' => route('console.entreprises.show', $entreprise),
            ]);
    }

    private function employees(?int $tenant, string $pattern): Collection
    {
        return $this->matching(Employee::where('entreprise_id', $tenant), ['full_name', 'matricule', 'email', 'phone'], $pattern)
            ->orderBy('full_name')->limit(self::PER_GROUP)->get()
            ->map(fn (Employee $employee) => [
                'title' => $employee->full_name,
                'subtitle' => collect([$employee->position, $employee->matricule])->filter()->implode(' · ') ?: 'Employé',
                'url' => route('admin.rh.employees.show', $employee),
            ]);
    }

    private function clients(?int $tenant, string $pattern): Collection
    {
        return $this->matching(CommercialClient::where('entreprise_id', $tenant), ['name', 'responsible_name', 'email', 'phone', 'tax_id'], $pattern)
            ->orderBy('name')->limit(self::PER_GROUP)->get()
            ->map(fn (CommercialClient $client) => [
                'title' => $client->name,
                'subtitle' => collect([$client->email, $client->phone, $client->city])->filter()->implode(' · ') ?: 'Client',
                'url' => route('admin.commercial.module', 'clients'),
            ]);
    }

    private function suppliers(?int $tenant, string $pattern): Collection
    {
        return $this->matching(CommercialSupplier::where('entreprise_id', $tenant), ['name', 'responsible_name', 'email', 'phone', 'tax_id'], $pattern)
            ->orderBy('name')->limit(self::PER_GROUP)->get()
            ->map(fn (CommercialSupplier $supplier) => [
                'title' => $supplier->name,
                'subtitle' => collect([$supplier->email, $supplier->phone])->filter()->implode(' · ') ?: 'Fournisseur',
                'url' => route('admin.commercial.module', 'fournisseurs'),
            ]);
    }

    /** Factures personnalisées (fiche dédiée) puis factures issues des livraisons. */
    private function salesInvoices(?int $tenant, string $pattern): Collection
    {
        $custom = $this->matching(CustomInvoice::where('entreprise_id', $tenant), ['reference', 'client_name', 'subject'], $pattern)
            ->latest('id')->limit(self::PER_GROUP)->get()
            ->map(fn (CustomInvoice $invoice) => [
                'title' => $invoice->reference ?: 'Facture #' . $invoice->id,
                'subtitle' => collect([$invoice->client_name, money((float) $invoice->total_ttc)])->filter()->implode(' · '),
                'url' => route('admin.commercial.custom-invoice.show', $invoice),
            ]);

        $delivered = $this->matching(CommercialInvoice::where('entreprise_id', $tenant), ['client_name', 'fne_reference'], $pattern)
            ->latest('id')->limit(self::PER_GROUP)->get()
            ->map(fn (CommercialInvoice $invoice) => [
                'title' => 'Facture de livraison #' . $invoice->id,
                'subtitle' => collect([$invoice->client_name, money((float) $invoice->amount)])->filter()->implode(' · '),
                'url' => route('admin.commercial.module', 'factures'),
            ]);

        return $custom->concat($delivered);
    }

    private function supplierInvoices(?int $tenant, string $pattern): Collection
    {
        return $this->matching(SupplierInvoice::where('entreprise_id', $tenant), ['invoice_number', 'supplier_name', 'supplier_tax_id'], $pattern)
            ->latest('invoice_date')->limit(self::PER_GROUP)->get()
            ->map(fn (SupplierInvoice $invoice) => [
                'title' => $invoice->invoice_number ?: 'Facture fournisseur #' . $invoice->id,
                'subtitle' => collect([$invoice->supplier_name, money((float) $invoice->total_amount)])->filter()->implode(' · '),
                'url' => route('admin.comptabilite.supplierInvoices.show', $invoice),
            ]);
    }

    private function treasuryAccounts(?int $tenant, string $pattern): Collection
    {
        $cash = $this->matching(CashAccount::where('entreprise_id', $tenant), ['name'], $pattern)
            ->orderBy('name')->limit(self::PER_GROUP)->get()
            ->map(fn (CashAccount $account) => [
                'title' => $account->name,
                'subtitle' => 'Caisse · ' . money((float) $account->balance),
                'url' => route('admin.comptabilite.caisses'),
            ]);

        $banks = $this->matching(BankAccount::where('entreprise_id', $tenant), ['name', 'bank_name', 'account_number'], $pattern)
            ->orderBy('name')->limit(self::PER_GROUP)->get()
            ->map(fn (BankAccount $account) => [
                'title' => $account->name,
                'subtitle' => collect(['Banque', $account->account_number, money((float) $account->current_balance)])->filter()->implode(' · '),
                'url' => route('admin.comptabilite.banques'),
            ]);

        return $cash->concat($banks);
    }
}
