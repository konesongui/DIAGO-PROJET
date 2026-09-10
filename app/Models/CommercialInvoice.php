<?php
namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CommercialInvoice extends Model
{
    use BelongsToEntreprise;
    protected $fillable = ['entreprise_id','created_by_user_id','delivery_id','client_name','amount','total_ht','tax_amount','tax_rate','tax_regime','tax_rate_id','currency','paid_amount','issued_at','credited_amount','payment_method','cash_account_id','bank_account_id','status','fne_status','fne_reference','fne_token','fne_balance_sticker','fne_response','fne_error','fne_certified_at'];
    protected $casts = ['amount'=>'decimal:2','issued_at'=>'datetime','credited_amount'=>'decimal:2','total_ht'=>'decimal:2','tax_amount'=>'decimal:2','tax_rate'=>'decimal:3','paid_amount'=>'decimal:2','fne_balance_sticker'=>'decimal:2','fne_response'=>'array','fne_certified_at'=>'datetime'];
    public function delivery(): BelongsTo { return $this->belongsTo(CommercialDelivery::class, 'delivery_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
