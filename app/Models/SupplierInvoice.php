<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoice extends Model
{
    use BelongsToEntreprise;
    protected $fillable = [
        'entreprise_id',
        'invoice_number',
        'supplier_name',
        'supplier_tax_id',
        'invoice_date',
        'due_date',
        'subtotal',
        'total_ht',
        'tax_amount',
        'tax_regime',
        'total_amount',
        'currency',
        'status',
        'file_path',
        'original_filename',
        'extracted_data',
        'raw_text',
        'fne_status', 'fne_reference', 'fne_token', 'fne_balance_sticker',
        'fne_response', 'fne_error', 'fne_certified_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'total_ht' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'extracted_data' => 'array',
        'fne_balance_sticker' => 'decimal:2',
        'fne_response' => 'array',
        'fne_certified_at' => 'datetime',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }
}
