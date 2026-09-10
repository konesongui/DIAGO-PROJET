<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class LedgerAccount extends Model
{
    use BelongsToEntreprise;

    /** Roles reconnus : ils permettent de retrouver un compte sans coder son numero. */
    public const ROLE_CUSTOMERS = 'customers';
    public const ROLE_SUPPLIERS = 'suppliers';
    public const ROLE_VAT_COLLECTED = 'vat_collected';
    public const ROLE_VAT_DEDUCTIBLE = 'vat_deductible';
    public const ROLE_VAT_DUE = 'vat_due';
    public const ROLE_CASH = 'cash';
    public const ROLE_BANK = 'bank';
    public const ROLE_SALES = 'sales';
    public const ROLE_PURCHASES = 'purchases';

    protected $fillable = ['entreprise_id', 'code', 'name', 'type', 'role', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class, 'ledger_account_id');
    }

    /** Un compte d'actif ou de charge augmente au debit. */
    public function increasesOnDebit(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true);
    }
}
