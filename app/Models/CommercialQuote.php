<?php
namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CommercialQuote extends Model
{
    use BelongsToEntreprise;
    protected $fillable = ['entreprise_id','created_by_user_id','reference','client_id','client_name','quote_date','due_date','payment_terms','delivery_terms','delivery_location','payment_method','subject','customer_order_code','total_ht','total_discount','net_ht','tax_rate','tax_amount','total_ttc','lines','status'];
    protected $casts = ['quote_date'=>'date','due_date'=>'date','lines'=>'array','total_ht'=>'decimal:2','total_discount'=>'decimal:2','net_ht'=>'decimal:2','tax_rate'=>'decimal:2','tax_amount'=>'decimal:2','total_ttc'=>'decimal:2'];
    public function order(): HasOne { return $this->hasOne(CommercialOrder::class, 'quote_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
