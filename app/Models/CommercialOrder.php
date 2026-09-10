<?php
namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CommercialOrder extends Model
{
    use BelongsToEntreprise;
    protected $fillable = ['entreprise_id','created_by_user_id','quote_id','client_name','customer_order_code','lines','total_ttc','total_ht','tax_amount','tax_rate','tax_regime','tax_rate_id','currency','status'];
    protected $casts = ['lines'=>'array','total_ttc'=>'decimal:2','total_ht'=>'decimal:2','tax_amount'=>'decimal:2','tax_rate'=>'decimal:3'];
    public function delivery(): HasOne { return $this->hasOne(CommercialDelivery::class, 'order_id'); }
    public function quote(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(CommercialQuote::class, 'quote_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
