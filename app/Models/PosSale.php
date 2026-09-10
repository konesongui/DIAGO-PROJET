<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;

class PosSale extends Model
{
    use BelongsToEntreprise;
    protected $fillable = ['entreprise_id', 'client_id', 'client_name', 'lines', 'total', 'total_ht','tax_amount','tax_rate','tax_regime','tax_rate_id','currency', 'paid_amount', 'change_amount', 'payment_method', 'cash_account_id', 'bank_account_id', 'status'];
    protected $casts = ['lines' => 'array', 'total' => 'decimal:2','total_ht'=>'decimal:2','tax_amount'=>'decimal:2','tax_rate'=>'decimal:3', 'paid_amount' => 'decimal:2', 'change_amount' => 'decimal:2'];
}
