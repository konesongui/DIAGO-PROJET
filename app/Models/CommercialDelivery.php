<?php
namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CommercialDelivery extends Model
{
    use BelongsToEntreprise;
    protected $fillable = ['entreprise_id','created_by_user_id','order_id','client_name','lines','delivery_type','status'];
    protected $casts = ['lines'=>'array'];
    public function invoice(): HasOne { return $this->hasOne(CommercialInvoice::class, 'delivery_id'); }
    public function order(): BelongsTo { return $this->belongsTo(CommercialOrder::class, 'order_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
