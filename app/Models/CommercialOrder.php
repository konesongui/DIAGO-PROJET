<?php
namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CommercialOrder extends Model
{
    use BelongsToEntreprise;
    protected $fillable = ['entreprise_id','created_by_user_id','quote_id','client_name','customer_order_code','lines','total_ttc','status'];
    protected $casts = ['lines'=>'array','total_ttc'=>'decimal:2'];
    public function delivery(): HasOne { return $this->hasOne(CommercialDelivery::class, 'order_id'); }
    public function quote(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(CommercialQuote::class, 'quote_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
