<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use App\Models\Concerns\HasTaxLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialProforma extends Model
{
    use BelongsToEntreprise;
    use HasTaxLabel;

    protected $fillable = [
        'entreprise_id', 'reference', 'client_id', 'client_name', 'client_phone', 'creation_date', 'due_date',
        'payment_terms', 'delivery_terms', 'delivery_location', 'payment_method', 'subject',
        'total_ht', 'total_discount', 'net_ht', 'tax_amount', 'tax_rate', 'tax_rate_id', 'tax_regime', 'currency', 'total_ttc', 'status',
    ];

    protected $casts = [
        'creation_date' => 'date',
        'due_date' => 'date',
        'total_ht' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'net_ht' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'total_ttc' => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(CommercialProformaLine::class, 'proforma_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(CommercialClient::class, 'client_id');
    }

    /** Lignes au format des documents imprimés (print/item-lines), avec la remise propre à chaque ligne. */
    public function documentLines(): array
    {
        return $this->lines->map(fn (CommercialProformaLine $line) => [
            'item_name' => $line->item_name,
            'category_name' => collect([$line->type === 'service' ? 'Service' : 'Produit', $line->category])->filter()->implode(' · '),
            'unit' => $line->unit,
            'quantity' => (float) $line->quantity,
            'unit_price' => (float) $line->unit_price,
            'discount_label' => (float) $line->discount > 0
                ? ($line->discount_type === 'amount' ? '− ' . money((float) $line->discount) : rtrim(rtrim(number_format((float) $line->discount, 2, ',', ''), '0'), ',') . ' %')
                : null,
            'line_total' => (float) $line->line_total,
        ])->all();
    }

    /** État lisible : la date limite passée l'emporte sur l'envoi. */
    public function state(): string
    {
        if ($this->due_date && $this->due_date->lt(today())) {
            return 'expired';
        }

        return $this->status === 'sent' ? 'sent' : 'draft';
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> libellé, ton du badge, icône, libellé d'onglet */
    public static function states(): array
    {
        return [
            'draft' => ['Brouillon', 'neutral', 'bi-pencil', 'Brouillons'],
            'sent' => ['Envoyée', 'success', 'bi-send-check', 'Envoyées'],
            'expired' => ['Expirée', 'danger', 'bi-calendar-x', 'Expirées'],
        ];
    }
}
