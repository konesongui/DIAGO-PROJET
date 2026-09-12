<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEntreprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockExit extends Model
{
    use BelongsToEntreprise;
    protected $fillable = ['entreprise_id', 'stock_inventory_id', 'delivery_id', 'reason', 'note', 'exit_date', 'total_value'];
    protected $casts = ['exit_date' => 'date', 'total_value' => 'decimal:2'];

    public function lines(): HasMany
    {
        return $this->hasMany(StockExitLine::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(CommercialDelivery::class, 'delivery_id');
    }

    /** Motifs d'une sortie manuelle : clé enregistrée => libellé. Les écarts d'inventaire (« inventory ») sont passés par l'inventaire. */
    public static function reasons(): array
    {
        return [
            'sale' => 'Vente hors devis',
            'internal_use' => 'Usage interne',
            'damage' => 'Casse ou détérioration',
            'loss' => 'Perte ou vol',
            'supplier_return' => 'Retour au fournisseur',
            'gift' => 'Don ou échantillon',
            'other' => 'Autre',
        ];
    }

    /** Où est partie la marchandise : la livraison et son client, ou le motif. */
    public function originLabel(): string
    {
        if ($this->delivery_id) {
            return 'Livraison' . ($this->delivery?->client_name ? ' à ' . $this->delivery->client_name : '');
        }
        if ($this->reason === 'inventory') {
            return 'Écart d’inventaire';
        }

        return self::reasons()[$this->reason] ?? 'Motif non renseigné';
    }
}
